import Markdown from '@/components/markdown';
import { SeoScore, SeoScoreMini } from '@/components/seo-score';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertCircle,
    ArrowLeft,
    CalendarClock,
    Check,
    CheckCircle,
    Clipboard,
    Clock,
    Download,
    ExternalLink,
    FileText,
    Globe,
    Image,
    Loader2,
    Pencil,
    RefreshCw,
    Send,
    Trash2,
    Webhook,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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
    word_count: number;
    reading_time_minutes: number;
    seo_score: number | null;
    generation_metadata: {
        provider?: string;
        model?: string;
        keyword?: string;
        seo_breakdown?: SeoBreakdown;
    } | null;
    generated_at: string | null;
    created_at: string;
    updated_at: string;
    project: {
        id: number;
        name: string;
    };
    keyword: {
        id: number;
        keyword: string;
    } | null;
    ai_provider: {
        id: number;
        name: string;
    } | null;
}

interface Project {
    id: number;
    name: string;
}

interface FeaturedImage {
    id: number;
    url: string;
    width: number;
    height: number;
}

interface CostBreakdown {
    text_generation: number;
    image_generation: number;
    improvement: number;
    total: number;
    details: Array<{
        operation: string;
        model: string;
        cost: number;
        tokens: number | null;
        images: number | null;
        created_at: string;
    }>;
}

interface Integration {
    id: number;
    type: string;
    name: string;
    has_credentials: boolean;
}

interface Publication {
    id: number;
    integration_id: number;
    integration_name: string | null;
    integration_type: string | null;
    status: 'pending' | 'publishing' | 'published' | 'failed';
    external_url: string | null;
    error_message: string | null;
    published_at: string | null;
    created_at: string;
}

interface ScheduledContent {
    id: number;
    status: string;
    scheduled_date: string | null;
    scheduled_time: string | null;
}

interface Props {
    project: Project;
    article: Article;
    featuredImage: FeaturedImage | null;
    costBreakdown: CostBreakdown;
    integrations: Integration[];
    publications: Publication[];
    scheduledContent: ScheduledContent | null;
}

function getStatusColor(status: string): string {
    switch (status) {
        case 'published':
            return 'bg-green-500';
        case 'in_review':
        case 'approved':
            return 'bg-yellow-500';
        case 'generating':
        case 'queued':
        case 'publishing_queued':
            return 'bg-blue-500';
        case 'enriching':
            return 'bg-purple-500';
        case 'failed':
            return 'bg-red-500';
        default:
            return 'bg-gray-400';
    }
}

function formatStatus(status: string): string {
    switch (status) {
        case 'in_review':
            return 'In Review';
        case 'publishing_queued':
            return 'Publishing';
        case 'enriching':
            return 'Enriching';
        default:
            return status.charAt(0).toUpperCase() + status.slice(1);
    }
}

function formatCost(cost: number): string {
    if (cost === 0) return '$0.00';
    if (cost < 0.01) return `$${cost.toFixed(4)}`;
    return `$${cost.toFixed(2)}`;
}

function getIntegrationIcon(type: string) {
    switch (type) {
        case 'webhook':
            return Webhook;
        case 'wordpress':
        case 'webflow':
        case 'shopify':
        case 'wix':
            return Globe;
        default:
            return Globe;
    }
}

