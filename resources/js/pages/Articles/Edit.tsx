import InputError from '@/components/input-error';
import { SeoScore } from '@/components/seo-score';
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
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import MDEditor from '@uiw/react-md-editor';
import { Check, Clipboard, Download, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface SeoBreakdownCategory {
    score: number;
    max: number;
    details: Record<string, unknown>;
}

interface SeoBreakdown {
    keyword_optimization: SeoBreakdownCategory;
    content_structure: SeoBreakdownCategory;
    content_length: SeoBreakdownCategory;
    meta_quality: SeoBreakdownCategory;
    enrichment: SeoBreakdownCategory;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    meta_description: string | null;
    content: string;
    content_markdown: string;
    status: string;
    seo_score: number | null;
    generation_metadata: {
        seo_breakdown?: SeoBreakdown;
    } | null;
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
    const [copied, setCopied] = useState(false);
    const [isImproving, setIsImproving] = useState(false);
    const [isRecalculating, setIsRecalculating] = useState(false);
    const [currentSeoScore, setCurrentSeoScore] = useState<number | null>(article.seo_score);
    const [currentSeoBreakdown, setCurrentSeoBreakdown] = useState(
        article.generation_metadata?.seo_breakdown,
    );
    const { csrf_token } = usePage<{ csrf_token: string }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        ...(article.keyword
            ? [
                  {
                      title: 'Keywords',
                      href: `/projects/${project.id}/keywords`,
                  },
                  {
                      title: article.keyword.keyword,
                      href: `/projects/${project.id}/keywords/${article.keyword.id}`,
                  },
              ]
            : []),
        {
            title: article.title,
            href: `/projects/${project.id}/articles/${article.id}`,
        },
        {
            title: 'Edit',
            href: `/projects/${project.id}/articles/${article.id}/edit`,
        },
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

    function handleCopyToClipboard() {
        navigator.clipboard.writeText(data.content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function handleDownload() {
        const blob = new Blob([data.content], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${article.slug}.md`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    async function handleImprove(improvementType: string) {
        setIsImproving(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/improve`,
                { improvement_type: improvementType },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                },
            );

            const result = response.data;

            // Apply the improvement to the form
            if (result.field === 'title') {
                setData('title', result.value);
            } else if (result.field === 'meta_description') {
                setData('meta_description', result.value);
            } else if (result.field === 'content') {
                setData('content', result.value);
            }

            toast.success(result.message);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to apply improvement');
            }
        } finally {
            setIsImproving(false);
        }
    }

    async function handleRecalculateSeo() {
        setIsRecalculating(true);
        try {
            const response = await axios.post(
                `/projects/${project.id}/articles/${article.id}/recalculate-seo`,
                {
                    title: data.title,
                    meta_description: data.meta_description,
                    content: data.content,
                },
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            setCurrentSeoScore(response.data.score);
            setCurrentSeoBreakdown(response.data.breakdown);
            toast.success('SEO score recalculated');
        } catch (error) {
            toast.error('Failed to recalculate SEO score');
        } finally {
            setIsRecalculating(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit - ${article.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Edit Article</h1>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleCopyToClipboard}
                        >
                            {copied ? (
                                <>
                                    <Check className="mr-2 h-4 w-4" />
                                    Copied!
                                </>
                            ) : (
                                <>
                                    <Clipboard className="mr-2 h-4 w-4" />
                                    Copy Markdown
                                </>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDownload}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Download .md
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Main form area */}
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-4 lg:col-span-2"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Article Details</CardTitle>
                                <CardDescription>
                                    Update your article information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            value={data.title}
                                            onChange={(e) =>
                                                setData('title', e.target.value)
                                            }
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            value={data.status}
                                            onValueChange={(value) =>
                                                setData('status', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft">
                                                    Draft
                                                </SelectItem>
                                                <SelectItem value="review">
                                                    Review
                                                </SelectItem>
                                                <SelectItem value="published">
                                                    Published
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="meta_description">
                                        Meta Description
                                    </Label>
                                    <Input
                                        id="meta_description"
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData(
                                                'meta_description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="SEO meta description (150-160 characters)"
                                        maxLength={255}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {data.meta_description.length}/160
                                        characters
                                    </p>
                                    <InputError
                                        message={errors.meta_description}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Content (Markdown)</Label>
                                    <div data-color-mode="light">
                                        <MDEditor
                                            value={data.content}
                                            onChange={(value) =>
                                                setData('content', value || '')
                                            }
                                            height={500}
                                            preview="live"
                                        />
                                    </div>
                                    <InputError message={errors.content} />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex justify-end gap-2">
                            <Button asChild variant="outline">
                                <Link
                                    href={`/projects/${project.id}/articles/${article.id}`}
                                >
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Save Changes
                            </Button>
                        </div>
                    </form>

                    {/* SEO Recommendations Sidebar */}
                    {(currentSeoScore !== null || currentSeoBreakdown) && (
                        <div className="lg:col-span-1">
                            <Card className="sticky top-4">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                SEO Recommendations
                                            </CardTitle>
                                            <CardDescription>
                                                Improve your score by addressing
                                                these items
                                            </CardDescription>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={handleRecalculateSeo}
                                            disabled={isRecalculating}
                                            title="Recalculate SEO score"
                                        >
                                            <RefreshCw
                                                className={`h-4 w-4 ${isRecalculating ? 'animate-spin' : ''}`}
                                            />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <SeoScore
                                        score={currentSeoScore}
                                        breakdown={currentSeoBreakdown}
                                        keyword={article.keyword?.keyword}
                                        onImprove={handleImprove}
                                        isImproving={isImproving}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
