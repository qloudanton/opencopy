import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
    website_url: string | null;
    description: string | null;
}

interface Props {
    project: Project;
}

export default function ProjectDetails({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            name: project.name,
            website_url: project.website_url ?? '',
            description: project.description ?? '',
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/general`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`General Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="General"
                    description="Basic project information"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Project Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="My Tech Blog"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="website_url">Website URL</Label>
                            <Input
                                id="website_url"
                                type="url"
                                value={data.website_url}
                                onChange={(e) =>
                                    setData('website_url', e.target.value)
                                }
                                placeholder="https://myblog.com"
                            />
                            <p className="text-xs text-muted-foreground">
                                The website where your content will be published
                            </p>
                            <InputError message={errors.website_url} />
                        </div>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="description"
                                label="Description (optional)"
                                tooltip="Describe your business, products, or services. This helps the AI understand your context when generating content, analyzing keywords, and creating content plans."
                            />
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="A brief description of this project and its purpose"
                                rows={12}
                            />
                            <InputError message={errors.description} />
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