export default function Show({
    project,
    article,
    featuredImage,
    costBreakdown,
    integrations,
    publications: initialPublications,
    scheduledContent,
}: Props) {
    const [copied, setCopied] = useState(false);
    const [publications, setPublications] =
        useState<Publication[]>(initialPublications);
    const [selectedIntegrations, setSelectedIntegrations] = useState<number[]>(
        [],
    );
    const [isPublishing, setIsPublishing] = useState(false);
    const [retryingId, setRetryingId] = useState<number | null>(null);
    const { csrf_token } = usePage<{ csrf_token: string }>().props;
    const content = article.content_markdown || article.content;
    const seoBreakdown = article.generation_metadata?.seo_breakdown;

    // Check if any publication is pending/publishing
    const hasPendingPublications = publications.some(
        (p) => p.status === 'pending' || p.status === 'publishing',
    );

    // Poll for status updates when there are pending publications
    const pollForStatus = useCallback(async () => {
        try {
            const response = await axios.get(
                `/projects/${project.id}/articles/${article.id}/publication-status`,
                { headers: { Accept: 'application/json' } },
            );
            setPublications(response.data.publications);
        } catch {
            // Silently fail polling
        }
    }, [project.id, article.id]);

    useEffect(() => {
        if (!hasPendingPublications) return;

        const interval = setInterval(pollForStatus, 2000);
        return () => clearInterval(interval);
    }, [hasPendingPublications, pollForStatus]);

    async function handlePublish() {
        if (selectedIntegrations.length === 0) {
            toast.error('Please select at least one integration');
            return;
        }

        setIsPublishing(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/publish`,
                { integration_ids: selectedIntegrations },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );
            toast.success(response.data.message);
            setSelectedIntegrations([]);
            // Immediately poll for new status
            await pollForStatus();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to publish article');
            }
        } finally {
            setIsPublishing(false);
        }
    }

    async function handleRetry(publicationId: number) {
        setRetryingId(publicationId);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/retry-publication`,
                { publication_id: publicationId },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );
            toast.success(response.data.message);
            await pollForStatus();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to retry publication');
            }
        } finally {
            setRetryingId(null);
        }
    }

    function toggleIntegration(integrationId: number) {
        setSelectedIntegrations((prev) =>
            prev.includes(integrationId)
                ? prev.filter((id) => id !== integrationId)
                : [...prev, integrationId],
        );
    }

    function getPublicationForIntegration(
        integrationId: number,
    ): Publication | undefined {
        return publications.find((p) => p.integration_id === integrationId);
    }

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
    ];

    function handleDelete() {
        if (confirm('Are you sure you want to delete this article?')) {
            router.delete(`/projects/${project.id}/articles/${article.id}`);
        }
    }

    function handleCopyToClipboard() {
        navigator.clipboard.writeText(content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function handleDownload() {
        const blob = new Blob([content], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${article.slug}.md`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={article.title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {article.keyword && (
                            <Button asChild variant="ghost" size="icon">
                                <Link
                                    href={`/projects/${project.id}/keywords/${article.keyword.id}`}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                        )}
                        <h1 className="text-2xl font-bold">{article.title}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleCopyToClipboard}
                        >
                            {copied ? (
                                <Check className="h-4 w-4" />
                            ) : (
                                <Clipboard className="h-4 w-4" />
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDownload}
                        >
                            <Download className="h-4 w-4" />
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={`/projects/${project.id}/articles/${article.id}/edit`}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            size="icon"
                            onClick={handleDelete}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {/* Main content grid */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Left column - Content */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Stats cards */}
                        <div className="grid gap-4 md:grid-cols-4">
                            <Card>
                                <CardContent className="pt-4">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-2xl font-bold">
                                            {article.word_count?.toLocaleString() ||
                                                0}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        words
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <div className="flex items-center gap-2">
                                        <Clock className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-2xl font-bold">
                                            {article.reading_time_minutes || 1}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        min read
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <SeoScoreMini score={article.seo_score} />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <span className="text-sm font-medium">
                                        {article.generation_metadata?.model ||
                                            'Manual'}
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        {article.generation_metadata
                                            ?.provider || 'Created'}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* SEO Analysis */}
                        {(article.seo_score !== null || seoBreakdown) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>SEO Analysis</CardTitle>
                                    <CardDescription>
                                        How well your content is optimized for
                                        search engines
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <SeoScore
                                        score={article.seo_score}
                                        breakdown={seoBreakdown}
                                        keyword={article.keyword?.keyword}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {/* Article Content */}
                        <Card className="flex-1">
                            <CardHeader>
                                <CardTitle>Content</CardTitle>
                                <CardDescription>
                                    Generated{' '}
                                    {article.generated_at
                                        ? new Date(
                                              article.generated_at,
                                          ).toLocaleDateString()
                                        : new Date(
                                              article.created_at,
                                          ).toLocaleDateString()}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Markdown content={content} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right column - Sidebar */}
                    <div className="space-y-4 lg:col-span-1">
                        {/* Featured Image */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Featured Image
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
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
                                    <Link
                                        href={`/projects/${project.id}/articles/${article.id}/edit`}
                                        className="block"
                                    >
                                        <div
                                            className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed bg-muted/30 p-6 transition-colors hover:border-primary hover:bg-muted/50"
                                            style={{ aspectRatio: '1312/736' }}
                                        >
                                            <Image className="mb-2 h-8 w-8 text-muted-foreground" />
                                            <p className="text-sm text-muted-foreground">
                                                No featured image
                                            </p>
                                            <p className="mt-1 text-xs text-primary">
                                                Click to generate
                                            </p>
                                        </div>
                                    </Link>
                                )}
                            </CardContent>
                        </Card>

                        {/* Target Keyword */}
                        {article.keyword && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        Target Keyword
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Link
                                        href={`/projects/${project.id}/keywords/${article.keyword.id}`}
                                        className="text-sm font-medium text-primary hover:underline"
                                    >
                                        {article.keyword.keyword}
                                    </Link>
                                </CardContent>
                            </Card>
                        )}

                        {/* Status */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {scheduledContent ? (
                                    <div className="flex items-center gap-2">
                                        <div
                                            className={`h-2 w-2 rounded-full ${getStatusColor(scheduledContent.status)}`}
                                        />
                                        <span className="text-sm font-medium">
                                            {formatStatus(
                                                scheduledContent.status,
                                            )}
                                        </span>
                                    </div>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        -
                                    </span>
                                )}
                            </CardContent>
                        </Card>

                        {/* Slug */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Slug
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Input
                                    value={article.slug}
                                    readOnly
                                    className="h-8 bg-muted text-sm"
                                />
                            </CardContent>
                        </Card>

                        {/* Meta Description */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        Meta Description
                                    </CardTitle>
                                    <span className="text-xs text-muted-foreground">
                                        {article.meta_description?.length || 0}
                                        /160
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {article.meta_description ? (
                                    <p className="text-sm text-muted-foreground">
                                        {article.meta_description}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground italic">
                                        No meta description
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* AI Provider */}
                        {article.ai_provider && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        AI Provider
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">
                                        {article.ai_provider.name}
                                    </p>
                                    {article.generation_metadata?.model && (
                                        <p className="text-xs text-muted-foreground">
                                            Model:{' '}
                                            {article.generation_metadata.model}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Generation Cost */}
                        {costBreakdown.total > 0 && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base">
                                        Generation Cost
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Text generation
                                            </span>
                                            <span className="font-medium">
                                                {formatCost(
                                                    costBreakdown.text_generation,
                                                )}
                                            </span>
                                        </div>
                                        {costBreakdown.image_generation > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Images
                                                </span>
                                                <span className="font-medium">
                                                    {formatCost(
                                                        costBreakdown.image_generation,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                        {costBreakdown.improvement > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Improvements
                                                </span>
                                                <span className="font-medium">
                                                    {formatCost(
                                                        costBreakdown.improvement,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                        <div className="border-t pt-2">
                                            <div className="flex justify-between text-sm font-semibold">
                                                <span>Total</span>
                                                <span className="text-primary">
                                                    {formatCost(
                                                        costBreakdown.total,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Publishing */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        Publishing
                                    </CardTitle>
                                    {hasPendingPublications && (
                                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Schedule/Publishing Status Banner */}
                                {scheduledContent?.status === 'published' ? (
                                    (() => {
                                        // Find successful publications
                                        const successfulPubs = publications.filter(
                                            (p) => p.status === 'published',
                                        );
                                        // Get the most recent published_at date
                                        const mostRecentPub = successfulPubs
                                            .filter((p) => p.published_at)
                                            .sort(
                                                (a, b) =>
                                                    new Date(
                                                        b.published_at!,
                                                    ).getTime() -
                                                    new Date(
                                                        a.published_at!,
                                                    ).getTime(),
                                            )[0];

                                        return (
                                            <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950/50">
                                                <CheckCircle className="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-medium text-green-900 dark:text-green-100">
                                                        Published
                                                    </p>
                                                    {mostRecentPub?.published_at && (
                                                        <p className="text-sm text-green-700 dark:text-green-300">
                                                            {new Date(
                                                                mostRecentPub.published_at,
                                                            ).toLocaleDateString(
                                                                'en-US',
                                                                {
                                                                    weekday:
                                                                        'short',
                                                                    month: 'short',
                                                                    day: 'numeric',
                                                                    year: 'numeric',
                                                                },
                                                            )}{' '}
                                                            at{' '}
                                                            {new Date(
                                                                mostRecentPub.published_at,
                                                            ).toLocaleTimeString(
                                                                'en-US',
                                                                {
                                                                    hour: 'numeric',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </p>
                                                    )}
                                                    {successfulPubs.length >
                                                        0 && (
                                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                                            {successfulPubs.map(
                                                                (pub) => (
                                                                    <span
                                                                        key={
                                                                            pub.id
                                                                        }
                                                                        className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/50 dark:text-green-200"
                                                                    >
                                                                        {pub.integration_name ||
                                                                            pub.integration_type ||
                                                                            'Integration'}
                                                                    </span>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })()
                                ) : scheduledContent?.status ===
                                  'enriching' ? (
                                    <div className="flex items-start gap-3 rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-900 dark:bg-purple-950/50">
                                        <Loader2 className="mt-0.5 h-5 w-5 shrink-0 animate-spin text-purple-600" />
                                        <div>
                                            <p className="font-medium text-purple-900 dark:text-purple-100">
                                                Enriching...
                                            </p>
                                            <p className="text-sm text-purple-700 dark:text-purple-300">
                                                Adding images, videos, and other
                                                enhancements.
                                            </p>
                                        </div>
                                    </div>
                                ) : scheduledContent?.status ===
                                  'publishing_queued' ? (
                                    <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/50">
                                        <Loader2 className="mt-0.5 h-5 w-5 shrink-0 animate-spin text-blue-600" />
                                        <div>
                                            <p className="font-medium text-blue-900 dark:text-blue-100">
                                                Publishing...
                                            </p>
                                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                                This article is being published
                                                to your integrations.
                                            </p>
                                        </div>
                                    </div>
                                ) : scheduledContent?.scheduled_date ? (
                                    <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/50">
                                        <CalendarClock className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
                                        <div>
                                            <p className="font-medium text-blue-900 dark:text-blue-100">
                                                Scheduled
                                            </p>
                                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                                {new Date(
                                                    scheduledContent.scheduled_date,
                                                ).toLocaleDateString('en-US', {
                                                    weekday: 'short',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                                {scheduledContent.scheduled_time &&
                                                    ` at ${scheduledContent.scheduled_time}`}
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-lg border border-muted bg-muted/30 p-3">
                                        <Clock className="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                                        <div>
                                            <p className="font-medium">
                                                Not Scheduled
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Publish manually or schedule via
                                                the content planner.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* Integrations */}
                                {integrations.length === 0 ? (
                                    <div className="text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No integrations configured
                                        </p>
                                        <Button
                                            asChild
                                            variant="link"
                                            size="sm"
                                            className="mt-1"
                                        >
                                            <Link
                                                href={`/projects/${project.id}/integrations`}
                                            >
                                                Add integration
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {/* Integration list with status */}
                                        <div className="space-y-2">
                                            {integrations.map((integration) => {
                                                const Icon = getIntegrationIcon(
                                                    integration.type,
                                                );
                                                const publication =
                                                    getPublicationForIntegration(
                                                        integration.id,
                                                    );
                                                const isSelected =
                                                    selectedIntegrations.includes(
                                                        integration.id,
                                                    );

                                                return (
                                                    <div
                                                        key={integration.id}
                                                        className="flex items-center justify-between rounded-lg border p-2"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            {!publication && (
                                                                <Checkbox
                                                                    id={`int-${integration.id}`}
                                                                    checked={
                                                                        isSelected
                                                                    }
                                                                    onCheckedChange={() =>
                                                                        toggleIntegration(
                                                                            integration.id,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        !integration.has_credentials
                                                                    }
                                                                />
                                                            )}
                                                            <Icon className="h-4 w-4 text-muted-foreground" />
                                                            <label
                                                                htmlFor={`int-${integration.id}`}
                                                                className="text-sm font-medium"
                                                            >
                                                                {
                                                                    integration.name
                                                                }
                                                            </label>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            {publication && (
                                                                <PublicationStatus
                                                                    publication={
                                                                        publication
                                                                    }
                                                                    onRetry={() =>
                                                                        handleRetry(
                                                                            publication.id,
                                                                        )
                                                                    }
                                                                    isRetrying={
                                                                        retryingId ===
                                                                        publication.id
                                                                    }
                                                                />
                                                            )}
                                                            {!integration.has_credentials && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-xs"
                                                                >
                                                                    No
                                                                    credentials
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* Publish button */}
                                        {selectedIntegrations.length > 0 && (
                                            <Button
                                                className="w-full"
                                                onClick={handlePublish}
                                                disabled={isPublishing}
                                            >
                                                {isPublishing ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Send className="mr-2 h-4 w-4" />
                                                )}
                                                Publish to{' '}
                                                {selectedIntegrations.length}{' '}
                                                integration
                                                {selectedIntegrations.length >
                                                    1 && 's'}
                                            </Button>
                                        )}

                                        {/* Link to manage integrations */}
                                        <div className="text-center">
                                            <Button
                                                asChild
                                                variant="link"
                                                size="sm"
                                            >
                                                <Link
                                                    href={`/projects/${project.id}/integrations`}
                                                >
                                                    Manage integrations
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

// Publication status component
function PublicationStatus({
    publication,
    onRetry,
    isRetrying,
}: {
    publication: Publication;
    onRetry: () => void;
    isRetrying: boolean;
}) {
    switch (publication.status) {
        case 'published':
            return (
                <div className="flex items-center gap-1">
                    {publication.external_url ? (
                        <a
                            href={publication.external_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-1 text-xs text-green-600 hover:underline dark:text-green-400"
                        >
                            <CheckCircle className="h-3 w-3" />
                            Published
                            <ExternalLink className="h-3 w-3" />
                        </a>
                    ) : (
                        <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                            <CheckCircle className="h-3 w-3" />
                            Published
                        </span>
                    )}
                </div>
            );
        case 'pending':
        case 'publishing':
            return (
                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Loader2 className="h-3 w-3 animate-spin" />
                    {publication.status === 'pending'
                        ? 'Queued'
                        : 'Publishing...'}
                </span>
            );
        case 'failed':
            return (
                <div className="flex items-center gap-2">
                    <span
                        className="flex items-center gap-1 text-xs text-red-600 dark:text-red-400"
                        title={publication.error_message || 'Failed'}
                    >
                        <AlertCircle className="h-3 w-3" />
                        Failed
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 px-2"
                        onClick={onRetry}
                        disabled={isRetrying}
                    >
                        {isRetrying ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                            <RefreshCw className="h-3 w-3" />
                        )}
                    </Button>
                </div>
            );
        default:
            return null;
    }
}
