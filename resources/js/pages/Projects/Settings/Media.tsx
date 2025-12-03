import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
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
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
    generate_inline_images: boolean;
    generate_featured_image: boolean;
    brand_color: string | null;
    image_style: string;
    include_youtube_videos: boolean;
    include_infographic_placeholders: boolean;
}

interface Props {
    project: Project;
    hasYouTubeApiKey: boolean;
}

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

export default function Media({ project, hasYouTubeApiKey }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        { title: 'Media', href: `/projects/${project.id}/settings/media` },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            generate_inline_images: project.generate_inline_images ?? true,
            generate_featured_image: project.generate_featured_image ?? true,
            brand_color: project.brand_color ?? '',
            image_style: project.image_style ?? 'illustration',
            include_youtube_videos: project.include_youtube_videos ?? false,
            include_infographic_placeholders:
                project.include_infographic_placeholders ?? false,
        });

    const showImageStyleSettings =
        data.generate_inline_images || data.generate_featured_image;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/media`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Media Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Media"
                    description="Configure images and multimedia in your articles"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Embedded Media Section */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">Embedded Media</h4>

                        <div className="space-y-4">
                            <div
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-4',
                                    !hasYouTubeApiKey && 'opacity-50',
                                )}
                            >
                                <div className="space-y-0.5">
                                    <FieldLabel
                                        htmlFor="include_youtube_videos"
                                        label="YouTube Videos"
                                        tooltip="Automatically find and embed relevant YouTube videos in your articles. Videos increase time on page and can improve engagement."
                                    />
                                    {!hasYouTubeApiKey && (
                                        <p className="text-xs text-muted-foreground">
                                            YouTube API key required.{' '}
                                            <button
                                                type="button"
                                                className="text-primary underline"
                                                onClick={() =>
                                                    router.visit(
                                                        '/settings/integrations',
                                                    )
                                                }
                                            >
                                                Configure in Integrations
                                            </button>
                                        </p>
                                    )}
                                </div>
                                <Switch
                                    id="include_youtube_videos"
                                    checked={data.include_youtube_videos}
                                    disabled={!hasYouTubeApiKey}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'include_youtube_videos',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <div className="space-y-0.5">
                                    <FieldLabel
                                        htmlFor="include_infographic_placeholders"
                                        label="Image Placeholders"
                                        tooltip="Add [IMAGE] and [INFOGRAPHIC] placeholder sections in generated articles. These can be replaced with AI-generated images or your own custom visuals."
                                    />
                                </div>
                                <Switch
                                    id="include_infographic_placeholders"
                                    checked={
                                        data.include_infographic_placeholders
                                    }
                                    onCheckedChange={(checked) => {
                                        setData(
                                            'include_infographic_placeholders',
                                            checked,
                                        );
                                        // If disabling placeholders, also disable inline image generation
                                        if (!checked) {
                                            setData(
                                                'generate_inline_images',
                                                false,
                                            );
                                        }
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* AI Image Generation Section */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">
                            AI Image Generation
                        </h4>

                        <div className="space-y-4">
                            <div
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-4',
                                    !data.include_infographic_placeholders &&
                                        'opacity-50',
                                )}
                            >
                                <div className="space-y-0.5">
                                    <FieldLabel
                                        htmlFor="generate_inline_images"
                                        label="Generate In-Article Images"
                                        tooltip="When enabled, AI will automatically generate images to replace [IMAGE] and [INFOGRAPHIC] placeholders in your articles. Requires Image Placeholders to be enabled."
                                    />
                                    {!data.include_infographic_placeholders && (
                                        <p className="text-xs text-muted-foreground">
                                            Enable Image Placeholders above to
                                            use this feature
                                        </p>
                                    )}
                                </div>
                                <Switch
                                    id="generate_inline_images"
                                    checked={data.generate_inline_images}
                                    disabled={
                                        !data.include_infographic_placeholders
                                    }
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'generate_inline_images',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <div className="space-y-0.5">
                                    <FieldLabel
                                        htmlFor="generate_featured_image"
                                        label="Generate Featured Image"
                                        tooltip="Automatically generate a featured/hero image for each article after content is generated. The image will match your configured style and brand color."
                                    />
                                </div>
                                <Switch
                                    id="generate_featured_image"
                                    checked={data.generate_featured_image}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'generate_featured_image',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    {/* Image Style Section - only show if image generation is enabled */}
                    {showImageStyleSettings && (
                        <>
                            <Separator />

                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Image Style
                                </h4>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <FieldLabel
                                            htmlFor="brand_color"
                                            label="Brand Color"
                                            tooltip="Your primary brand color, used as an accent in AI-generated images. This helps maintain visual consistency across your content."
                                        />
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
                                        <InputError
                                            message={errors.brand_color}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <FieldLabel
                                            htmlFor="image_style"
                                            label="Image Style"
                                            tooltip="The visual style for all AI-generated images (both featured and in-article). Choose a style that matches your brand aesthetic."
                                        />
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
                                                            <span>
                                                                {style.label}
                                                            </span>
                                                            <span className="ml-2 text-muted-foreground">
                                                                -{' '}
                                                                {
                                                                    style.description
                                                                }
                                                            </span>
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.image_style}
                                        />
                                    </div>
                                </div>
                            </div>
                        </>
                    )}

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
