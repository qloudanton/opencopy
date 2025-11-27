import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';

interface Keyword {
    id: number;
    keyword: string;
    secondary_keywords: string[] | null;
    search_intent: string | null;
    target_word_count: number | null;
    tone: string | null;
    additional_instructions: string | null;
    priority: number;
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
        { title: keyword.keyword, href: `/projects/${project.id}/keywords/${keyword.id}` },
        { title: 'Edit', href: `/projects/${project.id}/keywords/${keyword.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        keyword: keyword.keyword,
        secondary_keywords: keyword.secondary_keywords?.join(', ') || '',
        search_intent: keyword.search_intent || '',
        target_word_count: keyword.target_word_count?.toString() || '',
        tone: keyword.tone || '',
        additional_instructions: keyword.additional_instructions || '',
        priority: keyword.priority.toString(),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const formData = {
            ...data,
            secondary_keywords: data.secondary_keywords
                ? data.secondary_keywords.split(',').map(k => k.trim()).filter(k => k)
                : [],
            target_word_count: data.target_word_count ? parseInt(data.target_word_count) : null,
            priority: parseInt(data.priority),
            search_intent: data.search_intent || null,
            tone: data.tone || null,
        };
        put(`/projects/${project.id}/keywords/${keyword.id}`, {
            data: formData,
        });
    }

    function handleDelete() {
        if (confirm('Are you sure you want to delete this keyword? This will also delete all generated articles.')) {
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
                                    onChange={(e) => setData('keyword', e.target.value)}
                                    placeholder="best coffee machines 2024"
                                />
                                <InputError message={errors.keyword} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="secondary_keywords">Secondary Keywords (optional)</Label>
                                <Textarea
                                    id="secondary_keywords"
                                    value={data.secondary_keywords}
                                    onChange={(e) => setData('secondary_keywords', e.target.value)}
                                    placeholder="espresso machine, drip coffee maker, french press"
                                    rows={2}
                                />
                                <p className="text-muted-foreground text-xs">Separate keywords with commas</p>
                                <InputError message={errors.secondary_keywords} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="search_intent">Search Intent</Label>
                                    <Select
                                        value={data.search_intent}
                                        onValueChange={(value) => setData('search_intent', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select intent" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="informational">Informational</SelectItem>
                                            <SelectItem value="transactional">Transactional</SelectItem>
                                            <SelectItem value="navigational">Navigational</SelectItem>
                                            <SelectItem value="commercial">Commercial</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.search_intent} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tone">Tone</Label>
                                    <Select
                                        value={data.tone}
                                        onValueChange={(value) => setData('tone', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select tone" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="professional">Professional</SelectItem>
                                            <SelectItem value="casual">Casual</SelectItem>
                                            <SelectItem value="technical">Technical</SelectItem>
                                            <SelectItem value="friendly">Friendly</SelectItem>
                                            <SelectItem value="authoritative">Authoritative</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.tone} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="target_word_count">Target Word Count</Label>
                                    <Input
                                        id="target_word_count"
                                        type="number"
                                        value={data.target_word_count}
                                        onChange={(e) => setData('target_word_count', e.target.value)}
                                        placeholder="1500"
                                        min={300}
                                        max={10000}
                                    />
                                    <InputError message={errors.target_word_count} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="priority">Priority (0-100)</Label>
                                    <Input
                                        id="priority"
                                        type="number"
                                        value={data.priority}
                                        onChange={(e) => setData('priority', e.target.value)}
                                        min={0}
                                        max={100}
                                    />
                                    <InputError message={errors.priority} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="additional_instructions">Additional Instructions (optional)</Label>
                                <Textarea
                                    id="additional_instructions"
                                    value={data.additional_instructions}
                                    onChange={(e) => setData('additional_instructions', e.target.value)}
                                    placeholder="Include a comparison table, focus on budget options under $200..."
                                    rows={4}
                                />
                                <InputError message={errors.additional_instructions} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Update Keyword
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="max-w-2xl border-destructive">
                    <CardHeader>
                        <CardTitle className="text-destructive">Danger Zone</CardTitle>
                        <CardDescription>
                            Permanently delete this keyword and all its generated articles.
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
