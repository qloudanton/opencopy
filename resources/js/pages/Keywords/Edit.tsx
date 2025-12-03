import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

interface Keyword {
    id: number;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
}

interface Project {
    id: number;
    name: string;
}

interface Props {
    project: Project;
    keyword: Keyword;
}

export default function Edit({ project, keyword }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Keywords', href: `/projects/${project.id}/keywords` },
        {
            title: keyword.keyword,
            href: `/projects/${project.id}/keywords/${keyword.id}`,
        },
        {
            title: 'Edit',
            href: `/projects/${project.id}/keywords/${keyword.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        keyword: keyword.keyword,
        secondary_keywords: keyword.secondary_keywords?.join(', ') || '',
        search_intent: keyword.search_intent || '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const formData = {
            ...data,
            secondary_keywords: data.secondary_keywords
                ? data.secondary_keywords
                      .split(',')
                      .map((k) => k.trim())
                      .filter((k) => k)
                : [],
            search_intent: data.search_intent || null,
        };
        put(`/projects/${project.id}/keywords/${keyword.id}`, {
            data: formData,
        });
    }

    function handleDelete() {
        if (
            confirm(
                'Are you sure you want to delete this keyword? This will also delete all generated articles.',
            )
        ) {
            router.delete(`/projects/${project.id}/keywords/${keyword.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${keyword.keyword} - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-bold">Edit Keyword</h1>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Keyword Details</CardTitle>
                        <CardDescription>
                            Update keyword settings for content generation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="keyword">Primary Keyword</Label>
                                <Input
                                    id="keyword"
                                    value={data.keyword}
                                    onChange={(e) =>
                                        setData('keyword', e.target.value)
                                    }
                                    placeholder="best coffee machines 2024"
                                />
                                <InputError message={errors.keyword} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="secondary_keywords">
                                    Secondary Keywords (optional)
                                </Label>
                                <Textarea
                                    id="secondary_keywords"
                                    value={data.secondary_keywords}
                                    onChange={(e) =>
                                        setData(
                                            'secondary_keywords',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="espresso machine, drip coffee maker, french press"
                                    rows={2}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Separate keywords with commas
                                </p>
                                <InputError
                                    message={errors.secondary_keywords}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="search_intent">
                                    Search Intent (optional)
                                </Label>
                                <Select
                                    value={data.search_intent}
                                    onValueChange={(value) =>
                                        setData('search_intent', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Auto-detect" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="informational">
                                            Informational - How-to guides,
                                            explanations
                                        </SelectItem>
                                        <SelectItem value="commercial">
                                            Commercial - Comparisons, reviews
                                        </SelectItem>
                                        <SelectItem value="transactional">
                                            Transactional - Buy, download, sign
                                            up
                                        </SelectItem>
                                        <SelectItem value="navigational">
                                            Navigational - Brand/product
                                            specific
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Leave empty to let AI detect the intent
                                </p>
                                <InputError message={errors.search_intent} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Update Keyword
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="max-w-2xl border-destructive">
                    <CardHeader>
                        <CardTitle className="text-destructive">
                            Danger Zone
                        </CardTitle>
                        <CardDescription>
                            Permanently delete this keyword and all its
                            generated articles.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete Keyword
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
