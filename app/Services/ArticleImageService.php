<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Prism\Prism\Prism;

class ArticleImageService
{
    protected const WIDTH = 1200;

    protected const HEIGHT = 800;

    /**
     * Regex pattern to match image placeholders with explicit style.
     * Format: [IMAGE: description – style: stylename] or [IMAGE: description - style: stylename]
     * Also accepts comma separator: [IMAGE: description, style: stylename]
     */
    protected const PLACEHOLDER_PATTERN = '/\[IMAGE:\s*(.+?)\s*(?:–|-|,)\s*style:\s*(\w+)\s*\]/iu';

    /**
     * Regex pattern to match visual asset placeholders without explicit style.
     * Matches: [INFOGRAPHIC: ...], [DIAGRAM: ...], [CHART: ...], [ILLUSTRATION: ...], [FLOWCHART: ...], [GRAPH: ...]
     */
    protected const VISUAL_ASSET_PATTERN = '/\[(INFOGRAPHIC|DIAGRAM|CHART|ILLUSTRATION|FLOWCHART|GRAPH|SCREENSHOT|MOCKUP):\s*(.+?)\s*\]/iu';

    /**
     * Default styles for different visual asset types.
     */
    protected const VISUAL_ASSET_STYLES = [
        'infographic' => 'illustration',
        'diagram' => 'illustration',
        'chart' => 'illustration',
        'illustration' => 'illustration',
        'flowchart' => 'illustration',
        'graph' => 'illustration',
        'screenshot' => 'realistic',
        'mockup' => 'realistic',
    ];

    /**
     * Providers that support image generation.
     * Gemini (Nano Banana) is the preferred provider for image generation.
     */
    protected const IMAGE_GENERATION_PROVIDERS = ['gemini', 'openai'];

    /**
     * Nano Banana model for image generation (Gemini 2.0 Flash with image output).
     */
    protected const NANO_BANANA_MODEL = 'gemini-2.0-flash-exp';

    protected ImageManager $imageManager;

