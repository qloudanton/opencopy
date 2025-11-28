import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface AiProvider {
    id: number;
    name: string;
    provider: string;
}

interface Project {
    id: number;
    name: string;
    default_ai_provider_id: number | null;
    default_word_count: number;
    default_tone: string;
    target_audience: string | null;
    brand_guidelines: string | null;
    primary_language: string;
    target_region: string | null;
    internal_links_per_article: number;
    // Engagement settings
    brand_color: string | null;
    image_style: string;
    include_youtube_videos: boolean;
    include_emojis: boolean;
    include_infographic_placeholders: boolean;
    include_cta: boolean;
    cta_product_name: string | null;
    cta_website_url: string | null;
    cta_features: string | null;
    cta_action_text: string | null;
}

interface Props {
    project: Project;
    aiProviders: AiProvider[];
}

const toneOptions = [
    {
        value: 'professional',
        label: 'Professional',
        description: 'Formal, business-appropriate',
    },
    {
        value: 'casual',
        label: 'Casual',
        description: 'Relaxed, conversational',
    },
    { value: 'friendly', label: 'Friendly', description: 'Warm, approachable' },
    {
        value: 'technical',
        label: 'Technical',
        description: 'Precise, detailed',
    },
    {
        value: 'authoritative',
        label: 'Authoritative',
        description: 'Expert, confident',
    },
    {
        value: 'conversational',
        label: 'Conversational',
        description: 'Like talking to a friend',
    },
];

const languageOptions = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'it', label: 'Italian' },
    { value: 'nl', label: 'Dutch' },
    { value: 'pl', label: 'Polish' },
    { value: 'ru', label: 'Russian' },
    { value: 'zh', label: 'Chinese' },
    { value: 'ja', label: 'Japanese' },
    { value: 'ko', label: 'Korean' },
];

const regionOptions = [
    { value: 'global', label: 'Global (no specific region)' },
    { value: 'us', label: 'United States' },
    { value: 'uk', label: 'United Kingdom' },
    { value: 'ca', label: 'Canada' },
    { value: 'au', label: 'Australia' },
    { value: 'de', label: 'Germany' },
    { value: 'fr', label: 'France' },
    { value: 'es', label: 'Spain' },
    { value: 'br', label: 'Brazil' },
    { value: 'in', label: 'India' },
    { value: 'jp', label: 'Japan' },
    { value: 'kr', label: 'South Korea' },
    { value: 'cn', label: 'China' },
];

const imageStyleOptions = [
    {
        value: 'illustration',
        label: 'Illustration',
        description: 'Clean, modern illustrations',
    },
    { value: 'sketch', label: 'Sketch', description: 'Hand-drawn style' },
    {
        value: 'watercolor',
        label: 'Watercolor',
        description: 'Artistic watercolor effect',
    },
    {
        value: 'cinematic',
        label: 'Cinematic',
        description: 'Dramatic, movie-like visuals',
    },
    {
        value: 'brand-text',
        label: 'Brand Text',
        description: 'Title overlaid on brand color',
    },
];

