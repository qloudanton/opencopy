<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Prism\Prism\Prism;

class FeaturedImageService
{
    protected const WIDTH = 1312;

    protected const HEIGHT = 736;

    /**
     * Providers that support image generation via Prism.
     * OpenAI (DALL-E) is the preferred provider for image generation.
     */
    protected const IMAGE_GENERATION_PROVIDERS = ['openai', 'gemini'];

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
     * Generate a featured image for an article.
     *
     * @return array{image: Image, url: string}
     */
    public function generate(Article $article, AiProvider $aiProvider, ?string $styleOverride = null): array
    {
        $project = $article->project;
        $style = $styleOverride ?? $project->image_style ?? 'illustration';
        $brandColor = $project->brand_color ?? '#3B82F6';

        // Delete existing featured image if any
        $existingImage = $article->images()->where('type', 'featured')->first();
        if ($existingImage) {
            if ($existingImage->path) {
                Storage::disk('public')->delete($existingImage->path);
            }
            $existingImage->delete();
        }

        // Generate background image with AI
        $usedAiGeneration = in_array($aiProvider->provider, self::IMAGE_GENERATION_PROVIDERS);
        $backgroundPath = $this->generateBackground($article, $aiProvider, $style, $brandColor);

        // Log usage for AI image generation
        if ($usedAiGeneration) {
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
                operation: 'featured_image',
                metadata: [
                    'style' => $style,
                    'brand_color' => $brandColor,
                ]
            );
        }

        // Overlay text on the background
        $finalPath = $this->overlayText($backgroundPath, $article->title, $brandColor, $style);

        // Get file info
        $fullPath = Storage::disk('public')->path($finalPath);
        $fileSize = filesize($fullPath);

