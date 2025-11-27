import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';

interface Article {
    id: number;
    title: string;
    slug: string;
    meta_description: string | null;
    content: string;
    content_markdown: string;
    status: string;
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
}

interface Props {
    project: Project;
    article: Article;
}

export default function Edit({ project, article }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        ...(article.keyword ? [
            { title: 'Keywords', href: `/projects/${project.id}/keywords` },
            { title: article.keyword.keyword, href: `/projects/${project.id}/keywords/${article.keyword.id}` },
        ] : []),
        { title: article.title, href: `/projects/${project.id}/articles/${article.id}` },
        { title: 'Edit', href: `/projects/${project.id}/articles/${article.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: article.title,
        meta_description: article.meta_description || '',
        content: article.content_markdown || article.content,
        status: article.status,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/articles/${article.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit - ${article.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Edit Article</h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Article Details</CardTitle>
                            <CardDescription>Update your article information</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(value) => setData('status', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="review">Review</SelectItem>
                                            <SelectItem value="published">Published</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="meta_description">Meta Description</Label>
                                <Input
                                    id="meta_description"
                                    value={data.meta_description}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                    placeholder="SEO meta description (150-160 characters)"
                                    maxLength={255}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {data.meta_description.length}/160 characters
                                </p>
                                <InputError message={errors.meta_description} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="content">Content (Markdown)</Label>
                                <Textarea
                                    id="content"
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    rows={20}
                                    className="font-mono text-sm"
                                />
                                <InputError message={errors.content} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/projects/${project.id}/articles/${article.id}`}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
