import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Globe,
    Key,
    Link2,
    Plus,
    Settings,
} from 'lucide-react';

interface Project {
    id: number;
    name: string;
    website_url: string | null;
    description: string | null;
    primary_language: string | null;
    target_region: string | null;
    default_tone: string | null;
    keywords_count: number;
    articles_count: number;
    integrations_count: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
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

function formatUrl(url: string): string {
    try {
        const parsed = new URL(url);
        return parsed.hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

function formatTone(tone: string | null): string {
    if (!tone) return '';
    return tone.charAt(0).toUpperCase() + tone.slice(1);
}

export default function Index({ projects }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Projects</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your content projects
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/projects/create">
                            <Plus className="mr-2 h-4 w-4" />
                            New Project
                        </Link>
                    </Button>
                </div>

                {projects.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="mb-4 rounded-full bg-muted p-4">
                                <FileText className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">
                                No projects yet
                            </h3>
                            <p className="mb-6 max-w-sm text-center text-sm text-muted-foreground">
                                Create your first project to start generating
                                SEO-optimized content for your website.
                            </p>
                            <Button asChild>
                                <Link href="/projects/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create your first project
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <Card
                                key={project.id}
                                className="group relative transition-all hover:border-primary hover:shadow-md"
                            >
                                {/* Settings button in top right */}
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Link
                                            href={`/projects/${project.id}/settings`}
                                            className="absolute top-3 right-3 z-10 rounded-md p-1.5 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 hover:bg-muted hover:text-foreground"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Settings className="h-4 w-4" />
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Project Settings</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Link href={`/projects/${project.id}`}>
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between pr-8">
                                            <div className="space-y-1">
                                                <CardTitle className="text-lg">
                                                    {project.name}
                                                </CardTitle>
                                                {project.website_url && (
                                                    <CardDescription className="flex items-center gap-1">
                                                        <Globe className="h-3 w-3" />
                                                        {formatUrl(
                                                            project.website_url,
                                                        )}
                                                    </CardDescription>
                                                )}
                                            </div>
                                        </div>
                                        {project.description && (
                                            <p className="line-clamp-2 pt-2 text-sm text-muted-foreground">
                                                {project.description}
                                            </p>
                                        )}
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        {/* Stats row */}
                                        <div className="mb-4 flex items-center gap-4 text-sm">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className="flex items-center gap-1.5 text-muted-foreground">
                                                        <Key className="h-3.5 w-3.5" />
                                                        <span className="font-medium">
                                                            {
                                                                project.keywords_count
                                                            }
                                                        </span>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Keywords</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className="flex items-center gap-1.5 text-muted-foreground">
                                                        <FileText className="h-3.5 w-3.5" />
                                                        <span className="font-medium">
                                                            {
                                                                project.articles_count
                                                            }
                                                        </span>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Articles</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            {project.integrations_count > 0 && (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className="flex items-center gap-1.5 text-muted-foreground">
                                                            <Link2 className="h-3.5 w-3.5" />
                                                            <span className="font-medium">
                                                                {
                                                                    project.integrations_count
                                                                }
                                                            </span>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Integrations</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            )}
                                        </div>

                                        {/* Tags row */}
                                        <div className="flex flex-wrap items-center gap-2">
                                            {project.primary_language && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {project.primary_language}
                                                </Badge>
                                            )}
                                            {project.target_region &&
                                                project.target_region !==
                                                    'global' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {project.target_region}
                                                    </Badge>
                                                )}
                                            {project.default_tone && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {formatTone(
                                                        project.default_tone,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>

                                        {/* Website link */}
                                        {project.website_url && (
                                            <a
                                                href={project.website_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <ExternalLink className="h-3 w-3" />
                                                Visit website
                                            </a>
                                        )}
                                    </CardContent>
                                </Link>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