        // Create image record
        $image = Image::create([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'type' => 'featured',
            'source' => 'ai_generated',
            'prompt' => $this->buildPrompt($article, $style, $brandColor),
            'path' => $finalPath,
            'url' => Storage::disk('public')->url($finalPath),
            'alt_text' => $article->title,
            'caption' => null,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'file_size' => $fileSize,
            'mime_type' => 'image/png',
            'metadata' => [
                'style' => $style,
                'brand_color' => $brandColor,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        // Clean up background image
        if ($backgroundPath !== $finalPath) {
            Storage::disk('public')->delete($backgroundPath);
        }

        return [
            'image' => $image,
            'url' => $image->url,
        ];
    }

    /**
     * Generate background image using AI.
     */
    protected function generateBackground(Article $article, AiProvider $aiProvider, string $style, string $brandColor): string
    {
        $prompt = $this->buildPrompt($article, $style, $brandColor);

        // Check if provider supports image generation
        if (in_array($aiProvider->provider, self::IMAGE_GENERATION_PROVIDERS)) {
            return $this->generateWithPrism($aiProvider, $prompt);
        }

        // Fallback: create a gradient background
        return $this->createGradientBackground($brandColor, $style);
    }

    /**
     * Generate image using Prism (supports OpenAI DALL-E and Gemini Imagen).
     */
    protected function generateWithPrism(AiProvider $aiProvider, string $prompt): string
    {
        $providerConfig = $this->buildProviderConfig($aiProvider);

        // Determine the image model based on provider
        $imageModel = $this->getImageModel($aiProvider);

        // Provider-specific options for better quality
        $providerOptions = $this->getProviderOptions($aiProvider, $imageModel);

        $response = $this->prism->image()
            ->using($aiProvider->provider, $imageModel, $providerConfig)
            ->withClientOptions(['timeout' => 120])
            ->withProviderOptions($providerOptions)
            ->withPrompt($prompt)
            ->generate();

        $generatedImage = $response->firstImage();

        if (! $generatedImage) {
            throw new \RuntimeException('No image was generated');
        }

        // Get image content from base64 or URL
        if ($generatedImage->base64) {
            $imageContent = base64_decode($generatedImage->base64);
        } elseif ($generatedImage->url) {
            $imageContent = file_get_contents($generatedImage->url);
        } else {
            throw new \RuntimeException('Generated image has no content');
        }

        // Resize to exact dimensions
        $image = $this->imageManager->read($imageContent);
        $image->cover(self::WIDTH, self::HEIGHT);

        // Save to storage
        $filename = 'featured-images/bg-'.Str::random(32).'.png';
        Storage::disk('public')->put($filename, $image->toPng()->toString());

        return $filename;
    }

    /**
     * Build provider configuration for Prism.
     *
     * @return array<string, mixed>
     */
    protected function buildProviderConfig(AiProvider $aiProvider): array
    {
        $config = [];

        if ($aiProvider->api_key) {
            $config['api_key'] = $aiProvider->api_key;
        }

        if ($aiProvider->api_endpoint) {
            $config['url'] = $aiProvider->api_endpoint;
        }

        return $config;
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
            // OpenAI fallback - use gpt-image-1 if configured, otherwise dall-e-3
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
            // Gemini 2.0 Flash doesn't support aspect_ratio parameter
            'gemini' => [],
            default => [],
        };
    }

    /**
     * Create a gradient background as fallback.
     */
    protected function createGradientBackground(string $brandColor, string $style): string
    {
        $image = $this->imageManager->create(self::WIDTH, self::HEIGHT);

        // Fill with white/light gray background
        $image->fill('#f8f9fa');

        // Add brand color elements around the edges
        $this->addEdgeDecoration($image, $brandColor, $style);

        $filename = 'featured-images/bg-'.Str::random(32).'.png';
        Storage::disk('public')->put($filename, $image->toPng()->toString());

        return $filename;
    }

    /**
     * Add decorative elements around the edges of the image, keeping the center clear.
     */
    protected function addEdgeDecoration(\Intervention\Image\Interfaces\ImageInterface $image, string $brandColor, string $style): void
    {
        // Define the "safe zone" - center area to keep clear (30% margin on each side)
        $marginX = (int) (self::WIDTH * 0.20);
        $marginY = (int) (self::HEIGHT * 0.20);

        // Edge zones: top, bottom, left, right
        $edges = [
            'top' => ['x_min' => 0, 'x_max' => self::WIDTH, 'y_min' => 0, 'y_max' => $marginY],
            'bottom' => ['x_min' => 0, 'x_max' => self::WIDTH, 'y_min' => self::HEIGHT - $marginY, 'y_max' => self::HEIGHT],
            'left' => ['x_min' => 0, 'x_max' => $marginX, 'y_min' => $marginY, 'y_max' => self::HEIGHT - $marginY],
            'right' => ['x_min' => self::WIDTH - $marginX, 'x_max' => self::WIDTH, 'y_min' => $marginY, 'y_max' => self::HEIGHT - $marginY],
        ];

        switch ($style) {
            case 'sketch':
                // Sketchy lines around edges
                foreach ($edges as $edge) {
                    for ($i = 0; $i < 10; $i++) {
                        $x1 = rand($edge['x_min'], $edge['x_max']);
                        $y1 = rand($edge['y_min'], $edge['y_max']);
                        $x2 = $x1 + rand(-60, 60);
                        $y2 = $y1 + rand(-30, 30);
                        $image->drawLine(function ($line) use ($x1, $y1, $x2, $y2, $brandColor) {
                            $line->from($x1, $y1);
                            $line->to($x2, $y2);
                            $line->color($brandColor);
                            $line->width(rand(1, 3));
                        });
                    }
                }
                break;

            case 'watercolor':
                // Soft circles around edges
                foreach ($edges as $edge) {
                    for ($i = 0; $i < 4; $i++) {
                        $x = rand($edge['x_min'] + 30, max($edge['x_min'] + 30, $edge['x_max'] - 30));
                        $y = rand($edge['y_min'] + 30, max($edge['y_min'] + 30, $edge['y_max'] - 30));
                        $radius = rand(40, 120);
                        $image->drawCircle($x, $y, function ($circle) use ($radius, $brandColor) {
                            $circle->radius($radius);
                            $circle->background($brandColor.'20');
                        });
                    }
                }
                break;

            case 'cinematic':
            case 'brand_text':
                // Bold rectangles around edges
                foreach ($edges as $edge) {
                    for ($i = 0; $i < 2; $i++) {
                        $x = rand($edge['x_min'], max($edge['x_min'], $edge['x_max'] - 100));
                        $y = rand($edge['y_min'], max($edge['y_min'], $edge['y_max'] - 80));
                        $width = rand(60, 150);
                        $height = rand(30, 100);
                        $image->drawRectangle($x, $y, function ($rect) use ($width, $height, $brandColor) {
                            $rect->size($width, $height);
                            $rect->background($brandColor.'25');
                        });
                    }
                }
                break;

            case 'illustration':
            default:
                // Mix of shapes around edges
                foreach ($edges as $edge) {
                    for ($i = 0; $i < 3; $i++) {
                        $x = rand($edge['x_min'] + 20, max($edge['x_min'] + 20, $edge['x_max'] - 50));
                        $y = rand($edge['y_min'] + 20, max($edge['y_min'] + 20, $edge['y_max'] - 50));
                        if ($i % 2 === 0) {
                            $radius = rand(25, 60);
                            $image->drawCircle($x, $y, function ($circle) use ($radius, $brandColor) {
                                $circle->radius($radius);
                                $circle->background($brandColor.'30');
                            });
                        } else {
                            $size = rand(30, 70);
                            $image->drawRectangle($x, $y, function ($rect) use ($size, $brandColor) {
                                $rect->size($size, $size);
                                $rect->background($brandColor.'20');
                            });
                        }
                    }
                }
                break;
        }
    }

    /**
     * Add subtle texture based on image style.
     */
    protected function addStyleTexture(\Intervention\Image\Interfaces\ImageInterface $image, string $style): void
    {
        // Add some visual interest based on style
        switch ($style) {
            case 'sketch':
                // Add some subtle lines for sketch effect
                for ($i = 0; $i < 50; $i++) {
                    $x1 = rand(0, self::WIDTH);
                    $y1 = rand(0, self::HEIGHT);
                    $x2 = $x1 + rand(-100, 100);
                    $y2 = $y1 + rand(-50, 50);
                    $image->drawLine(function ($line) use ($x1, $y1, $x2, $y2) {
                        $line->from($x1, $y1);
                        $line->to($x2, $y2);
                        $line->color('rgba(255, 255, 255, 0.1)');
                    });
                }
                break;

            case 'watercolor':
                // Add soft circular shapes for watercolor effect
                for ($i = 0; $i < 20; $i++) {
                    $x = rand(0, self::WIDTH);
                    $y = rand(0, self::HEIGHT);
                    $radius = rand(50, 200);
                    $image->drawCircle($x, $y, function ($circle) use ($radius) {
                        $circle->radius($radius);
                        $circle->background('rgba(255, 255, 255, 0.05)');
                    });
                }
                break;

            case 'cinematic':
                // Add dramatic vignette-like effect with overlapping circles
                for ($i = 0; $i < 15; $i++) {
                    $x = rand(0, self::WIDTH);
                    $y = rand(0, self::HEIGHT);
                    $radius = rand(100, 300);
                    $image->drawCircle($x, $y, function ($circle) use ($radius) {
                        $circle->radius($radius);
                        $circle->background('rgba(0, 0, 0, 0.08)');
                    });
                }
                break;

            case 'brand_text':
                // Minimal texture - just a few subtle geometric accents
                for ($i = 0; $i < 5; $i++) {
                    $x = rand(0, self::WIDTH);
                    $y = rand(0, self::HEIGHT);
                    $width = rand(100, 400);
                    $height = rand(10, 30);
                    $image->drawRectangle($x, $y, function ($rectangle) use ($width, $height) {
                        $rectangle->size($width, $height);
                        $rectangle->background('rgba(255, 255, 255, 0.08)');
                    });
                }
                break;

            case 'illustration':
            default:
                // Add geometric shapes for illustration effect
                for ($i = 0; $i < 10; $i++) {
                    $x = rand(0, self::WIDTH);
                    $y = rand(0, self::HEIGHT);
                    $width = rand(50, 200);
                    $height = rand(50, 200);
                    $image->drawRectangle($x, $y, function ($rectangle) use ($width, $height) {
                        $rectangle->size($width, $height);
                        $rectangle->background('rgba(255, 255, 255, 0.03)');
                    });
                }
                break;
        }
    }

    /**
     * Overlay text on the background image.
     */
    protected function overlayText(string $backgroundPath, string $title, string $brandColor, string $style): string
    {
        $fullPath = Storage::disk('public')->path($backgroundPath);
        $image = $this->imageManager->read($fullPath);

        // Parse title into main title and subtitle
        $titleParts = $this->parseTitle($title);
        $mainTitle = $titleParts['main'];
        $subtitle = $titleParts['subtitle'];

        // Calculate font sizes (larger for impact)
        // If no subtitle, use slightly smaller font to allow more lines
        $mainFontSize = $subtitle
            ? $this->calculateMainFontSize($mainTitle)
            : $this->calculateMainFontSizeNoSubtitle($mainTitle);
        $subtitleFontSize = (int) ($mainFontSize * 0.7); // Subtitle is 70% of main

        // Wrap text for center area
        // If no subtitle, main title can use up to 4 lines; otherwise 2 lines each
        $mainMaxLines = $subtitle ? 2 : 4;
        $wrappedMain = $this->wrapText($mainTitle, $mainFontSize, $mainMaxLines);
        $wrappedSubtitle = $subtitle ? $this->wrapText($subtitle, $subtitleFontSize, 2) : '';

        // Calculate positions - centered text
        $centerX = self::WIDTH / 2;

        $mainLineCount = substr_count($wrappedMain, "\n") + 1;
        $mainLineHeight = $mainFontSize * 1.15;
        $mainTextHeight = $mainLineCount * $mainLineHeight;

        $subtitleLineCount = $wrappedSubtitle ? substr_count($wrappedSubtitle, "\n") + 1 : 0;
        $subtitleLineHeight = $subtitleFontSize * 1.3;
        $subtitleTextHeight = $subtitleLineCount * $subtitleLineHeight;

        $gap = $subtitle ? 10 : 0;
        $totalHeight = $mainTextHeight + $gap + $subtitleTextHeight;
        $startY = (self::HEIGHT - $totalHeight) / 2;

        // Draw main title in black, centered (serif font)
        $image->text($wrappedMain, (int) $centerX, (int) $startY, function (FontFactory $font) use ($mainFontSize) {
            $font->filename($this->getSerifFontPath());
            $font->size($mainFontSize);
            $font->color('#1a1a1a');
            $font->align('center');
            $font->valign('top');
            $font->lineHeight(1.15);
        });

        // Draw subtitle centered
        if ($wrappedSubtitle) {
            $subtitleY = $startY + $mainTextHeight + $gap;
            $subtitleText = '— '.$wrappedSubtitle;

            $image->text($subtitleText, (int) $centerX, (int) $subtitleY, function (FontFactory $font) use ($subtitleFontSize) {
                $font->filename($this->getSerifFontPath());
                $font->size($subtitleFontSize);
                $font->color('#444444');
                $font->align('center');
                $font->valign('top');
                $font->lineHeight(1.3);
            });
        }

        // Save final image
        $filename = 'featured-images/'.Str::random(32).'.png';
        Storage::disk('public')->put($filename, $image->toPng()->toString());

        return $filename;
    }

    /**
     * Parse title into main title and subtitle.
     *
     * @return array{main: string, subtitle: string|null}
     */
    protected function parseTitle(string $title): array
    {
        // Try to split on common separators: - : |
        $separators = [' - ', ': ', ' | ', ' – ', ' — '];

        foreach ($separators as $separator) {
            if (str_contains($title, $separator)) {
                $parts = explode($separator, $title, 2);
                if (count($parts) === 2) {
                    return [
                        'main' => trim($parts[0]),
                        'subtitle' => trim($parts[1]),
                    ];
                }
            }
        }

        // No separator found - use full title as main
        return [
            'main' => $title,
            'subtitle' => null,
        ];
    }

    /**
     * Calculate font size for main title (larger for impact).
     * Used when there IS a subtitle (main title limited to 2 lines).
     */
    protected function calculateMainFontSize(string $title): int
    {
        $length = strlen($title);

        if ($length <= 20) {
            return 130;
        }
        if ($length <= 35) {
            return 115;
        }
        if ($length <= 50) {
            return 100;
        }

        return 100;
    }

    /**
     * Calculate font size for main title when there's NO subtitle.
     * Uses slightly smaller font to allow up to 4 lines of text.
     */
    protected function calculateMainFontSizeNoSubtitle(string $title): int
    {
        $length = strlen($title);

        // For titles without subtitle, we allow 4 lines so we can use
        // slightly smaller fonts for longer titles to ensure readability
        if ($length <= 30) {
            return 115; // Short titles still get large font
        }
        if ($length <= 50) {
            return 100;
        }
        if ($length <= 80) {
            return 90;
        }
        if ($length <= 120) {
            return 80;
        }

        return 70; // Very long titles
    }

    /**
     * Calculate optimal font size based on title length.
     *
     * Font sizes are calibrated to remain readable when the image
     * is scaled down to thumbnail size (259 × 145 px).
     */
    protected function calculateFontSize(string $title): int
    {
        $length = strlen($title);

        // Maximum impact fonts for thumbnail readability
        if ($length <= 30) {
            return 160;
        }
        if ($length <= 50) {
            return 140;
        }
        if ($length <= 80) {
            return 120;
        }
        if ($length <= 110) {
            return 100;
        }

        return 85;
    }

    /**
     * Wrap text to fit within image width.
     */
    protected function wrapText(string $text, int $fontSize, int $maxLines = 3): string
    {
        // Text area is ~70% of image width for centered text (leaving margins for edge decorations)
        $maxWidth = self::WIDTH * 0.85;
        $charWidth = $fontSize * 0.48; // Approximate character width for serif
        $maxCharsPerLine = (int) floor($maxWidth / $charWidth);

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine ? $currentLine.' '.$word : $word;

            if (strlen($testLine) <= $maxCharsPerLine) {
                $currentLine = $testLine;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        // Limit to max lines
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '.').'...';
        }

        return implode("\n", $lines);
    }

