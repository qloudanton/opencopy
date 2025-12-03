import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { axios } from '@/lib/axios';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2, RefreshCw } from 'lucide-react';
import * as React from 'react';

interface Project {
    id: number;
    name: string;
    internal_links_per_article: number;
    sitemap_url: string | null;
    auto_internal_linking: boolean;
    prioritize_blog_links: boolean;
    cross_link_articles: boolean;
    sitemap_last_fetched_at: string | null;
}

interface PageStats {
    total: number;
    active: number;
    by_type: {
        blog: number;
        product: number;
        service: number;
        landing: number;
        other: number;
    };
}

interface Props {
    project: Project;
    pageStats: PageStats;
}

export default function InternalLinking({ project, pageStats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        {
            title: 'Internal Linking',
            href: `/projects/${project.id}/settings/internal-linking`,
        },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            auto_internal_linking: project.auto_internal_linking ?? false,
            internal_links_per_article: project.internal_links_per_article ?? 3,
            sitemap_url: project.sitemap_url ?? '',
            prioritize_blog_links: project.prioritize_blog_links ?? true,
            cross_link_articles: project.cross_link_articles ?? true,
        });

    const [syncingPages, setSyncingPages] = React.useState(false);
    const [syncMessage, setSyncMessage] = React.useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/internal-linking`);
    }

    async function handleSyncSitemap() {
        setSyncingPages(true);
        setSyncMessage(null);

        try {
            const response = await axios.post(
                `/projects/${project.id}/pages/sync`,
            );
            const result = response.data;

            if (result.success) {
                setSyncMessage({ type: 'success', text: result.message });
                router.reload({ only: ['pageStats', 'project'] });
            } else {
                setSyncMessage({
                    type: 'error',
                    text: result.message || 'Failed to sync sitemap',
                });
            }
        } catch (error) {
            const message =
                axios.isAxiosError(error) && error.response?.data?.message
                    ? error.response.data.message
                    : 'Network error while syncing sitemap';
            setSyncMessage({
                type: 'error',
                text: message,
            });
        } finally {
            setSyncingPages(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Internal Linking Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Internal Linking"
                    description="Automatically add internal links to your articles"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="space-y-0.5">
                            <FieldLabel
                                htmlFor="auto_internal_linking"
                                label="Enable Internal Linking"
                                tooltip="When enabled, the AI will automatically find and insert relevant internal links from your page database while generating articles."
                            />
                        </div>
                        <Switch
                            id="auto_internal_linking"
                            checked={data.auto_internal_linking}
                            onCheckedChange={(checked) =>
                                setData('auto_internal_linking', checked)
                            }
                        />
                    </div>

                    {data.auto_internal_linking && (
                        <div className="space-y-6 rounded-lg border p-4">
                            {/* Link Count Section */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Link Density
                                </h4>

                                <div className="space-y-2">
                                    <FieldLabel
                                        htmlFor="internal_links_per_article"
                                        label="Links per Article"
                                        tooltip="The number of internal links to include in each generated article. More links can help with SEO and user navigation, but too many can feel spammy. 2-5 is typically ideal."
                                    />
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
                                        0-10 links (recommended: 2-5)
                                    </p>
                                    <InputError
                                        message={
                                            errors.internal_links_per_article
                                        }
                                    />
                                </div>
                            </div>

                            {/* Sitemap Section */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Page Database
                                </h4>

                                <div className="space-y-2">
                                    <FieldLabel
                                        htmlFor="sitemap_url"
                                        label="Sitemap URL"
                                        tooltip="URL to your sitemap.xml file. We'll import all pages from your sitemap so articles can link to them. You can also manually add pages."
                                    />
                                    <div className="flex gap-2">
                                        <Input
                                            id="sitemap_url"
                                            type="url"
                                            value={data.sitemap_url}
                                            onChange={(e) =>
                                                setData(
                                                    'sitemap_url',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="https://example.com/sitemap.xml"
                                            className="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleSyncSitemap}
                                            disabled={
                                                syncingPages ||
                                                !data.sitemap_url
                                            }
                                        >
                                            {syncingPages ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <RefreshCw className="mr-2 h-4 w-4" />
                                            )}
                                            {syncingPages
                                                ? 'Syncing...'
                                                : 'Sync'}
                                        </Button>
                                    </div>
                                    <InputError message={errors.sitemap_url} />
                                </div>

                                {syncMessage && (
                                    <div
                                        className={`rounded-lg p-3 text-sm ${
                                            syncMessage.type === 'success'
                                                ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                                                : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-400'
                                        }`}
                                    >
                                        {syncMessage.text}
                                    </div>
                                )}

                                {pageStats.total > 0 && (
                                    <div className="rounded-lg border p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                Imported Pages
                                            </span>
                                            <Link
                                                href={`/projects/${project.id}/pages`}
                                                className="text-sm text-primary hover:underline"
                                            >
                                                Manage Pages
                                            </Link>
                                        </div>
                                        <div className="mb-3 flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {pageStats.active} active /{' '}
                                                {pageStats.total} total
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-5 gap-2 text-center text-xs">
                                            <div className="rounded bg-muted p-2">
                                                <div className="font-medium">
                                                    {pageStats.by_type.blog}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    Blog
                                                </div>
                                            </div>
                                            <div className="rounded bg-muted p-2">
                                                <div className="font-medium">
                                                    {pageStats.by_type.product}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    Product
                                                </div>
                                            </div>
                                            <div className="rounded bg-muted p-2">
                                                <div className="font-medium">
                                                    {pageStats.by_type.service}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    Service
                                                </div>
                                            </div>
                                            <div className="rounded bg-muted p-2">
                                                <div className="font-medium">
                                                    {pageStats.by_type.landing}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    Landing
                                                </div>
                                            </div>
                                            <div className="rounded bg-muted p-2">
                                                <div className="font-medium">
                                                    {pageStats.by_type.other}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    Other
                                                </div>
                                            </div>
                                        </div>
                                        {project.sitemap_last_fetched_at && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                Last synced:{' '}
                                                {new Date(
                                                    project.sitemap_last_fetched_at,
                                                ).toLocaleString()}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Linking Behavior Section */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium">
                                    Linking Preferences
                                </h4>
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <FieldLabel
                                                htmlFor="prioritize_blog_links"
                                                label="Prioritize Blog Posts"
                                                tooltip="When choosing which pages to link to, prefer blog posts over product, service, or landing pages. Useful for building topical authority."
                                            />
                                        </div>
                                        <Switch
                                            id="prioritize_blog_links"
                                            checked={data.prioritize_blog_links}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'prioritize_blog_links',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <FieldLabel
                                                htmlFor="cross_link_articles"
                                                label="Cross-Link OpenCopy Articles"
                                                tooltip="Include links to other articles you've generated with OpenCopy. This helps build a connected content cluster and keeps readers on your site."
                                            />
                                        </div>
                                        <Switch
                                            id="cross_link_articles"
                                            checked={data.cross_link_articles}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'cross_link_articles',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
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
