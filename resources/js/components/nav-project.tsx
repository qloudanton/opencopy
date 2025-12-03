import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { type Project, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, FolderKanban, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

const LAST_PROJECT_KEY = 'opencopy_last_project_id';

function getDomainFromUrl(url: string | null): string | null {
    if (!url) return null;
    try {
        const parsed = new URL(url);
        return parsed.hostname;
    } catch {
        return null;
    }
}

function ProjectIcon({
    project,
    size = 'md',
}: {
    project: Project;
    size?: 'sm' | 'md';
}) {
    const [imgError, setImgError] = useState(false);
    const domain = getDomainFromUrl(project.website_url);
    const iconSize = size === 'sm' ? 'size-3.5' : 'size-4';
    const containerSize = size === 'sm' ? 'size-6' : 'size-8';

    if (domain && !imgError) {
        return (
            <div
                className={`flex ${containerSize} items-center justify-center overflow-hidden rounded-lg bg-muted`}
            >
                <img
                    src={`https://www.google.com/s2/favicons?domain=${domain}&sz=32`}
                    alt={`${project.name} favicon`}
                    className="h-5 w-5 object-contain"
                    onError={() => setImgError(true)}
                />
            </div>
        );
    }

    return (
        <div
            className={`flex ${containerSize} items-center justify-center rounded-lg ${size === 'sm' ? 'border' : 'bg-sidebar-primary text-sidebar-primary-foreground'}`}
        >
            <FolderKanban className={iconSize} />
        </div>
    );
}

export function NavProject() {
    const { projects, currentProject } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    // Store last project in localStorage when it changes
    useEffect(() => {
        if (currentProject) {
            localStorage.setItem(LAST_PROJECT_KEY, String(currentProject.id));
        }
    }, [currentProject]);

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {currentProject ? (
                                <ProjectIcon
                                    project={currentProject}
                                    size="md"
                                />
                            ) : (
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                    <FolderKanban className="size-4" />
                                </div>
                            )}
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentProject?.name ?? 'Select Project'}
                                </span>
                                {currentProject?.domain && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {currentProject.domain}
                                    </span>
                                )}
                                {!currentProject && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        No project selected
                                    </span>
                                )}
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Projects
                        </DropdownMenuLabel>
                        {projects.length === 0 ? (
                            <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                                No projects yet
                            </div>
                        ) : (
                            projects.map((project) => (
                                <DropdownMenuItem key={project.id} asChild>
                                    <Link
                                        href={`/projects/${project.id}`}
                                        className="flex cursor-pointer items-center gap-2"
                                    >
                                        <ProjectIcon
                                            project={project}
                                            size="sm"
                                        />
                                        <span className="flex-1 truncate">
                                            {project.name}
                                        </span>
                                        {currentProject?.id === project.id && (
                                            <Check className="size-4 text-primary" />
                                        )}
                                    </Link>
                                </DropdownMenuItem>
                            ))
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href="/projects/create"
                                className="flex cursor-pointer items-center gap-2"
                            >
                                <div className="flex size-6 items-center justify-center rounded-sm border border-dashed">
                                    <Plus className="size-3.5" />
                                </div>
                                <span className="text-muted-foreground">
                                    Create new project
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export function getLastProjectId(): number | null {
    if (typeof window === 'undefined') return null;
    const stored = localStorage.getItem(LAST_PROJECT_KEY);
    return stored ? parseInt(stored, 10) : null;
}
