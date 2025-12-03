import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn, isSameUrl, resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface Props extends PropsWithChildren {
    projectId: number;
    projectName: string;
}

export default function ProjectSettingsLayout({
    children,
    projectId,
    projectName,
}: Props) {
    const sidebarNavItems: NavItem[] = [
        {
            title: 'General',
            href: `/projects/${projectId}/settings`,
            icon: null,
        },
        {
            title: 'Content',
            href: `/projects/${projectId}/settings/content`,
            icon: null,
        },
        {
            title: 'Localization',
            href: `/projects/${projectId}/settings/localization`,
            icon: null,
        },
        {
            title: 'Internal Linking',
            href: `/projects/${projectId}/settings/internal-linking`,
            icon: null,
        },
        {
            title: 'Media',
            href: `/projects/${projectId}/settings/media`,
            icon: null,
        },
        {
            title: 'Call-to-Action',
            href: `/projects/${projectId}/settings/call-to-action`,
            icon: null,
        },
        {
            title: 'Publishing',
            href: `/projects/${projectId}/settings/publishing`,
            icon: null,
        },
        {
            title: 'Danger Zone',
            href: `/projects/${projectId}/settings/danger-zone`,
            icon: null,
        },
    ];

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading
                title="Project Settings"
                description={`Configure settings for ${projectName}`}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${resolveUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-start',
                                    item.title === 'Danger Zone' &&
                                        'text-destructive hover:text-destructive',
                                    {
                                        'bg-muted': isSameUrl(
                                            currentPath,
                                            item.href,
                                        ),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 lg:max-w-2xl">
                    <section className="space-y-6">{children}</section>
                </div>
            </div>
        </div>
    );
}
