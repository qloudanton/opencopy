import { generate } from '@/actions/App/Http/Controllers/KeywordController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface Keyword {
    id: number;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
    status: string;
    priority: number;
    articles_count: number;
    created_at: string;
}

interface Project {
    id: number;
    name: string;
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
    project: Project;
    keywords: PaginatedKeywords;
}

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

export default function Index({ project, keywords }: Props) {
    const [generatingId, setGeneratingId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Keywords', href: `/projects/${project.id}/keywords` },
    ];

    function handleGenerate(keyword: Keyword) {
        setGeneratingId(keyword.id);
        router.post(
            generate.url({ project: project.id, keyword: keyword.id }),
            {},
            {
                onFinish: () => setGeneratingId(null),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Keywords - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Keywords</h1>
                    <Button asChild>
                        <Link href={`/projects/${project.id}/keywords/create`}>
                            Add Keyword
                        </Link>
                    </Button>
                </div>

                {keywords.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="mb-4 text-muted-foreground">
                                No keywords yet
                            </p>
                            <Button asChild>
                                <Link
                                    href={`/projects/${project.id}/keywords/create`}
                                >
                                    Add your first keyword
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
                                            Keyword
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
                                        <th className="p-3 text-right text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {keywords.data.map((keyword) => {
                                        const isProcessing =
                                            keyword.status === 'generating' ||
                                            keyword.status === 'queued';
                                        const isThisGenerating =
                                            generatingId === keyword.id;
                                        return (
                                            <tr
                                                key={keyword.id}
                                                className="border-b last:border-0 hover:bg-muted/30"
                                            >
                                                <td className="p-3">
                                                    <Link
                                                        href={`/projects/${project.id}/keywords/${keyword.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {keyword.keyword}
                                                    </Link>
                                                    {keyword.secondary_keywords &&
                                                        keyword
                                                            .secondary_keywords
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
                                                <td className="p-3 text-sm text-muted-foreground capitalize">
                                                    {keyword.search_intent ||
                                                        '-'}
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
                                                <td className="p-3 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            handleGenerate(
                                                                keyword,
                                                            )
                                                        }
                                                        disabled={
                                                            isProcessing ||
                                                            isThisGenerating
                                                        }
                                                    >
                                                        {isProcessing ||
                                                        isThisGenerating ? (
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Sparkles className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
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
