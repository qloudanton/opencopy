import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
    include_cta: boolean;
    cta_product_name: string | null;
    cta_website_url: string | null;
    cta_features: string | null;
    cta_action_text: string | null;
}

interface Props {
    project: Project;
}

export default function CallToAction({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        {
            title: 'Call-to-Action',
            href: `/projects/${project.id}/settings/call-to-action`,
        },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            include_cta: project.include_cta ?? false,
            cta_product_name: project.cta_product_name ?? '',
            cta_website_url: project.cta_website_url ?? '',
            cta_features: project.cta_features ?? '',
            cta_action_text: project.cta_action_text ?? '',
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/call-to-action`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Call-to-Action Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Call-to-Action"
                    description="Promote your product or service within articles"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="space-y-0.5">
                            <FieldLabel
                                htmlFor="include_cta"
                                label="Enable Call-to-Action"
                                tooltip="When enabled, the AI will naturally weave mentions of your product into articles where relevant. The CTA will feel organic, not like an advertisement."
                            />
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
                        <div className="space-y-6 rounded-lg border p-4">
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Product Details
                                </h4>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <FieldLabel
                                            htmlFor="cta_product_name"
                                            label="Product Name"
                                            tooltip="The name of your product or service that the AI will mention in articles. Use your official brand name."
                                        />
                                        <Input
                                            id="cta_product_name"
                                            value={data.cta_product_name}
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
                                            message={errors.cta_product_name}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <FieldLabel
                                            htmlFor="cta_website_url"
                                            label="Website URL"
                                            tooltip="The URL readers will be directed to. Use your homepage, a landing page, or a product page. Include https://."
                                        />
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
                                            message={errors.cta_website_url}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <FieldLabel
                                        htmlFor="cta_features"
                                        label="Key Features"
                                        tooltip="List the main benefits or features of your product. The AI uses these to write relevant, contextual mentions that connect your product to the article topic."
                                    />
                                    <Textarea
                                        id="cta_features"
                                        value={data.cta_features}
                                        onChange={(e) =>
                                            setData(
                                                'cta_features',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g., AI-powered content generation, SEO optimization, WordPress integration, automatic internal linking"
                                        rows={3}
                                        maxLength={500}
                                    />
                                    <InputError message={errors.cta_features} />
                                </div>

                                <div className="space-y-2">
                                    <FieldLabel
                                        htmlFor="cta_action_text"
                                        label="Action Text"
                                        tooltip="The call-to-action phrase that encourages readers to click. Keep it short and action-oriented."
                                    />
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
                                    <InputError
                                        message={errors.cta_action_text}
                                    />
                                </div>
                            </div>

                            {/* Preview */}
                            {data.cta_product_name && data.cta_website_url && (
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium">
                                        Preview
                                    </h4>
                                    <div className="rounded-lg bg-muted p-4 text-sm">
                                        <p className="text-muted-foreground">
                                            Example of how your CTA might appear
                                            in an article:
                                        </p>
                                        <p className="mt-2 italic">
                                            "...tools like{' '}
                                            <span className="font-medium text-primary">
                                                {data.cta_product_name}
                                            </span>{' '}
                                            can help automate this process.{' '}
                                            <a
                                                href={data.cta_website_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary underline"
                                            >
                                                {data.cta_action_text ||
                                                    'Learn more'}
                                            </a>
                                            ."
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
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