    public function __construct(
        protected Prism $prism,
        protected UsageTrackingService $usageTrackingService
    ) {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Process all image placeholders in an article.
     *
     * @return array{article: Article, images_generated: int, errors: array}
     */
    public function processArticleImages(Article $article, AiProvider $aiProvider): array
    {
        $content = $article->content_markdown ?? $article->content;

        // Fix any escaped brackets in image markdown syntax that may have been
        // introduced by the markdown serializer (e.g., !\[... -> ![...)
        $content = $this->fixEscapedImageSyntax($content);

        $placeholders = $this->findImagePlaceholders($content);

        if (empty($placeholders)) {
            return [
                'article' => $article,
                'images_generated' => 0,
                'errors' => [],
            ];
        }

        $errors = [];
        $imagesGenerated = 0;

        foreach ($placeholders as $placeholder) {
            try {
                $image = $this->generateInlineImage(
                    $placeholder['description'],
                    $placeholder['style'],
                    $article,
                    $aiProvider
                );

                // Replace placeholder with markdown image
                $markdownImage = "![{$placeholder['description']}]({$image->url})";
                $content = str_replace($placeholder['full_match'], $markdownImage, $content);

                $imagesGenerated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'placeholder' => $placeholder['full_match'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update article content
        $article->content_markdown = $content;
        $article->content = $content;
        $article->save();

        return [
            'article' => $article,
            'images_generated' => $imagesGenerated,
            'errors' => $errors,
        ];
    }

    /**
     * Find all image placeholders in content.
     *
     * @return array<array{full_match: string, description: string, style: string, type: string}>
     */
    public function findImagePlaceholders(string $content): array
    {
        $placeholders = [];

        // Match [IMAGE: description – style: stylename] format
        preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $placeholders[] = [
                'full_match' => $match[0],
                'description' => trim($match[1]),
                'style' => strtolower(trim($match[2])),
                'type' => 'image',
            ];
        }

        // Match [INFOGRAPHIC: ...], [DIAGRAM: ...], etc. format
        preg_match_all(self::VISUAL_ASSET_PATTERN, $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $assetType = strtolower(trim($match[1]));
            $description = trim($match[2]);

            // Prepend the asset type to the description for better context
            $fullDescription = ucfirst($assetType).': '.$description;

            $placeholders[] = [
                'full_match' => $match[0],
                'description' => $fullDescription,
                'style' => self::VISUAL_ASSET_STYLES[$assetType] ?? 'illustration',
                'type' => $assetType,
            ];
        }

        return $placeholders;
    }

    /**
     * Fix escaped brackets in markdown image syntax.
     *
     * The markdown serializer sometimes escapes brackets, turning valid image syntax
     * like ![alt](url) into !\[alt](url). This method fixes those escaped brackets.
     */
    protected function fixEscapedImageSyntax(string $content): string
    {
        // Fix !\[ -> ![ (escaped opening bracket in image syntax)
        $content = preg_replace('/!\\\\\[/', '![', $content);

        // Fix \]( -> ]( (escaped closing bracket before URL)
        $content = preg_replace('/\\\\\]\(/', '](', $content);

        return $content;
    }

    /**
     * Generate an inline image for the article.
     */
    public function generateInlineImage(
        string $description,
        string $style,
        Article $article,
        AiProvider $aiProvider
    ): Image {
        $project = $article->project;
        $brandColor = $project->brand_color ?? '#3B82F6';

        // Validate style
        $validStyles = ['sketch', 'watercolor', 'illustration', 'cinematic', 'brand_text', 'photo', 'realistic'];
        if (! in_array($style, $validStyles)) {
            $style = 'illustration';
        }

        // Build the prompt
        $prompt = $this->buildPrompt($description, $style, $brandColor);

        // Generate the image
        $usedAiGeneration = in_array($aiProvider->provider, self::IMAGE_GENERATION_PROVIDERS);
        if ($usedAiGeneration) {
            $imagePath = $this->generateWithPrism($aiProvider, $prompt);

            // Log usage for AI image generation
            $imageModel = $this->getImageModel($aiProvider);
            $providerOptions = $this->getProviderOptions($aiProvider, $imageModel);

            $this->usageTrackingService->logImageGeneration(
                user: $project->user,
                article: $article,
                aiProvider: $aiProvider,
                model: $imageModel,
                imageCount: 1,
                size: $providerOptions['size'] ?? null,
                quality: $providerOptions['quality'] ?? null,
                operation: 'inline_image',
                metadata: [
                    'style' => $style,
                    'description' => $description,
                    'brand_color' => $brandColor,
                ]
            );
        } else {
            // Fallback to placeholder image
            $imagePath = $this->createPlaceholderImage($description, $brandColor);
        }

        // Get file info
        $fullPath = Storage::disk('public')->path($imagePath);
        $fileSize = filesize($fullPath);

        // Create image record
        $image = Image::create([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'type' => 'inline',
            'source' => 'ai_generated',
            'prompt' => $prompt,
            'path' => $imagePath,
            'url' => Storage::disk('public')->url($imagePath),
            'alt_text' => $description,
            'caption' => null,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'file_size' => $fileSize,
            'mime_type' => 'image/png',
            'metadata' => [
                'style' => $style,
                'original_description' => $description,
                'brand_color' => $brandColor,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        return $image;
    }

    /**
     * Build the AI prompt for inline image generation.
     */
    protected function buildPrompt(string $description, string $style, string $brandColor): string
    {
        $colorName = $this->getColorName($brandColor);

        $prompt = "{$description}. ";

        switch ($style) {
            case 'sketch':
                $prompt .= 'Hand-drawn pencil sketch style. Light strokes, artistic illustration. ';
                $prompt .= "Black and white with {$colorName} accent highlights. ";
                break;

            case 'watercolor':
                $prompt .= 'Soft watercolor painting style. Gentle color washes, artistic feel. ';
                $prompt .= "{$colorName} as the primary accent color. ";
                break;

            case 'cinematic':
                $prompt .= 'Cinematic photography style. Professional lighting, dramatic composition. ';
                $prompt .= "High quality, editorial look. {$colorName} color grading. ";
                break;

            case 'brand_text':
                $prompt .= 'Clean corporate style. Modern, professional aesthetic. ';
                $prompt .= "Minimalist design with {$colorName} brand colors. ";
                break;

            case 'photo':
            case 'realistic':
                $prompt .= 'Photorealistic style. High quality stock photo look. ';
                $prompt .= 'Natural lighting, professional composition. ';
                break;

            case 'illustration':
            default:
                $prompt .= 'Modern flat illustration style. Clean vector-like graphics. ';
                $prompt .= "{$colorName} as accent color. Professional blog illustration. ";
                break;
        }

        $prompt .= 'Do not include any text or typography in the image. ';
        $prompt .= 'High quality, suitable for blog article.';

        return $prompt;
    }

    /**
     * Generate image using Prism.
     */
    protected function generateWithPrism(AiProvider $aiProvider, string $prompt): string
    {
        $config = [];
        if ($aiProvider->api_key) {
            $config['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $config['url'] = $aiProvider->api_endpoint;
        }

        $imageModel = match ($aiProvider->provider) {
            // Nano Banana - Gemini's native image generation (preferred)
            'gemini' => config('services.gemini.image_model', self::NANO_BANANA_MODEL),
            // OpenAI fallback - use gpt-image-1 if configured, otherwise dall-e-3
            'openai' => config('services.openai.image_model', 'dall-e-3'),
            default => throw new \RuntimeException("Provider {$aiProvider->provider} does not support image generation. Use Gemini (Nano Banana) for best results."),
        };

        // Provider-specific options for better quality
        $providerOptions = match ($aiProvider->provider) {
            'openai' => $imageModel === 'gpt-image-1'
                ? ['quality' => 'high', 'size' => '1536x1024']      // GPT Image settings
                : ['quality' => 'hd', 'style' => 'natural', 'size' => '1792x1024'],  // DALL-E 3 HD
            'gemini' => [
                'aspect_ratio' => '3:2',     // Close to our 1200x800 target
            ],
            default => [],
        };

        $response = $this->prism->image()
            ->using($aiProvider->provider, $imageModel, $config)
            ->withClientOptions(['timeout' => 120])
            ->withProviderOptions($providerOptions)
            ->withPrompt($prompt)
            ->generate();

        $generatedImage = $response->firstImage();

        if (! $generatedImage) {
            throw new \RuntimeException('No image was generated');
        }

        // Get image content
        if ($generatedImage->base64) {
            $imageContent = base64_decode($generatedImage->base64);
        } elseif ($generatedImage->url) {
            $imageContent = file_get_contents($generatedImage->url);
        } else {
            throw new \RuntimeException('Generated image has no content');
        }

        // Resize to standard dimensions
        $image = $this->imageManager->read($imageContent);
        $image->cover(self::WIDTH, self::HEIGHT);

        // Save to storage
        $filename = 'article-images/'.Str::random(32).'.png';
        Storage::disk('public')->put($filename, $image->toPng()->toString());

        return $filename;
    }

    /**
     * Create a placeholder image for providers without image generation.
     */
    protected function createPlaceholderImage(string $description, string $brandColor): string
    {
        $image = $this->imageManager->create(self::WIDTH, self::HEIGHT);
        $image->fill('#f8f9fa');

        // Add some visual interest with brand color
        $rgb = $this->hexToRgb($brandColor);

        // Draw a subtle pattern
        for ($i = 0; $i < 10; $i++) {
            $x = rand(50, self::WIDTH - 50);
            $y = rand(50, self::HEIGHT - 50);
            $radius = rand(30, 100);
            $image->drawCircle($x, $y, function ($circle) use ($radius, $brandColor) {
                $circle->radius($radius);
                $circle->background($brandColor.'15');
            });
        }

        $filename = 'article-images/placeholder-'.Str::random(32).'.png';
        Storage::disk('public')->put($filename, $image->toPng()->toString());

        return $filename;
    }

    /**
     * Get a descriptive color name from hex code.
     */
    protected function getColorName(string $hex): string
    {
        $rgb = $this->hexToRgb($hex);
        $r = $rgb['r'];
        $g = $rgb['g'];
        $b = $rgb['b'];

        if ($r > 100 && $b > 150 && $g < 100 && $b > $g) {
            return 'purple';
        }
        if ($r > 180 && $b > 180 && $g < 150) {
            return 'magenta';
        }
        if ($r > 200 && $g < 100 && $b < 100) {
            return 'red';
        }
        if ($r < 100 && $g > 200 && $b < 100) {
            return 'green';
        }
        if ($r < 100 && $g < 100 && $b > 200) {
            return 'blue';
        }
        if ($r > 200 && $g > 200 && $b < 100) {
            return 'yellow';
        }
        if ($r < 100 && $g > 200 && $b > 200) {
            return 'cyan';
        }
        if ($r > 200 && $g > 150 && $b < 100) {
            return 'orange';
        }

        if ($b >= $r && $b >= $g) {
            return 'blue';
        }
        if ($g >= $r && $g >= $b) {
            return 'green';
        }

        return 'warm';
    }

    /**
     * Convert hex color to RGB.
     *
     * @return array{r: int, g: int, b: int}
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Get the appropriate image generation model for the provider.
     * Nano Banana (Gemini) is the preferred provider for all image generation.
     */
    protected function getImageModel(AiProvider $aiProvider): string
    {
        return match ($aiProvider->provider) {
            // Nano Banana - Gemini's native image generation (preferred)
            'gemini' => config('services.gemini.image_model', self::NANO_BANANA_MODEL),
            // OpenAI fallback
            'openai' => config('services.openai.image_model', 'dall-e-3'),
            default => throw new \RuntimeException("Provider {$aiProvider->provider} does not support image generation. Use Gemini (Nano Banana) for best results."),
        };
    }

    /**
     * Get provider-specific options for image generation.
     *
     * @return array<string, mixed>
     */
    protected function getProviderOptions(AiProvider $aiProvider, string $imageModel): array
    {
        return match ($aiProvider->provider) {
            'openai' => $imageModel === 'gpt-image-1'
                ? ['quality' => 'high', 'size' => '1536x1024']
                : ['quality' => 'hd', 'style' => 'natural', 'size' => '1792x1024'],
            'gemini' => ['aspect_ratio' => '3:2'],
            default => [],
        };
    }
}