    /**
     * Build the AI prompt for background generation.
     */
    protected function buildPrompt(Article $article, string $style, string $brandColor): string
    {
        $keyword = $article->keyword?->keyword ?? 'professional';
        $colorName = $this->getColorName($brandColor);

        // Concept: almost empty white marble with tiny corner accents only
        $basePrompt = 'Clean white marble desk, top-down photography. ';
        $basePrompt .= 'CENTER IS EMPTY: Only white/gray marble veins visible in middle - no objects. ';
        $basePrompt .= "Corners have small items cropped by edges: {$colorName} pencil, succulent plant, ";
        $basePrompt .= 'binder clip, paper clips, eraser - scattered at angles in corners only. ';
        $basePrompt .= 'All items touch and extend past image boundaries. ';
        $basePrompt .= 'NO paper, NO notebook, NO documents anywhere in the image. ';
        $basePrompt .= "Aesthetic flat lay. {$colorName}/purple accents. Soft natural light. ";

        switch ($style) {
            case 'sketch':
                $basePrompt .= 'Hand-drawn sketch style. Pencil drawing aesthetic. ';
                $basePrompt .= 'Light pencil strokes, artistic illustration look. ';
                break;

            case 'watercolor':
                $basePrompt .= 'Soft watercolor painting style. Gentle washes of color. ';
                $basePrompt .= 'Dreamy, artistic, pastel tones. ';
                break;

            case 'cinematic':
                $basePrompt .= 'Cinematic photography style. Professional lighting. ';
                $basePrompt .= 'Subtle shadows, depth of field blur on edge items. ';
                break;

            case 'brand_text':
                $basePrompt .= 'Clean corporate photography. Minimal modern aesthetic. ';
                $basePrompt .= 'Sharp, professional, geometric items only. ';
                break;

            case 'illustration':
            default:
                $basePrompt .= 'Flat illustration style. Simple vector-like graphics. ';
                $basePrompt .= 'Clean lines, minimal shading, modern design. ';
                break;
        }

        $basePrompt .= 'NO text, words, or readable writing on any items. ';
        $basePrompt .= 'Items partially visible at edges (cropped). Empty white center for text overlay.';

        return $basePrompt;
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

        // Check for purple/magenta first (high R and B, low G)
        // This catches colors like #a904d7, #9b59b6, #8e44ad
        if ($r > 100 && $b > 150 && $g < 100 && $b > $g) {
            return 'purple';
        }

        // Pink/magenta (high R and B, moderate G)
        if ($r > 180 && $b > 180 && $g < 150) {
            return 'magenta';
        }

        // Simple color categorization
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

        // Default to the dominant color
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
     * Darken a color by a given amount.
     *
     * @param  array{r: int, g: int, b: int}  $rgb
     * @return array{r: int, g: int, b: int}
     */
    protected function darkenColor(array $rgb, float $amount): array
    {
        return [
            'r' => (int) max(0, $rgb['r'] * (1 - $amount)),
            'g' => (int) max(0, $rgb['g'] * (1 - $amount)),
            'b' => (int) max(0, $rgb['b'] * (1 - $amount)),
        ];
    }

    /**
     * Lighten a color by a given amount.
     *
     * @param  array{r: int, g: int, b: int}  $rgb
     * @return array{r: int, g: int, b: int}
     */
    protected function lightenColor(array $rgb, float $amount): array
    {
        return [
            'r' => (int) min(255, $rgb['r'] + (255 - $rgb['r']) * $amount),
            'g' => (int) min(255, $rgb['g'] + (255 - $rgb['g']) * $amount),
            'b' => (int) min(255, $rgb['b'] + (255 - $rgb['b']) * $amount),
        ];
    }

    /**
     * Get the path to a suitable font file.
     */
    protected function getFontPath(): string
    {
        // Use system fonts
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', // Linux
            '/System/Library/Fonts/Helvetica.ttc', // macOS
            '/System/Library/Fonts/SFNSDisplay.ttf', // macOS newer
            'C:\\Windows\\Fonts\\arial.ttf', // Windows
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Intervention Image default font (GD bundled)
        return 5; // GD built-in font
    }

    /**
     * Get the path to a serif font file.
     */
    protected function getSerifFontPath(): string
    {
        // Use system serif fonts
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf', // Linux
            '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf', // macOS
            '/System/Library/Fonts/Times.ttc', // macOS
            '/System/Library/Fonts/NewYork.ttf', // macOS newer
            '/System/Library/Fonts/Supplemental/Georgia Bold.ttf', // macOS
            'C:\\Windows\\Fonts\\timesbd.ttf', // Windows - Times New Roman Bold
            'C:\\Windows\\Fonts\\georgia.ttf', // Windows - Georgia
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback to sans-serif if no serif found
        return $this->getFontPath();
    }
}
