import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { toneOptions, wordCountOptions } from '@/lib/content-options';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { Image, Plus, Type, X } from 'lucide-react';
import * as React from 'react';

interface AiProvider {
    id: number;
    name: string;
    provider: string;
}

interface Project {
    id: number;
    name: string;
    default_ai_provider_id: number | null;
    default_image_provider_id: number | null;
    default_word_count: number;
    default_tone: string;
    target_audiences: string[] | null;
    competitors: string[] | null;
    brand_guidelines: string | null;
    include_emojis: boolean;
}

interface Props {
    project: Project;
    textProviders: AiProvider[];
    imageProviders: AiProvider[];
    accountDefaultTextProvider: AiProvider | null;
    accountDefaultImageProvider: AiProvider | null;
}

export default function Content({
    project,
    textProviders,
    imageProviders,
    accountDefaultTextProvider,
    accountDefaultImageProvider,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        { title: 'Content', href: `/projects/${project.id}/settings/content` },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            default_ai_provider_id:
                project.default_ai_provider_id?.toString() ?? '',
            default_image_provider_id:
                project.default_image_provider_id?.toString() ?? '',
            default_word_count: project.default_word_count,
            default_tone: project.default_tone,
            target_audiences: project.target_audiences ?? [],
            competitors: project.competitors ?? [],
            brand_guidelines: project.brand_guidelines ?? '',
            include_emojis: project.include_emojis ?? false,
        });

    const [newAudience, setNewAudience] = React.useState('');
    const [newCompetitor, setNewCompetitor] = React.useState('');

    function handleAddAudience(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const trimmed = newAudience.trim();
            if (trimmed && !data.target_audiences.includes(trimmed)) {
                setData('target_audiences', [
                    ...data.target_audiences,
                    trimmed,
                ]);
                setNewAudience('');
            }
        }
    }

    function handleRemoveAudience(audience: string) {
        setData(
            'target_audiences',
            data.target_audiences.filter((a) => a !== audience),
        );
    }

    function handleAddCompetitor(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const trimmed = newCompetitor.trim();
            if (trimmed && !data.competitors.includes(trimmed)) {
                setData('competitors', [...data.competitors, trimmed]);
                setNewCompetitor('');
            }
        }
    }

    function handleRemoveCompetitor(competitor: string) {
        setData(
            'competitors',
            data.competitors.filter((c) => c !== competitor),
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/content`, {
            data: {
                ...data,
                default_ai_provider_id: data.default_ai_provider_id
                    ? parseInt(data.default_ai_provider_id)
                    : null,
                default_image_provider_id: data.default_image_provider_id
                    ? parseInt(data.default_image_provider_id)
                    : null,
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Content Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Content"
                    description="Configure how your articles are generated"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* AI Providers Section */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">AI Providers</h4>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {/* Text Generation */}
                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="default_ai_provider_id"
                                    label="Text Generation"
                                    icon={Type}
                                    tooltip="The AI model used to generate article content for this project. Choose 'Use account default' to automatically use whatever provider is set in your account settings."
                                />
                                <Select
                                    value={
                                        data.default_ai_provider_id ||
                                        'account-default'
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'default_ai_provider_id',
                                            value === 'account-default'
                                                ? ''
                                                : value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="account-default">
                                            Use account default
                                            {accountDefaultTextProvider && (
                                                <span className="ml-1 text-muted-foreground">
                                                    (
                                                    {
                                                        accountDefaultTextProvider.name
                                                    }
                                                    )
                                                </span>
                                            )}
                                        </SelectItem>
                                        <div className="my-1 border-t" />
                                        {textProviders.length === 0 ? (
                                            <div className="px-2 py-1.5 text-sm text-muted-foreground">
                                                No text providers configured
                                            </div>
                                        ) : (
                                            textProviders.map((provider) => (
                                                <SelectItem
                                                    key={provider.id}
                                                    value={provider.id.toString()}
                                                >
                                                    {provider.name} (
                                                    {provider.provider})
                                                </SelectItem>
                                            ))
                                        )}
                                        <div className="my-1 border-t" />
                                        <button
                                            type="button"
                                            className="relative flex w-full cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm text-primary outline-none hover:bg-accent focus:bg-accent"
                                            onClick={() =>
                                                router.visit(
                                                    '/settings/ai-providers',
                                                )
                                            }
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Provider
                                        </button>
                                    </SelectContent>
                                </Select>
                                {!data.default_ai_provider_id &&
                                    !accountDefaultTextProvider && (
                                        <p className="text-xs text-amber-600">
                                            No account default set.{' '}
                                            <button
                                                type="button"
                                                className="underline"
                                                onClick={() =>
                                                    router.visit(
                                                        '/settings/generation',
                                                    )
                                                }
                                            >
                                                Configure account defaults
                                            </button>
                                        </p>
                                    )}
                                <InputError
                                    message={errors.default_ai_provider_id}
                                />
                            </div>

                            {/* Image Generation */}
                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="default_image_provider_id"
                                    label="Image Generation"
                                    icon={Image}
                                    tooltip="The AI model used to generate images for this project. Choose 'Use account default' to automatically use whatever provider is set in your account settings."
                                />
                                <Select
                                    value={
                                        data.default_image_provider_id ||
                                        'account-default'
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'default_image_provider_id',
                                            value === 'account-default'
                                                ? ''
                                                : value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="account-default">
                                            Use account default
                                            {accountDefaultImageProvider && (
                                                <span className="ml-1 text-muted-foreground">
                                                    (
                                                    {
                                                        accountDefaultImageProvider.name
                                                    }
                                                    )
                                                </span>
                                            )}
                                        </SelectItem>
                                        <div className="my-1 border-t" />
                                        {imageProviders.length === 0 ? (
                                            <div className="px-2 py-1.5 text-sm text-muted-foreground">
                                                No image providers configured
                                            </div>
                                        ) : (
                                            imageProviders.map((provider) => (
                                                <SelectItem
                                                    key={provider.id}
                                                    value={provider.id.toString()}
                                                >
                                                    {provider.name} (
                                                    {provider.provider})
                                                </SelectItem>
                                            ))
                                        )}
                                        <div className="my-1 border-t" />
                                        <button
                                            type="button"
                                            className="relative flex w-full cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm text-primary outline-none hover:bg-accent focus:bg-accent"
                                            onClick={() =>
                                                router.visit(
                                                    '/settings/ai-providers',
                                                )
                                            }
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Provider
                                        </button>
                                    </SelectContent>
                                </Select>
                                {!data.default_image_provider_id &&
                                    !accountDefaultImageProvider && (
                                        <p className="text-xs text-amber-600">
                                            No account default set.{' '}
                                            <button
                                                type="button"
                                                className="underline"
                                                onClick={() =>
                                                    router.visit(
                                                        '/settings/generation',
                                                    )
                                                }
                                            >
                                                Configure account defaults
                                            </button>
                                        </p>
                                    )}
                                <InputError
                                    message={errors.default_image_provider_id}
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Writing Style Section */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">Writing Style</h4>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="default_word_count"
                                    label="Target Word Count"
                                    tooltip="The approximate length of generated articles. Longer articles may rank better for competitive keywords, while shorter articles work well for simple topics."
                                />
                                <Select
                                    value={data.default_word_count.toString()}
                                    onValueChange={(value) =>
                                        setData(
                                            'default_word_count',
                                            parseInt(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="default_word_count">
                                        <SelectValue placeholder="Select length" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {wordCountOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value.toString()}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.default_word_count}
                                />
                            </div>

                            <div className="space-y-2">
                                <FieldLabel
                                    htmlFor="default_tone"
                                    label="Tone of Voice"
                                    tooltip="The voice and personality of your content. Choose a tone that matches your brand and resonates with your target audience."
                                />
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
                                                {tone.label} -{' '}
                                                {tone.description}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.default_tone} />
                            </div>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <FieldLabel
                                    htmlFor="include_emojis"
                                    label="Include Emojis"
                                    tooltip="Add relevant emojis to headings and key points. This can make content feel more casual and engaging, but may not suit all brands."
                                />
                            </div>
                            <Switch
                                id="include_emojis"
                                checked={data.include_emojis}
                                onCheckedChange={(checked) =>
                                    setData('include_emojis', checked)
                                }
                            />
                        </div>

                        <p className="text-xs text-muted-foreground">
                            These are default settings for new content. You can
                            override the tone of voice and word count for
                            individual articles in the Content Planner.
                        </p>
                    </div>

                    <Separator />

                    {/* Audience Section */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">
                            Audience & Brand
                        </h4>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="target_audiences"
                                label="Target Audiences"
                                tooltip="Who you're writing for. The AI uses this to adjust vocabulary, examples, and explanations. These are auto-populated during onboarding but can be edited here."
                            />
                            {data.target_audiences.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {data.target_audiences.map((audience) => (
                                        <Badge
                                            key={audience}
                                            variant="secondary"
                                            className="gap-1 pr-1"
                                        >
                                            {audience}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemoveAudience(
                                                        audience,
                                                    )
                                                }
                                                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                            >
                                                <X className="h-3 w-3" />
                                                <span className="sr-only">
                                                    Remove {audience}
                                                </span>
                                            </button>
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            <Input
                                id="target_audiences"
                                value={newAudience}
                                onChange={(e) => setNewAudience(e.target.value)}
                                onKeyDown={handleAddAudience}
                                placeholder="Type an audience and press Enter"
                            />
                            <p className="text-xs text-muted-foreground">
                                Press Enter to add each audience
                            </p>
                            <InputError message={errors.target_audiences} />
                        </div>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="competitors"
                                label="Competitors"
                                tooltip="Add competitor websites or company names. The AI uses this when generating keyword suggestions to find gaps where you could outrank competitors and discover topics they may have missed."
                            />
                            {data.competitors.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {data.competitors.map((competitor) => (
                                        <Badge
                                            key={competitor}
                                            variant="secondary"
                                            className="gap-1 pr-1"
                                        >
                                            <img
                                                src={`https://www.google.com/s2/favicons?domain=${competitor}&sz=16`}
                                                alt=""
                                                className="h-4 w-4"
                                                onError={(e) => {
                                                    e.currentTarget.style.display =
                                                        'none';
                                                }}
                                            />
                                            {competitor}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemoveCompetitor(
                                                        competitor,
                                                    )
                                                }
                                                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                            >
                                                <X className="h-3 w-3" />
                                                <span className="sr-only">
                                                    Remove {competitor}
                                                </span>
                                            </button>
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            <Input
                                id="competitors"
                                value={newCompetitor}
                                onChange={(e) =>
                                    setNewCompetitor(e.target.value)
                                }
                                onKeyDown={handleAddCompetitor}
                                placeholder="Enter competitor domain and press Enter (e.g., competitor.com)"
                            />
                            <p className="text-xs text-muted-foreground">
                                Press Enter to add each competitor
                            </p>
                            <InputError message={errors.competitors} />
                        </div>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="brand_guidelines"
                                label="Brand Guidelines"
                                tooltip="Specific rules for the AI to follow when writing. Include things like: words to avoid, phrases to use, formatting preferences, or any other style requirements."
                            />
                            <Textarea
                                id="brand_guidelines"
                                value={data.brand_guidelines}
                                onChange={(e) =>
                                    setData('brand_guidelines', e.target.value)
                                }
                                placeholder="e.g., Use active voice, avoid jargon, never say 'utilize' - say 'use' instead"
                                rows={4}
                                maxLength={2000}
                            />
                            <InputError message={errors.brand_guidelines} />
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">
                                Saved
                            </p>
                        </Transition>
                    </div>
                </form>
            </ProjectSettingsLayout>
        </AppLayout>
    );
}
