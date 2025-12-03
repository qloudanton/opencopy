import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { Clock, Rocket, Send } from 'lucide-react';

interface Project {
    id: number;
    name: string;
    auto_publish: 'manual' | 'immediate' | 'scheduled';
    skip_review: boolean;
}

interface Props {
    project: Project;
}

const autoPublishOptions = [
    {
        value: 'manual',
        label: 'Manual',
        description: 'Review and publish articles yourself',
        icon: Send,
    },
    {
        value: 'immediate',
        label: 'Immediate',
        description: 'Publish right after generation completes',
        icon: Rocket,
    },
    {
        value: 'scheduled',
        label: 'Scheduled',
        description: 'Publish at the scheduled date and time',
        icon: Clock,
    },
];

export default function Publishing({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        {
            title: 'Publishing',
            href: `/projects/${project.id}/settings/publishing`,
        },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            auto_publish: project.auto_publish ?? 'manual',
            skip_review: project.skip_review ?? false,
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/publishing`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Publishing Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Publishing"
                    description="Configure how articles are published after generation"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Auto-Publish Mode */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">
                            Auto-Publish Mode
                        </h4>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="auto_publish"
                                label="When to Publish"
                                tooltip="Choose when articles should be automatically published to your integrations (webhooks, WordPress, etc.)"
                            />
                            <Select
                                value={data.auto_publish}
                                onValueChange={(value) =>
                                    setData(
                                        'auto_publish',
                                        value as
                                            | 'manual'
                                            | 'immediate'
                                            | 'scheduled',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {autoPublishOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            <div className="flex items-center gap-2">
                                                <option.icon className="h-4 w-4 text-muted-foreground" />
                                                <span>{option.label}</span>
                                                <span className="text-muted-foreground">
                                                    - {option.description}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.auto_publish} />
                        </div>

                        {data.auto_publish === 'immediate' && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                <strong>Heads up:</strong> Articles will be
                                published to all active integrations
                                immediately after generation. If featured image
                                generation is enabled, there will be a short
                                delay to allow the image to be created first.
                            </div>
                        )}

                        {data.auto_publish === 'scheduled' && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                                Articles will be published when their scheduled
                                date and time arrives. Make sure to set a
                                scheduled date when adding content to the
                                planner.
                            </div>
                        )}
                    </div>

                    <Separator />

                    {/* Review Settings */}
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium">Review Workflow</h4>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <FieldLabel
                                    htmlFor="skip_review"
                                    label="Skip Review Step"
                                    tooltip="When enabled, articles are automatically approved after generation (skipping the 'In Review' status). This is required for scheduled publishing to work automatically."
                                />
                                <p className="text-sm text-muted-foreground">
                                    Auto-approve articles after generation
                                </p>
                            </div>
                            <Switch
                                id="skip_review"
                                checked={data.skip_review}
                                onCheckedChange={(checked) =>
                                    setData('skip_review', checked)
                                }
                            />
                        </div>
                        <InputError message={errors.skip_review} />

                        {data.auto_publish === 'scheduled' &&
                            !data.skip_review && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    <strong>Note:</strong> With scheduled
                                    publishing, you'll need to manually approve
                                    articles before they can be published at the
                                    scheduled time. Enable "Skip Review Step"
                                    for fully automated publishing.
                                </div>
                            )}
                    </div>

                    <Separator />

                    {/* Summary */}
                    <div className="space-y-2 rounded-lg bg-muted/50 p-4">
                        <h4 className="text-sm font-medium">
                            Your Publishing Workflow
                        </h4>
                        <ol className="list-inside list-decimal space-y-1 text-sm text-muted-foreground">
                            <li>Article is generated from keyword</li>
                            {data.skip_review ? (
                                <li>
                                    Article is automatically approved (review
                                    skipped)
                                </li>
                            ) : (
                                <li>
                                    Article enters "In Review" status for your
                                    review
                                </li>
                            )}
                            {data.auto_publish === 'manual' && (
                                <li>
                                    You manually publish when ready via the
                                    article page
                                </li>
                            )}
                            {data.auto_publish === 'immediate' && (
                                <li>
                                    Article is automatically published to all
                                    active integrations
                                </li>
                            )}
                            {data.auto_publish === 'scheduled' && (
                                <>
                                    {!data.skip_review && (
                                        <li>You approve the article</li>
                                    )}
                                    <li>
                                        Article is published at the scheduled
                                        date/time
                                    </li>
                                </>
                            )}
                        </ol>
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
