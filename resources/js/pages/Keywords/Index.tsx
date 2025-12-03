import {
    analyze,
    generate,
} from '@/actions/App/Http/Controllers/KeywordController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { axios } from '@/lib/axios';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, Loader2, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface ScheduledContent {
    id: number;
    keyword_id: number;
    status: string;
    error_message: string | null;
}

interface Keyword {
    id: number;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
    difficulty: string | null;
    volume: string | null;
    articles_count: number;
    created_at: string;
    scheduled_content: ScheduledContent | null;
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

const difficultyConfig: Record<string, { level: number; color: string }> = {
    low: { level: 1, color: 'bg-green-500' },
    medium: { level: 2, color: 'bg-yellow-500' },
    high: { level: 3, color: 'bg-red-500' },
};

const volumeConfig: Record<string, { level: number; color: string }> = {
    low: { level: 1, color: 'bg-slate-400' },
    medium: { level: 2, color: 'bg-slate-500' },
    high: { level: 3, color: 'bg-slate-600' },
};

function MiniProgressBar({
    level,
    maxLevel = 3,
    color,
}: {
    level: number;
    maxLevel?: number;
    color: string;
}) {
    return (
        <div className="flex gap-0.5">
            {Array.from({ length: maxLevel }).map((_, i) => (
                <div
                    key={i}
                    className={`h-2 w-2 rounded-sm ${i < level ? color : 'bg-muted'}`}
                />
            ))}
        </div>
    );
}

export default function Index({ project, keywords }: Props) {
    const [generatingId, setGeneratingId] = useState<number | null>(null);
    const [analyzing, setAnalyzing] = useState(false);

    const keywordsNeedingAnalysis = keywords.data.filter(
        (k) => !k.difficulty || !k.volume,
    );

    async function handleAnalyze() {
        if (keywordsNeedingAnalysis.length === 0) return;

        setAnalyzing(true);
        try {
            await axios.post(analyze.url({ project: project.id }), {
                keyword_ids: keywordsNeedingAnalysis.map((k) => k.id),
            });
            router.reload({ only: ['keywords'] });
        } catch (error) {
            console.error('Error analyzing keywords:', error);
        } finally {
            setAnalyzing(false);
        }
    }

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
                    <div className="flex items-center gap-2">
                        {keywordsNeedingAnalysis.length > 0 && (
                            <Button
                                variant="outline"
                                onClick={handleAnalyze}
                                disabled={analyzing}
                            >
                                {analyzing ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <BarChart3 className="mr-2 h-4 w-4" />
                                )}
                                Analyze ({keywordsNeedingAnalysis.length})
                            </Button>
                        )}
                        <Button asChild>
                            <Link
                                href={`/projects/${project.id}/keywords/create`}
                            >
                                Add Keyword
                            </Link>
                        </Button>
                    </div>
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
                                            Difficulty
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Volume
                                        </th>
                                        <th className="p-3 text-left text-sm font-medium">
                                            Articles
                                        </th>
                                        <th className="p-3 text-right text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {keywords.data.map((keyword) => {
                                        const isProcessing =
                                            keyword.scheduled_content?.status ===
                                                'generating' ||
                                            keyword.scheduled_content?.status ===
                                                'queued';
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
                                                <td className="p-3">
                                                    {keyword.difficulty ? (
                                                        <MiniProgressBar
                                                            level={
                                                                difficultyConfig[
                                                                    keyword.difficulty.toLowerCase()
                                                                ]?.level || 1
                                                            }
                                                            color={
                                                                difficultyConfig[
                                                                    keyword.difficulty.toLowerCase()
                                                                ]?.color ||
                                                                'bg-slate-400'
                                                            }
                                                        />
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            -
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    {keyword.volume ? (
                                                        <MiniProgressBar
                                                            level={
                                                                volumeConfig[
                                                                    keyword.volume.toLowerCase()
                                                                ]?.level || 1
                                                            }
                                                            color={
                                                                volumeConfig[
                                                                    keyword.volume.toLowerCase()
                                                                ]?.color ||
                                                                'bg-slate-400'
                                                            }
                                                        />
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            -
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-sm text-muted-foreground">
                                                    {keyword.articles_count}
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
