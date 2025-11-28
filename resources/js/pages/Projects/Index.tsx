import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
    domain: string | null;
    description: string | null;
    keywords_count: number;
    articles_count: number;
    is_active: boolean;
    created_at: string;
}

interface Props {
    projects: Project[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
];

export default function Index({ projects }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Projects</h1>
                    <Button asChild>
                        <Link href="/projects/create">Create Project</Link>
                    </Button>
                </div>

                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="mb-4 text-muted-foreground">
                                No projects yet
                            </p>
                            <Button asChild>
                                <Link href="/projects/create">
                                    Create your first project
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={`/projects/${project.id}`}
                            >
                                <Card className="transition-colors hover:border-primary">
                                    <CardHeader>
                                        <CardTitle>{project.name}</CardTitle>
                                        {project.domain && (
                                            <CardDescription>
                                                {project.domain}
                                            </CardDescription>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex gap-4 text-sm text-muted-foreground">
                                            <span>
                                                {project.keywords_count}{' '}
                                                keywords
                                            </span>
                                            <span>
                                                {project.articles_count}{' '}
                                                articles
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
