import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Clock, FileText } from 'lucide-react';

interface Keyword {
    id: number;
    keyword: string;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    status: string;
    word_count: number;
    reading_time_minutes: number;
    seo_score: number | null;
    keyword: Keyword | null;
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

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'published':
            return 'default';
        case 'review':
            return 'secondary';
        case 'draft':
            return 'outline';
        default:
            return 'outline';
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
                        <Link href={`/projects/${project.id}/keywords`}>Manage Keywords</Link>
                    </Button>
                </div>

                {articles.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-muted-foreground mb-4">No articles yet</p>
                            <p className="text-muted-foreground text-sm mb-4">
                                Generate articles from your keywords
                            </p>
                            <Button asChild>
                                <Link href={`/projects/${project.id}/keywords`}>Go to Keywords</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="rounded-md border">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="p-3 text-left text-sm font-medium">Title</th>
                                        <th className="p-3 text-left text-sm font-medium">Keyword</th>
                                        <th className="p-3 text-left text-sm font-medium">Status</th>
                                        <th className="p-3 text-left text-sm font-medium">Words</th>
                                        <th className="p-3 text-left text-sm font-medium">Read Time</th>
                                        <th className="p-3 text-left text-sm font-medium">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {articles.data.map((article) => (
                                        <tr key={article.id} className="border-b last:border-0 hover:bg-muted/30">
                                            <td className="p-3">
                                                <Link
                                                    href={`/articles/${article.id}`}
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
                                                        {article.keyword.keyword}
                                                    </Link>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={getStatusVariant(article.status)}>
                                                    {article.status}
                                                </Badge>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <FileText className="h-3 w-3" />
                                                    {article.word_count?.toLocaleString() || 0}
                                                </span>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {article.reading_time_minutes || 1} min
                                                </span>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {new Date(article.created_at).toLocaleDateString()}
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
                                        <Link href={articles.prev_page_url}>Previous</Link>
                                    </Button>
                                )}
                                <span className="flex items-center px-3 text-sm text-muted-foreground">
                                    Page {articles.current_page} of {articles.last_page}
                                </span>
                                {articles.next_page_url && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={articles.next_page_url}>Next</Link>
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
