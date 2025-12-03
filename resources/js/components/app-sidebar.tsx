import { index as projectsIndex } from '@/actions/App/Http/Controllers/ProjectController';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavProject } from '@/components/nav-project';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    FileText,
    Folder,
    FolderKanban,
    Globe,
    Key,
    LayoutGrid,
    Link2,
    Settings,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/qloudanton/opencopy',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://opencopy.ai/docs',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { currentProject } = usePage<SharedData>().props;

    // Project-scoped navigation items (only shown when a project is selected)
    const projectNavItems: NavItem[] = currentProject
        ? [
              {
                  title: 'Overview',
                  href: `/projects/${currentProject.id}`,
                  icon: LayoutGrid,
              },
              {
                  title: 'Keywords',
                  href: `/projects/${currentProject.id}/keywords`,
                  icon: Key,
              },
              {
                  title: 'Content Planner',
                  href: `/projects/${currentProject.id}/planner`,
                  icon: CalendarDays,
              },
              {
                  title: 'Articles',
                  href: `/projects/${currentProject.id}/articles`,
                  icon: FileText,
              },
              {
                  title: 'Integrations',
                  href: `/projects/${currentProject.id}/integrations`,
                  icon: Link2,
              },
              {
                  title: 'Internal Pages',
                  href: `/projects/${currentProject.id}/pages`,
                  icon: Globe,
              },
              {
                  title: 'Project Settings',
                  href: `/projects/${currentProject.id}/settings`,
                  icon: Settings,
              },
          ]
        : [];

    // Global navigation items (always shown)
    const globalNavItems: NavItem[] = [
        {
            title: 'All Projects',
            href: projectsIndex(),
            icon: FolderKanban,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/projects" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavProject />
            </SidebarHeader>

            <SidebarContent>
                {currentProject && (
                    <NavMain items={projectNavItems} label="Project" />
                )}
                <NavMain items={globalNavItems} label="Navigation" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
