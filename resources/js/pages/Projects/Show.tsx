import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';


interface Keyword {
    id: number;
    keyword: string;
    status: string;
    created_at: string;
}

interface Article {
    id: number;
    title: string;
    status: string;
    created_at: string;
}

interface Project {
    id: number;
    name: string;
    domain: string | null;
    description: string | null;
    keywords_count: number;
    articles_count: number;
    integrations_count: number;
    is_active: boolean;
    keywords: Keyword[];
    articles: Article[];
    created_at: string;
}

interface Props {
    project: Project;
}

export default function Show({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{project.name}</h1>
                        {project.domain && (
                            <p className="text-muted-foreground">{project.domain}</p>
                        )}
                    </div>
                    <Button asChild variant="outline">
                        <Link href={`/projects/${project.id}/edit`}>Edit</Link>
                    </Button>
                </div>

                {project.description && (
                    <p className="text-muted-foreground">{project.description}</p>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Link href={`/projects/${project.id}/keywords`}>
                        <Card className="hover:border-primary transition-colors">
                            <CardHeader className="pb-2">
                                <CardDescription>Keywords</CardDescription>
                                <CardTitle className="text-3xl">{project.keywords_count}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                    <Link href={`/projects/${project.id}/articles`}>
                        <Card className="hover:border-primary transition-colors">
                            <CardHeader className="pb-2">
                                <CardDescription>Articles</CardDescription>
                                <CardTitle className="text-3xl">{project.articles_count}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Integrations</CardDescription>
                            <CardTitle className="text-3xl">{project.integrations_count}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Keywords</CardTitle>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={`/projects/${project.id}/keywords`}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {project.keywords.length === 0 ? (
                                <div className="text-center py-4">
                                    <p className="text-muted-foreground text-sm mb-2">No keywords yet</p>
                                    <Button asChild size="sm">
                                        <Link href={`/projects/${project.id}/keywords/create`}>Add keyword</Link>
                                    </Button>
                                </div>
                            ) : (
                                <ul className="space-y-2">
                                    {project.keywords.map((keyword) => (
                                        <li key={keyword.id} className="flex justify-between text-sm">
                                            <Link
                                                href={`/projects/${project.id}/keywords/${keyword.id}`}
                                                className="hover:underline"
                                            >
                                                {keyword.keyword}
                                            </Link>
                                            <span className="text-muted-foreground">{keyword.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Articles</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {project.articles.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No articles yet</p>
                            ) : (
                                <ul className="space-y-2">
                                    {project.articles.map((article) => (
                                        <li key={article.id} className="flex justify-between text-sm">
                                            <Link
                                                href={`/articles/${article.id}`}
                                                className="truncate hover:underline"
                                            >
                                                {article.title}
                                            </Link>
                                            <span className="text-muted-foreground ml-2 shrink-0">{article.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