export default function Settings({ project, aiProviders }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        default_ai_provider_id:
            project.default_ai_provider_id?.toString() ?? '',
        default_word_count: project.default_word_count,
        default_tone: project.default_tone,
        target_audience: project.target_audience ?? '',
        brand_guidelines: project.brand_guidelines ?? '',
        primary_language: project.primary_language,
        target_region: project.target_region ?? '',
        internal_links_per_article: project.internal_links_per_article,
        // Engagement settings
        brand_color: project.brand_color ?? '',
        image_style: project.image_style ?? 'illustration',
        include_youtube_videos: project.include_youtube_videos ?? false,
        include_emojis: project.include_emojis ?? false,
        include_infographic_placeholders:
            project.include_infographic_placeholders ?? false,
        include_cta: project.include_cta ?? true,
        cta_product_name: project.cta_product_name ?? '',
        cta_website_url: project.cta_website_url ?? '',
        cta_features: project.cta_features ?? '',
        cta_action_text: project.cta_action_text ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings`, {
            data: {
                ...data,
                default_ai_provider_id: data.default_ai_provider_id
                    ? parseInt(data.default_ai_provider_id)
                    : null,
                target_region: data.target_region || null,
            },
        });
    }

    function handleDelete() {
        router.delete(`/projects/${project.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Settings - ${project.name}`} />
            <div className="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Project Settings</h1>
                    <p className="text-muted-foreground">
                        Configure content generation settings for {project.name}
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Content Generation</CardTitle>
                            <CardDescription>
                                Default settings for AI-generated articles
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="default_ai_provider_id">
                                    Default AI Provider
                                </Label>
                                <Select
                                    value={data.default_ai_provider_id}
                                    onValueChange={(value) =>
                                        setData('default_ai_provider_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {aiProviders.length === 0 ? (
                                            <SelectItem value="" disabled>
                                                No providers configured
                                            </SelectItem>
                                        ) : (
                                            aiProviders.map((provider) => (
                                                <SelectItem
                                                    key={provider.id}
                                                    value={provider.id.toString()}
                                                >
                                                    {provider.name} (
                                                    {provider.provider})
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Used when generating articles unless
                                    overridden
                                </p>
                                <InputError
                                    message={errors.default_ai_provider_id}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="default_word_count">
                                        Target Word Count
                                    </Label>
                                    <Input
                                        id="default_word_count"
                                        type="number"
                                        min={500}
                                        max={5000}
                                        value={data.default_word_count}
                                        onChange={(e) =>
                                            setData(
                                                'default_word_count',
                                                parseInt(e.target.value) ||
                                                    1500,
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        500-5000 words
                                    </p>
                                    <InputError
                                        message={errors.default_word_count}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="default_tone">
                                        Writing Tone
                                    </Label>
                                    <Select
                                        value={data.default_tone}
                                        onValueChange={(value) =>
                                            setData('default_tone', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {toneOptions.map((tone) => (
                                                <SelectItem
                                                    key={tone.value}
                                                    value={tone.value}
                                                >
                                                    {tone.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.default_tone} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="target_audience">
                                    Target Audience
                                </Label>
                                <Input
                                    id="target_audience"
                                    value={data.target_audience}
                                    onChange={(e) =>
                                        setData(
                                            'target_audience',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g., Small business owners looking to improve SEO"
                                    maxLength={500}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Describe who you're writing for
                                </p>
                                <InputError message={errors.target_audience} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="brand_guidelines">
                                    Brand Guidelines
                                </Label>
                                <Textarea
                                    id="brand_guidelines"
                                    value={data.brand_guidelines}
                                    onChange={(e) =>
                                        setData(
                                            'brand_guidelines',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g., Use active voice, avoid jargon, be conversational but professional"
                                    rows={4}
                                    maxLength={2000}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Style rules and preferences the AI should
                                    follow
                                </p>
                                <InputError message={errors.brand_guidelines} />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Save Changes
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SEO Preferences</CardTitle>
                            <CardDescription>
                                Language, region, and linking preferences
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="primary_language">
                                        Primary Language
                                    </Label>
                                    <Select
                                        value={data.primary_language}
                                        onValueChange={(value) =>
                                            setData('primary_language', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {languageOptions.map((lang) => (
                                                <SelectItem
                                                    key={lang.value}
                                                    value={lang.value}
                                                >
                                                    {lang.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.primary_language}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="target_region">
                                        Target Region
                                    </Label>
                                    <Select
                                        value={data.target_region}
                                        onValueChange={(value) =>
                                            setData('target_region', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a region" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {regionOptions.map((region) => (
                                                <SelectItem
                                                    key={region.value}
                                                    value={region.value}
                                                >
                                                    {region.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.target_region}
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="internal_links_per_article">
                                    Internal Links per Article
                                </Label>
                                <Input
                                    id="internal_links_per_article"
                                    type="number"
                                    min={0}
                                    max={10}
                                    value={data.internal_links_per_article}
                                    onChange={(e) =>
                                        setData(
                                            'internal_links_per_article',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className="w-24"
                                />
                                <p className="text-xs text-muted-foreground">
                                    How many internal links to include (0-10)
                                </p>
                                <InputError
                                    message={errors.internal_links_per_article}
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Save Changes
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Engagement</CardTitle>
                            <CardDescription>
                                Visual branding and content enhancement options
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Brand & Visual
                                </h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="brand_color">
                                            Brand Color
                                        </Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id="brand_color"
                                                type="color"
                                                value={
                                                    data.brand_color ||
                                                    '#3b82f6'
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'brand_color',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 w-14 cursor-pointer p-1"
                                            />
                                            <Input
                                                value={data.brand_color}
                                                onChange={(e) =>
                                                    setData(
                                                        'brand_color',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="#3b82f6"
                                                className="flex-1 font-mono"
                                                maxLength={7}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Used for featured images and visual
                                            accents
                                        </p>
                                        <InputError
                                            message={errors.brand_color}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="image_style">
                                            Image Style
                                        </Label>
                                        <Select
                                            value={data.image_style}
                                            onValueChange={(value) =>
                                                setData('image_style', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {imageStyleOptions.map(
                                                    (style) => (
                                                        <SelectItem
                                                            key={style.value}
                                                            value={style.value}
                                                        >
                                                            {style.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            Style for AI-generated images
                                            (coming soon)
                                        </p>
                                        <InputError
                                            message={errors.image_style}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Content Enhancements
                                </h4>
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="include_youtube_videos">
                                                YouTube Videos
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Embed relevant YouTube videos in
                                                articles
                                            </p>
                                        </div>
                                        <Switch
                                            id="include_youtube_videos"
                                            checked={
                                                data.include_youtube_videos
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'include_youtube_videos',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="include_emojis">
                                                Include Emojis
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Add emojis to headings and
                                                content
                                            </p>
                                        </div>
                                        <Switch
                                            id="include_emojis"
                                            checked={data.include_emojis}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'include_emojis',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="include_infographic_placeholders">
                                                Infographic Placeholders
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Add placeholders for
                                                infographics you can fill in
                                                later
                                            </p>
                                        </div>
                                        <Switch
                                            id="include_infographic_placeholders"
                                            checked={
                                                data.include_infographic_placeholders
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'include_infographic_placeholders',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <h4 className="text-sm font-medium">
                                            Call-to-Action
                                        </h4>
                                        <p className="text-xs text-muted-foreground">
                                            Include a contextual CTA promoting
                                            your product
                                        </p>
                                    </div>
                                    <Switch
                                        id="include_cta"
                                        checked={data.include_cta}
                                        onCheckedChange={(checked) =>
                                            setData('include_cta', checked)
                                        }
                                    />
                                </div>

                                {data.include_cta && (
                                    <div className="space-y-4 rounded-lg border p-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="cta_product_name">
                                                    Product Name
                                                </Label>
                                                <Input
                                                    id="cta_product_name"
                                                    value={
                                                        data.cta_product_name
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'cta_product_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="e.g., OpenCopy"
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.cta_product_name
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="cta_website_url">
                                                    Website URL
                                                </Label>
                                                <Input
                                                    id="cta_website_url"
                                                    type="url"
                                                    value={data.cta_website_url}
                                                    onChange={(e) =>
                                                        setData(
                                                            'cta_website_url',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="https://example.com"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={
                                                        errors.cta_website_url
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="cta_features">
                                                Key Features
                                            </Label>
                                            <Textarea
                                                id="cta_features"
                                                value={data.cta_features}
                                                onChange={(e) =>
                                                    setData(
                                                        'cta_features',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g., AI-powered content generation, SEO optimization, WordPress integration"
                                                rows={2}
                                                maxLength={500}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Features the AI will highlight
                                                in contextual CTAs
                                            </p>
                                            <InputError
                                                message={errors.cta_features}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="cta_action_text">
                                                Action Text
                                            </Label>
                                            <Input
                                                id="cta_action_text"
                                                value={data.cta_action_text}
                                                onChange={(e) =>
                                                    setData(
                                                        'cta_action_text',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g., Try for free, Get started, Learn more"
                                                maxLength={100}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                The call-to-action button text
                                            </p>
                                            <InputError
                                                message={errors.cta_action_text}
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Save Changes
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <Card className="border-destructive/50">
                    <CardHeader>
                        <CardTitle className="text-destructive">
                            Danger Zone
                        </CardTitle>
                        <CardDescription>
                            Irreversible actions for this project
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium">Delete Project</p>
                                <p className="text-sm text-muted-foreground">
                                    Permanently delete "{project.name}" and all
                                    its keywords, articles, and settings.
                                </p>
                            </div>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Project
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Delete "{project.name}"?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This action cannot be undone. This
                                            will permanently delete the project
                                            and all associated keywords,
                                            articles, and settings.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={handleDelete}
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        >
                                            Delete Project
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
