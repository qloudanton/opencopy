import { ContentEditor } from '@/components/editor';
import InputError from '@/components/input-error';
import { SeoScore } from '@/components/seo-score';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Check,
    Clipboard,
    Download,
    Image,
    RefreshCw,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface SeoBreakdownCategory {
    score: number;
    max: number;
    details: Record<string, unknown>;
}

interface SeoBreakdown {
    keyword_optimization: SeoBreakdownCategory;
    content_structure: SeoBreakdownCategory;
    content_length: SeoBreakdownCategory;
    meta_quality: SeoBreakdownCategory;
    enrichment: SeoBreakdownCategory;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    meta_description: string | null;
    content: string;
    content_markdown: string;
    seo_score: number | null;
    generation_metadata: {
        seo_breakdown?: SeoBreakdown;
    } | null;
    project: {
        id: number;
        name: string;
    };
    keyword: {
        id: number;
        keyword: string;
    } | null;
}

interface Project {
    id: number;
    name: string;
    image_style:
        | 'sketch'
        | 'watercolor'
        | 'illustration'
        | 'cinematic'
        | 'brand_text'
        | null;
    brand_color: string | null;
}

interface FeaturedImage {
    id: number;
    url: string;
    width: number;
    height: number;
}

interface Props {
    project: Project;
    article: Article;
    featuredImage: FeaturedImage | null;
}

