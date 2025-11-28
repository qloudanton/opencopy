import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
}

interface Keyword {
    id: number;
    project_id: number;
    project: Project;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
    status: string;
    priority: number;
    articles_count: number;
    created_at: string;
}

interface PaginatedKeywords {
    data: Keyword[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    keywords: PaginatedKeywords;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Keywords', href: '/keywords' },
];

function getStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'queued':
        case 'generating':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}

export default function IndexAll({ keywords }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All Keywords" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">All Keywords</h1>
                </div>

                {keywords.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="mb-4 text-muted-foreground">
                                No keywords yet
                            </p>
                            <Button asChild>
                                <Link href="/projects">Go to Projects</Link>
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
                                            Keyword
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Project
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Intent
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Status
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Articles
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Priority
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {keywords.data.map((keyword) => (
                                        <tr
                                            key={keyword.id}
                                            className="border-b last:border-0 hover:bg-muted/30"
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={`/projects/${keyword.project_id}/keywords/${keyword.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {keyword.keyword}
                                                </Link>
                                                {keyword.secondary_keywords &&
                                                    keyword.secondary_keywords
                                                        .length > 0 && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            +
                                                            {
                                                                keyword
                                                                    .secondary_keywords
                                                                    .length
                                                            }{' '}
                                                            secondary
                                                        </p>
                                                    )}
                                            </td>
                                            <td className="p-3">
                                                <Link
                                                    href={`/projects/${keyword.project_id}`}
                                                    className="text-sm text-muted-foreground hover:underline"
                                                >
                                                    {keyword.project.name}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground capitalize">
                                                {keyword.search_intent || '-'}
                                            </td>
                                            <td className="p-3">
                                                <Badge
                                                    variant={getStatusVariant(
                                                        keyword.status,
                                                    )}
                                                >
                                                    {keyword.status}
                                                </Badge>
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {keyword.articles_count}
                                            </td>
                                            <td className="p-3 text-sm text-muted-foreground">
                                                {keyword.priority}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {keywords.last_page > 1 && (
                            <div className="flex justify-center gap-2">
                                {keywords.prev_page_url && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={keywords.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                )}
                                <span className="flex items-center px-3 text-sm text-muted-foreground">
                                    Page {keywords.current_page} of{' '}
                                    {keywords.last_page}
                                </span>
                                {keywords.next_page_url && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={keywords.next_page_url}>
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
