import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Clock, FileText, Pencil, Trash2 } from 'lucide-react';
import Markdown from '@/components/markdown';

interface Article {
    id: number;
    title: string;
    slug: string;
    meta_description: string | null;
    content: string;
    content_markdown: string;
    word_count: number;
    reading_time_minutes: number;
    status: string;
    seo_score: number | null;
    generation_metadata: {
        provider?: string;
        model?: string;
        keyword?: string;
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

interface Props {
    project: Project;
    article: Article;
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

export default function Show({ project, article }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        ...(article.keyword ? [
            { title: 'Keywords', href: `/projects/${project.id}/keywords` },
            { title: article.keyword.keyword, href: `/projects/${project.id}/keywords/${article.keyword.id}` },
        ] : []),
        { title: article.title, href: `/projects/${project.id}/articles/${article.id}` },
    ];

    function handleDelete() {
        if (confirm('Are you sure you want to delete this article?')) {
            router.delete(`/projects/${project.id}/articles/${article.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={article.title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {article.keyword && (
                            <Button asChild variant="ghost" size="icon">
                                <Link href={`/projects/${project.id}/keywords/${article.keyword.id}`}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                        )}
                        <div>
                            <h1 className="text-2xl font-bold">{article.title}</h1>
                            {article.meta_description && (
                                <p className="text-muted-foreground text-sm mt-1">{article.meta_description}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={getStatusVariant(article.status)}>{article.status}</Badge>
                        <Button asChild variant="outline">
                            <Link href={`/projects/${project.id}/articles/${article.id}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button variant="destructive" size="icon" onClick={handleDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-4 w-4 text-muted-foreground" />
                                <span className="text-2xl font-bold">{article.word_count?.toLocaleString() || 0}</span>
                            </div>
                            <p className="text-sm text-muted-foreground">words</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4">
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <span className="text-2xl font-bold">{article.reading_time_minutes || 1}</span>
                            </div>
                            <p className="text-sm text-muted-foreground">min read</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4">
                            <span className="text-2xl font-bold">{article.seo_score ?? '-'}</span>
                            <p className="text-sm text-muted-foreground">SEO score</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4">
                            <span className="text-sm font-medium">
                                {article.generation_metadata?.model || 'Manual'}
                            </span>
                            <p className="text-sm text-muted-foreground">
                                {article.generation_metadata?.provider || 'Created'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="flex-1">
                    <CardHeader>
                        <CardTitle>Content</CardTitle>
                        <CardDescription>
                            Generated {article.generated_at
                                ? new Date(article.generated_at).toLocaleDateString()
                                : new Date(article.created_at).toLocaleDateString()}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Markdown content={article.content_markdown || article.content} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