export default function Edit({
    project,
    article,
    featuredImage: initialFeaturedImage,
}: Props) {
    const [copied, setCopied] = useState(false);
    const [isImproving, setIsImproving] = useState(false);
    const [isRecalculating, setIsRecalculating] = useState(false);
    const [isGeneratingImage, setIsGeneratingImage] = useState(false);
    const [isDeletingImage, setIsDeletingImage] = useState(false);
    const [isEnriching, setIsEnriching] = useState(false);
    const [currentSeoScore, setCurrentSeoScore] = useState<number | null>(
        article.seo_score,
    );
    const [currentSeoBreakdown, setCurrentSeoBreakdown] = useState(
        article.generation_metadata?.seo_breakdown,
    );
    const [featuredImage, setFeaturedImage] = useState<FeaturedImage | null>(
        initialFeaturedImage,
    );
    const [selectedStyle, setSelectedStyle] = useState<string>(
        project.image_style || 'illustration',
    );
    const { csrf_token } = usePage<{ csrf_token: string }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        ...(article.keyword
            ? [
                  {
                      title: 'Keywords',
                      href: `/projects/${project.id}/keywords`,
                  },
                  {
                      title: article.keyword.keyword,
                      href: `/projects/${project.id}/keywords/${article.keyword.id}`,
                  },
              ]
            : []),
        {
            title: article.title,
            href: `/projects/${project.id}/articles/${article.id}`,
        },
        {
            title: 'Edit',
            href: `/projects/${project.id}/articles/${article.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: article.title,
        slug: article.slug,
        meta_description: article.meta_description || '',
        content: article.content_markdown || article.content,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/articles/${article.id}`);
    }

    function handleCopyToClipboard() {
        navigator.clipboard.writeText(data.content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function handleDownload() {
        const blob = new Blob([data.content], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${article.slug}.md`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    async function handleRegenerateImage(options: {
        src: string;
        alt: string;
        style: string;
        prompt: string;
    }): Promise<string | null> {
        return generateImage(options.style, options.prompt);
    }

    async function handleGenerateImage(options: {
        style: string;
        prompt: string;
    }): Promise<string | null> {
        return generateImage(options.style, options.prompt);
    }

    async function generateImage(
        style: string,
        prompt: string,
    ): Promise<string | null> {
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/regenerate-inline-image`,
                {
                    style,
                    prompt,
                },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            if (response.data.success) {
                toast.success('Image generated successfully');
                return response.data.url;
            }

            toast.error(response.data.error || 'Failed to generate image');
            return null;
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to generate image');
            }
            return null;
        }
    }

    interface YouTubeVideo {
        id: string;
        title: string;
        description: string;
        thumbnail: string;
        channelTitle: string;
        url: string;
    }

    async function handleSearchYouTube(query: string): Promise<YouTubeVideo[]> {
        try {
            const response = await axios.post(
                `/projects/${project.id}/youtube-search`,
                { query },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            return response.data.videos || [];
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to search YouTube');
            }
            return [];
        }
    }

    async function handleImprove(improvementType: string) {
        setIsImproving(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/improve`,
                { improvement_type: improvementType },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            const result = response.data;

            // Apply the improvement to the form
            if (result.field === 'title') {
                setData('title', result.value);
            } else if (result.field === 'meta_description') {
                setData('meta_description', result.value);
            } else if (result.field === 'content') {
                setData('content', result.value);
            }

            toast.success(result.message);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to apply improvement');
            }
        } finally {
            setIsImproving(false);
        }
    }

    async function handleRecalculateSeo() {
        setIsRecalculating(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/recalculate-seo`,
                {
                    title: data.title,
                    meta_description: data.meta_description,
                    content: data.content,
                },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            setCurrentSeoScore(response.data.score);
            setCurrentSeoBreakdown(response.data.breakdown);
            toast.success('SEO score recalculated');
        } catch {
            toast.error('Failed to recalculate SEO score');
        } finally {
            setIsRecalculating(false);
        }
    }

    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = useCallback(() => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    }, []);

    const pollForStatus = useCallback(async () => {
        try {
            const response = await axios.get(
                `/projects/${project.id}/articles/${article.id}/featured-image-status`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            const { status, image, error } = response.data;

            if (status === 'completed' && image) {
                stopPolling();
                setFeaturedImage(image);
                setIsGeneratingImage(false);
                toast.success('Featured image generated successfully');
            } else if (status === 'failed') {
                stopPolling();
                setIsGeneratingImage(false);
                toast.error(error || 'Failed to generate featured image');
            }
            // If status is 'queued' or 'processing', keep polling
        } catch {
            stopPolling();
            setIsGeneratingImage(false);
            toast.error('Failed to check image generation status');
        }
    }, [project.id, article.id, stopPolling]);

    // Cleanup polling on unmount
    useEffect(() => {
        return () => stopPolling();
    }, [stopPolling]);

    async function handleGenerateFeaturedImage() {
        setIsGeneratingImage(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/generate-featured-image`,
                { style: selectedStyle },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            if (response.data.status === 'queued') {
                toast.info('Generating featured image...');
                // Start polling every 2 seconds
                pollingRef.current = setInterval(pollForStatus, 2000);
            } else if (response.data.image) {
                // Synchronous response (fallback)
                setFeaturedImage(response.data.image);
                setIsGeneratingImage(false);
                toast.success(response.data.message);
            }
        } catch (error) {
            setIsGeneratingImage(false);
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to generate featured image');
            }
        }
    }

    async function handleDeleteFeaturedImage() {
        setIsDeletingImage(true);
        try {
            await axios.delete(
                `/projects/${project.id}/articles/${article.id}/featured-image`,
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        Accept: 'application/json',
                    },
                },
            );

            setFeaturedImage(null);
            toast.success('Featured image deleted');
        } catch {
            toast.error('Failed to delete featured image');
        } finally {
            setIsDeletingImage(false);
        }
    }

    const enrichmentPollingRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );

    const stopEnrichmentPolling = useCallback(() => {
        if (enrichmentPollingRef.current) {
            clearInterval(enrichmentPollingRef.current);
            enrichmentPollingRef.current = null;
        }
    }, []);

    const pollForEnrichmentStatus = useCallback(async () => {
        try {
            const response = await axios.get(
                `/projects/${project.id}/articles/${article.id}/enrichment-status`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            const { status, content, results, error } = response.data;

            if (status === 'completed') {
                stopEnrichmentPolling();
                setIsEnriching(false);

                if (content) {
                    setData('content', content);
                }

                const imagesProcessed = results?.images?.processed || 0;
                const videosProcessed = results?.videos?.processed || 0;

                if (imagesProcessed > 0 || videosProcessed > 0) {
                    const parts = [];
                    if (imagesProcessed > 0) {
                        parts.push(
                            `${imagesProcessed} image${imagesProcessed > 1 ? 's' : ''}`,
                        );
                    }
                    if (videosProcessed > 0) {
                        parts.push(
                            `${videosProcessed} video${videosProcessed > 1 ? 's' : ''}`,
                        );
                    }
                    toast.success(`Enriched content: ${parts.join(', ')} added`);
                } else {
                    toast.info('No placeholders found to enrich');
                }
            } else if (status === 'failed') {
                stopEnrichmentPolling();
                setIsEnriching(false);
                toast.error(error || 'Content enrichment failed');
            }
            // If status is 'queued' or 'processing', keep polling
        } catch {
            stopEnrichmentPolling();
            setIsEnriching(false);
            toast.error('Failed to check enrichment status');
        }
    }, [project.id, article.id, stopEnrichmentPolling, setData]);

    // Cleanup enrichment polling on unmount
    useEffect(() => {
        return () => stopEnrichmentPolling();
    }, [stopEnrichmentPolling]);

    async function handleEnrichContent() {
        setIsEnriching(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/enrich`,
                {},
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            if (response.data.status === 'queued') {
                toast.info('Enriching content...');
                // Start polling every 3 seconds
                enrichmentPollingRef.current = setInterval(
                    pollForEnrichmentStatus,
                    3000,
                );
            }
        } catch (error) {
            setIsEnriching(false);
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to start content enrichment');
            }
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit - ${article.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Edit Article</h1>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleCopyToClipboard}
                        >
                            {copied ? (
                                <>
                                    <Check className="mr-2 h-4 w-4" />
                                    Copied!
                                </>
                            ) : (
                                <>
                                    <Clipboard className="mr-2 h-4 w-4" />
                                    Copy Markdown
                                </>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDownload}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Download .md
                        </Button>
                        <Button
                            size="sm"
                            onClick={handleSubmit}
                            disabled={processing}
                        >
                            <Check className="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Main form area */}
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-4 lg:col-span-2"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Article Details</CardTitle>
                                <CardDescription>
                                    Update your article information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) =>
                                            setData('title', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData(
                                                'slug',
                                                e.target.value
                                                    .toLowerCase()
                                                    .replace(/[^a-z0-9-]/g, '-')
                                                    .replace(/-+/g, '-')
                                                    .replace(/^-|-$/g, ''),
                                            )
                                        }
                                        placeholder="article-url-slug"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        URL-friendly identifier (lowercase
                                        letters, numbers, and hyphens only)
                                    </p>
                                    <InputError message={errors.slug} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="meta_description">
                                        Meta Description
                                    </Label>
                                    <Input
                                        id="meta_description"
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData(
                                                'meta_description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="SEO meta description (150-160 characters)"
                                        maxLength={255}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {data.meta_description.length}/160
                                        characters
                                    </p>
                                    <InputError
                                        message={errors.meta_description}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Content</Label>
                                    <ContentEditor
                                        content={data.content}
                                        onChange={(markdown) =>
                                            setData('content', markdown)
                                        }
                                        onRegenerateImage={
                                            handleRegenerateImage
                                        }
                                        onGenerateImage={handleGenerateImage}
                                        onSearchYouTube={handleSearchYouTube}
                                        placeholder="Start writing your article content..."
                                    />
                                    <InputError message={errors.content} />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex justify-end gap-2">
                            <Button asChild variant="outline">
                                <Link
                                    href={`/projects/${project.id}/articles/${article.id}`}
                                >
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Save Changes
                            </Button>
                        </div>
                    </form>

                    {/* Sidebar */}
                    <div className="space-y-4 lg:col-span-1">
                        {/* Featured Image Card */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Featured Image
                                </CardTitle>
                                <CardDescription>
                                    Generate a title-based featured image (1312
                                    × 736 px)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {featuredImage ? (
                                    <div className="overflow-hidden rounded-lg border">
                                        <img
                                            src={featuredImage.url}
                                            alt={article.title}
                                            className="w-full object-cover"
                                            style={{
                                                aspectRatio: '1312/736',
                                            }}
                                        />
                                    </div>
                                ) : (
                                    <div
                                        className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed bg-muted/30 p-6"
                                        style={{ aspectRatio: '1312/736' }}
                                    >
                                        <Image className="mb-2 h-8 w-8 text-muted-foreground" />
                                        <p className="text-sm text-muted-foreground">
                                            No featured image yet
                                        </p>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="image-style">Style</Label>
                                    <Select
                                        value={selectedStyle}
                                        onValueChange={setSelectedStyle}
                                    >
                                        <SelectTrigger id="image-style">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="illustration">
                                                Illustration
                                            </SelectItem>
                                            <SelectItem value="sketch">
                                                Sketch
                                            </SelectItem>
                                            <SelectItem value="watercolor">
                                                Watercolor
                                            </SelectItem>
                                            <SelectItem value="cinematic">
                                                Cinematic
                                            </SelectItem>
                                            <SelectItem value="brand_text">
                                                Brand Text
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex gap-2">
                                    <Button
                                        className="flex-1"
                                        onClick={handleGenerateFeaturedImage}
                                        disabled={isGeneratingImage}
                                    >
                                        {isGeneratingImage ? (
                                            <>
                                                <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                                {featuredImage
                                                    ? 'Regenerating...'
                                                    : 'Generating...'}
                                            </>
                                        ) : (
                                            <>
                                                {featuredImage ? (
                                                    <>
                                                        <RefreshCw className="mr-2 h-4 w-4" />
                                                        Regenerate
                                                    </>
                                                ) : (
                                                    <>
                                                        <Image className="mr-2 h-4 w-4" />
                                                        Generate Image
                                                    </>
                                                )}
                                            </>
                                        )}
                                    </Button>
                                    {featuredImage && (
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={handleDeleteFeaturedImage}
                                            disabled={isDeletingImage}
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Enrich Content Card */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Enrich Content
                                </CardTitle>
                                <CardDescription>
                                    Process image and video placeholders in your
                                    content
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    This will scan your content for
                                    [IMAGE_PLACEHOLDER] and [VIDEO_PLACEHOLDER]
                                    tags and replace them with generated images
                                    or embedded videos.
                                </p>
                                <Button
                                    className="w-full"
                                    variant="outline"
                                    onClick={handleEnrichContent}
                                    disabled={isEnriching}
                                >
                                    {isEnriching ? (
                                        <>
                                            <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                            Enriching...
                                        </>
                                    ) : (
                                        <>
                                            <Sparkles className="mr-2 h-4 w-4" />
                                            Enrich Content
                                        </>
                                    )}
                                </Button>
                            </CardContent>
                        </Card>

                        {/* SEO Recommendations Card */}
                        {(currentSeoScore !== null || currentSeoBreakdown) && (
                            <Card className="sticky top-4">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                SEO Recommendations
                                            </CardTitle>
                                            <CardDescription>
                                                Improve your score by addressing
                                                these items
                                            </CardDescription>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={handleRecalculateSeo}
                                            disabled={isRecalculating}
                                            title="Recalculate SEO score"
                                        >
                                            <RefreshCw
                                                className={`h-4 w-4 ${isRecalculating ? 'animate-spin' : ''}`}
                                            />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <SeoScore
                                        score={currentSeoScore}
                                        breakdown={currentSeoBreakdown}
                                        keyword={article.keyword?.keyword}
                                        onImprove={handleImprove}
                                        isImproving={isImproving}
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
