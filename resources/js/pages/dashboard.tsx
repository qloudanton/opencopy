import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useProject } from '@/hooks/use-project';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { useEffect, useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { projects } = usePage<SharedData>().props;
    const { getLastProject } = useProject();

    // Check if there's a last project to redirect to
    const lastProject = useMemo(() => getLastProject(), [getLastProject]);

    // Redirect to last project if one exists
    useEffect(() => {
        if (lastProject) {
            router.visit(`/projects/${lastProject.id}`);
        }
    }, [lastProject]);

    // Show loading state while redirecting
    if (lastProject) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 items-center justify-center">
                    <div className="animate-pulse text-muted-foreground">
                        Loading...
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <FolderKanban className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle>Welcome to OpenCopy</CardTitle>
                        <CardDescription>
                            {projects.length === 0
                                ? 'Create your first project to get started with AI-powered content generation.'
                                : 'Select a project to continue or create a new one.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {projects.length > 0 && (
                            <div className="space-y-2">
                                {projects.slice(0, 5).map((project) => (
                                    <Button
                                        key={project.id}
                                        variant="outline"
                                        className="w-full justify-start"
                                        asChild
                                    >
                                        <Link href={`/projects/${project.id}`}>
                                            <FolderKanban className="mr-2 h-4 w-4" />
                                            {project.name}
                                        </Link>
                                    </Button>
                                ))}
                                {projects.length > 5 && (
                                    <Button
                                        variant="ghost"
                                        className="w-full"
                                        asChild
                                    >
                                        <Link href="/projects">
                                            View all {projects.length} projects
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                        <Button
                            asChild
                            className={projects.length > 0 ? '' : 'w-full'}
                        >
                            <Link href="/projects/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Create New Project
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
