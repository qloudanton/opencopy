import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Clock, DollarSign, FileText } from 'lucide-react';

// Mini SEO score component for table display
function SeoScoreBadge({ score }: { score: number | null }) {
    if (score === null) {
        return <span className="text-sm text-muted-foreground">-</span>;
    }

    const getScoreColor = (s: number) => {
        if (s >= 80)
            return {
                bg: 'bg-green-500',
                text: 'text-green-700',
                label: 'Excellent',
            };
        if (s >= 60)
            return { bg: 'bg-lime-500', text: 'text-lime-700', label: 'Good' };
        if (s >= 40)
            return {
                bg: 'bg-orange-500',
                text: 'text-orange-700',
                label: 'Needs Work',
            };
        return { bg: 'bg-red-500', text: 'text-red-700', label: 'Poor' };
    };

    const { bg, text } = getScoreColor(score);
    const percentage = Math.min(100, Math.max(0, score));

    return (
        <div className="flex items-center gap-2">
            <div className="relative h-2 w-16 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn('h-full rounded-full transition-all', bg)}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            <span className={cn('text-sm font-medium tabular-nums', text)}>
                {score}
            </span>
        </div>
    );
}

interface Keyword {
    id: number;
    keyword: string;
}

interface ScheduledContent {
    id: number;
    status: string;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    word_count: number;
    reading_time_minutes: number;
    seo_score: number | null;
    usage_logs_sum_estimated_cost: number | null;
    keyword: Keyword | null;
    scheduled_content: ScheduledContent | null;
    created_at: string;
}

interface Project {
    id: number;
    name: string;
}

interface PaginatedArticles {
    data: Article[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    project: Project;
    articles: PaginatedArticles;
}

function getStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'published':
            return 'default';
        case 'in_review':
        case 'approved':
            return 'secondary';
        case 'generating':
        case 'queued':
        case 'enriching':
        case 'publishing_queued':
            return 'outline';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
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

export default function Index({ project, articles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Articles', href: `/projects/${project.id}/articles` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Articles - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Articles</h1>
                    <Button asChild variant="outline">
                        <Link href={`/projects/${project.id}/keywords`}>
                            Manage Keywords
                        </Link>
                    </Button>
                </div>

                {articles.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="mb-4 text-muted-foreground">
                                No articles yet
                            </p>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Generate articles from your keywords
                            </p>
                            <Button asChild>
                                <Link href={`/projects/${project.id}/keywords`}>
                                    Go to Keywords
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="rounded-md border">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="p-3 text-left text-sm font-medium">
                                            Title
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Keyword
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Status
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            SEO Score
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Words
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Read Time
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Cost
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Created
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {articles.data.map((article) => (
                                        <tr
                                            key={article.id}
                                            className="border-b last:border-0 hover:bg-muted/30"
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={`/projects/${project.id}/articles/${article.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {article.title}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {article.keyword ? (
                                                    <Link
                                                        href={`/projects/${project.id}/keywords/${article.keyword.id}`}
                                                        className="hover:underline"
                                                    >
                                                        {
                                                            article.keyword
                                                                .keyword
                                                        }
                                                    </Link>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-3">
                                                {article.scheduled_content ? (
                                                    <Badge
                                                        variant={getStatusVariant(
                                                            article
                                                                .scheduled_content
                                                                .status,
                                                        )}
                                                    >
                                                        {formatStatus(
                                                            article
                                                                .scheduled_content
                                                                .status,
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-3">
                                                <SeoScoreBadge
                                                    score={article.seo_score}
                                                />
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <FileText className="h-3 w-3" />
                                                    {article.word_count?.toLocaleString() ||
                                                        0}
                                                </span>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {article.reading_time_minutes ||
                                                        1}{' '}
                                                    min
                                                </span>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {article.usage_logs_sum_estimated_cost ? (
                                                    <span className="flex items-center gap-1">
                                                        <DollarSign className="h-3 w-3" />
                                                        {Number(
                                                            article.usage_logs_sum_estimated_cost,
                                                        ).toFixed(2)}
                                                    </span>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {new Date(
                                                    article.created_at,
                                                ).toLocaleString(undefined, {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {articles.last_page > 1 && (
                            <div className="flex justify-center gap-2">
                                {articles.prev_page_url && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={articles.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                )}
                                <span className="flex items-center px-3 text-sm text-muted-foreground">
                                    Page {articles.current_page} of{' '}
                                    {articles.last_page}
                                </span>
                                {articles.next_page_url && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={articles.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
