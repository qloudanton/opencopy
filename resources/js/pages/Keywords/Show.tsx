import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { generate } from '@/actions/App/Http/Controllers/KeywordController';
import { Loader2, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface Article {
    id: number;
    title: string;
    slug: string;
    status: string;
    seo_score: number | null;
    created_at: string;
}

interface Keyword {
    id: number;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
    target_word_count: number | null;
    tone: string | null;
    additional_instructions: string | null;
    status: string;
    priority: number;
    error_message: string | null;
    articles_count: number;
    articles: Article[];
    created_at: string;
    updated_at: string;
}

interface Project {
    id: number;
    name: string;
}

interface Props {
    project: Project;
    keyword: Keyword;
}

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
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

export default function Show({ project, keyword }: Props) {
    const [isGenerating, setIsGenerating] = useState(false);
    const isProcessing = keyword.status === 'generating' || keyword.status === 'queued';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Keywords', href: `/projects/${project.id}/keywords` },
        { title: keyword.keyword, href: `/projects/${project.id}/keywords/${keyword.id}` },
    ];

    function handleGenerate() {
        setIsGenerating(true);
        router.post(generate.url({ project: project.id, keyword: keyword.id }), {}, {
            onFinish: () => setIsGenerating(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${keyword.keyword} - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold">{keyword.keyword}</h1>
                        <Badge variant={getStatusVariant(keyword.status)}>{keyword.status}</Badge>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/projects/${project.id}/keywords/${keyword.id}/edit`}>Edit</Link>
                        </Button>
                        <Button
                            onClick={handleGenerate}
                            disabled={isProcessing || isGenerating}
                        >
                            {isProcessing || isGenerating ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {keyword.status === 'queued' ? 'Queued' : 'Generating...'}
                                </>
                            ) : (
                                <>
                                    <Sparkles className="mr-2 h-4 w-4" />
                                    Generate Article
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {keyword.error_message && (
                    <Card className="border-destructive">
                        <CardContent className="pt-4">
                            <p className="text-destructive text-sm">{keyword.error_message}</p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Keyword Settings</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {keyword.secondary_keywords && keyword.secondary_keywords.length > 0 && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground mb-1">Secondary Keywords</p>
                                    <div className="flex flex-wrap gap-1">
                                        {keyword.secondary_keywords.map((kw, idx) => (
                                            <Badge key={idx} variant="outline">{kw}</Badge>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Search Intent</p>
                                    <p className="capitalize">{keyword.search_intent || 'Not set'}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Tone</p>
                                    <p className="capitalize">{keyword.tone || 'Not set'}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Target Word Count</p>
                                    <p>{keyword.target_word_count?.toLocaleString() || 'Not set'}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Priority</p>
                                    <p>{keyword.priority}</p>
                                </div>
                            </div>

                            {keyword.additional_instructions && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground mb-1">Additional Instructions</p>
                                    <p className="text-sm whitespace-pre-wrap">{keyword.additional_instructions}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Statistics</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Articles Generated</p>
                                    <p className="text-3xl font-bold">{keyword.articles_count}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Created</p>
                                    <p>{new Date(keyword.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Generated Articles</CardTitle>
                        <CardDescription>Articles generated from this keyword</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {keyword.articles.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No articles generated yet</p>
                        ) : (
                            <div className="rounded-md border">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="p-3 text-left text-sm font-medium">Title</th>
                                            <th className="p-3 text-left text-sm font-medium">Status</th>
                                            <th className="p-3 text-left text-sm font-medium">SEO Score</th>
                                            <th className="p-3 text-left text-sm font-medium">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {keyword.articles.map((article) => (
                                            <tr key={article.id} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="p-3">
                                                    <Link
                                                        href={`/articles/${article.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {article.title}
                                                    </Link>
                                                </td>
                                                <td className="p-3">
                                                    <Badge variant={getStatusVariant(article.status)}>
                                                        {article.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-3 text-sm text-muted-foreground">
                                                    {article.seo_score ?? '-'}
                                                </td>
                                                <td className="p-3 text-sm text-muted-foreground">
                                                    {new Date(article.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
