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
import { Head, useForm } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
}

interface Props {
    project: Project;
}

export default function Create({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Keywords', href: `/projects/${project.id}/keywords` },
        { title: 'Create', href: `/projects/${project.id}/keywords/create` },
    ];

    const { data, setData, post, processing, errors } = useForm({
        keyword: '',
        secondary_keywords: '',
        search_intent: '',
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
        post(`/projects/${project.id}/keywords`, {
            data: formData,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Add Keyword - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-bold">Add Keyword</h1>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Keyword Details</CardTitle>
                        <CardDescription>
                            Add a keyword to generate SEO-optimized content for.
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
                                Add Keyword
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
