import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { format, isToday, isTomorrow } from 'date-fns';
import {
    AlertCircle,
    ArrowRight,
    Calendar,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileText,
    Inbox,
    Key,
    Link2,
    Loader2,
    Plus,
    Settings,
    Sparkles,
    XCircle,
} from 'lucide-react';

interface Keyword {
    id: number;
    keyword: string;
}

interface Article {
    id: number;
    title: string;
    status: string;
    keyword?: Keyword | null;
    created_at: string;
}

interface ScheduledContent {
    id: number;
    title: string | null;
    status: string;
    scheduled_date: string | null;
    scheduled_time: string | null;
    error_message: string | null;
    keyword?: Keyword | null;
    article?: { id: number; title: string; status: string } | null;
}

interface Integration {
    id: number;
    platform: string;
    name: string;
    is_active: boolean;
    last_published_at: string | null;
}

interface Project {
    id: number;
    name: string;
    website_url: string | null;
    description: string | null;
    keywords_count: number;
    articles_count: number;
    integrations_count: number;
}

interface ContentRunway {
    days_ahead: number;
    scheduled_count: number;
    next_scheduled: {
        id: number;
        title: string;
        scheduled_date: string | null;
        scheduled_time: string | null;
    } | null;
    last_scheduled_date: string | null;
    backlog_count: number;
    published_count: number;
    failed_count: number;
}

interface Props {
    project: Project;
    contentRunway: ContentRunway;
    needsAttention: ScheduledContent[];
    generating: ScheduledContent[];
    upcomingContent: ScheduledContent[];
    recentArticles: Article[];
    untargetedKeywords: Keyword[];
    integrations: Integration[];
}

const statusConfig: Record<string, { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
    backlog: { label: 'Backlog', color: 'bg-slate-500', icon: Inbox },
    scheduled: { label: 'Scheduled', color: 'bg-blue-500', icon: Calendar },
    generating: { label: 'Generating', color: 'bg-yellow-500', icon: Sparkles },
    published: { label: 'Published', color: 'bg-emerald-500', icon: CheckCircle2 },
    failed: { label: 'Failed', color: 'bg-red-500', icon: XCircle },
};

function formatScheduledDate(dateStr: string | null): string {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isToday(date)) return 'Today';
    if (isTomorrow(date)) return 'Tomorrow';
    return format(date, 'MMM d');
}

function formatFullDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return format(new Date(dateStr), 'MMMM d, yyyy');
}

function getRunwayStatus(days: number): { color: string; bgColor: string; label: string } {
    if (days >= 14) {
        return { color: 'text-emerald-600', bgColor: 'bg-emerald-50 dark:bg-emerald-950/30', label: "You're covered" };
    } else if (days >= 7) {
        return { color: 'text-yellow-600', bgColor: 'bg-yellow-50 dark:bg-yellow-950/30', label: 'Plan ahead soon' };
    } else if (days > 0) {
        return { color: 'text-orange-600', bgColor: 'bg-orange-50 dark:bg-orange-950/30', label: 'Running low' };
    }
    return { color: 'text-muted-foreground', bgColor: 'bg-muted/30', label: 'No content scheduled' };
}

export default function Show({
    project,
    contentRunway,
    needsAttention,
    generating,
    upcomingContent,
    recentArticles,
    untargetedKeywords,
    integrations,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    const runwayStatus = getRunwayStatus(contentRunway.days_ahead);
    const hasContent = contentRunway.scheduled_count > 0 || contentRunway.backlog_count > 0 || contentRunway.published_count > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {project.name}
                            </h1>
                            {project.website_url && (
                                <a
                                    href={project.website_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                            )}
                        </div>
                        {project.description && (
                            <p className="max-w-2xl text-sm text-muted-foreground line-clamp-2">
                                {project.description}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/projects/${project.id}/settings`}>
                                <Settings className="mr-1.5 h-4 w-4" />
                                Settings
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm">
                        <Link href={`/projects/${project.id}/planner`}>
                            <CalendarDays className="mr-1.5 h-4 w-4" />
                            Content Planner
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/projects/${project.id}/keywords`}>
                            <Key className="mr-1.5 h-4 w-4" />
                            Keywords
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/projects/${project.id}/articles`}>
                            <FileText className="mr-1.5 h-4 w-4" />
                            Articles
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/projects/${project.id}/integrations`}>
                            <Link2 className="mr-1.5 h-4 w-4" />
                            Integrations
                        </Link>
                    </Button>
                </div>

                {/* Content Runway */}
                <Card className={cn('border-2', runwayStatus.bgColor)}>
                    <CardContent className="p-6">
                        <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                            {/* Left: Days ahead */}
                            <div className="flex items-center gap-4">
                                <div className={cn('rounded-full p-3', runwayStatus.bgColor)}>
                                    <CalendarCheck className={cn('h-8 w-8', runwayStatus.color)} />
                                </div>
                                <div>
                                    <div className="flex items-baseline gap-2">
                                        <span className={cn('text-4xl font-bold', runwayStatus.color)}>
                                            {contentRunway.days_ahead}
                                        </span>
                                        <span className="text-lg text-muted-foreground">
                                            {contentRunway.days_ahead === 1 ? 'day' : 'days'}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        of content scheduled
                                    </p>
                                    <p className={cn('text-sm font-medium', runwayStatus.color)}>
                                        {runwayStatus.label}
                                    </p>
                                </div>
                            </div>

                            {/* Right: Details */}
                            <div className="flex flex-col gap-3 sm:items-end sm:text-right">
                                {contentRunway.next_scheduled ? (
                                    <>
                                        <div>
                                            <p className="text-xs text-muted-foreground uppercase tracking-wide">Next up</p>
                                            <p className="font-medium">{contentRunway.next_scheduled.title}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatScheduledDate(contentRunway.next_scheduled.scheduled_date)}
                                                {contentRunway.next_scheduled.scheduled_time && (
                                                    <span> at {contentRunway.next_scheduled.scheduled_time}</span>
                                                )}
                                            </p>
                                        </div>
                                        {contentRunway.last_scheduled_date && contentRunway.days_ahead > 0 && (
                                            <div>
                                                <p className="text-xs text-muted-foreground uppercase tracking-wide">Covered until</p>
                                                <p className="text-sm font-medium">
                                                    {formatFullDate(contentRunway.last_scheduled_date)}
                                                </p>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <div>
                                        <p className="text-sm text-muted-foreground mb-2">
                                            Schedule content to see your runway
                                        </p>
                                        <Button asChild size="sm">
                                            <Link href={`/projects/${project.id}/planner`}>
                                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                                Plan Content
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Bottom stats */}
                        {hasContent && (
                            <div className="mt-6 flex flex-wrap gap-4 border-t pt-4">
                                <div className="flex items-center gap-2 text-sm">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">{contentRunway.scheduled_count}</span>
                                    <span className="text-muted-foreground">scheduled</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <Inbox className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">{contentRunway.backlog_count}</span>
                                    <span className="text-muted-foreground">in backlog</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">{contentRunway.published_count}</span>
                                    <span className="text-muted-foreground">published</span>
                                </div>
                                {contentRunway.failed_count > 0 && (
                                    <div className="flex items-center gap-2 text-sm text-destructive">
                                        <XCircle className="h-4 w-4" />
                                        <span className="font-medium">{contentRunway.failed_count}</span>
                                        <span>failed</span>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Alerts Row */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Needs Attention */}
                    {needsAttention.length > 0 && (
                        <Card className="border-destructive/50">
                            <CardHeader className="pb-3">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="h-4 w-4 text-destructive" />
                                    <CardTitle className="text-base text-destructive">Needs Attention</CardTitle>
                                </div>
                                <CardDescription>
                                    Failed generations or overdue content
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {needsAttention.map((content) => (
                                        <div
                                            key={content.id}
                                            className="flex items-center justify-between rounded-md border border-destructive/20 bg-destructive/5 p-2"
                                        >
                                            <div className="flex-1 truncate">
                                                <span className="text-sm font-medium">
                                                    {content.title || content.keyword?.keyword || 'Untitled'}
                                                </span>
                                                {content.error_message && (
                                                    <p className="text-xs text-destructive truncate">
                                                        {content.error_message}
                                                    </p>
                                                )}
                                            </div>
                                            <Badge variant="destructive" className="ml-2 shrink-0">
                                                {content.status === 'failed' ? 'Failed' : 'Overdue'}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                                <Button asChild variant="outline" size="sm" className="mt-3 w-full">
                                    <Link href={`/projects/${project.id}/planner`}>
                                        View in Planner
                                        <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {/* Currently Generating */}
                    {generating.length > 0 && (
                        <Card className="border-yellow-500/50">
                            <CardHeader className="pb-3">
                                <div className="flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin text-yellow-600" />
                                    <CardTitle className="text-base">Generating Now</CardTitle>
                                </div>
                                <CardDescription>
                                    Content currently being generated by AI
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {generating.map((content) => (
                                        <div
                                            key={content.id}
                                            className="flex items-center gap-3 rounded-md border border-yellow-500/20 bg-yellow-500/5 p-2"
                                        >
                                            <div className="flex-1">
                                                <span className="text-sm font-medium">
                                                    {content.title || content.keyword?.keyword || 'Untitled'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Sparkles className="h-3 w-3 text-yellow-600" />
                                                Generating...
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Upcoming Content */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base">Upcoming Content</CardTitle>
                                <CardDescription>Scheduled for the next 7 days</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={`/projects/${project.id}/planner`}>
                                    View all
                                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {upcomingContent.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <CalendarDays className="mb-2 h-8 w-8 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        No content scheduled for the next 7 days
                                    </p>
                                    <Button asChild size="sm" className="mt-3">
                                        <Link href={`/projects/${project.id}/planner`}>
                                            <Plus className="mr-1.5 h-3.5 w-3.5" />
                                            Plan Content
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {upcomingContent.map((content) => {
                                        const config = statusConfig[content.status];
                                        const Icon = config?.icon || Calendar;
                                        return (
                                            <div
                                                key={content.id}
                                                className="flex items-center gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted/50"
                                            >
                                                <div className={cn('rounded-full p-1.5', config?.color || 'bg-muted')}>
                                                    <Icon className="h-3 w-3 text-white" />
                                                </div>
                                                <div className="flex-1 truncate">
                                                    <span className="text-sm font-medium">
                                                        {content.title || content.keyword?.keyword || 'Untitled'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Clock className="h-3 w-3" />
                                                    {formatScheduledDate(content.scheduled_date)}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Articles */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base">Recent Articles</CardTitle>
                                <CardDescription>Latest generated content</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={`/projects/${project.id}/articles`}>
                                    View all
                                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentArticles.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <FileText className="mb-2 h-8 w-8 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        No articles yet
                                    </p>
                                    <Button asChild size="sm" className="mt-3">
                                        <Link href={`/projects/${project.id}/planner`}>
                                            <Sparkles className="mr-1.5 h-3.5 w-3.5" />
                                            Generate Content
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {recentArticles.map((article) => (
                                        <Link
                                            key={article.id}
                                            href={`/projects/${project.id}/articles/${article.id}`}
                                            className="flex items-center gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted/50"
                                        >
                                            <FileText className="h-4 w-4 text-muted-foreground" />
                                            <div className="flex-1 truncate">
                                                <span className="text-sm font-medium">
                                                    {article.title}
                                                </span>
                                                {article.keyword && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {article.keyword.keyword}
                                                    </span>
                                                )}
                                            </div>
                                            <Badge variant="outline" className="shrink-0 text-xs">
                                                {article.status}
                                            </Badge>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Bottom Row */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Stats Cards */}
                    <Link href={`/projects/${project.id}/keywords`}>
                        <Card className="group transition-all hover:border-primary/50 hover:shadow-sm">
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="rounded-lg bg-muted p-2">
                                    <Key className="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold">{project.keywords_count}</div>
                                    <div className="text-sm text-muted-foreground">Keywords</div>
                                </div>
                                <ArrowRight className="ml-auto h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href={`/projects/${project.id}/articles`}>
                        <Card className="group transition-all hover:border-primary/50 hover:shadow-sm">
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="rounded-lg bg-muted p-2">
                                    <FileText className="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold">{project.articles_count}</div>
                                    <div className="text-sm text-muted-foreground">Articles</div>
                                </div>
                                <ArrowRight className="ml-auto h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href={`/projects/${project.id}/integrations`}>
                        <Card className="group transition-all hover:border-primary/50 hover:shadow-sm">
                            <CardContent className="flex items-center gap-4 p-4">
                                <div className="rounded-lg bg-muted p-2">
                                    <Link2 className="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold">{project.integrations_count}</div>
                                    <div className="text-sm text-muted-foreground">Integrations</div>
                                </div>
                                <ArrowRight className="ml-auto h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                {/* Untargeted Keywords */}
                {untargetedKeywords.length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base">Ready to Write</CardTitle>
                                <CardDescription>Keywords without articles yet</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={`/projects/${project.id}/keywords`}>
                                    View all
                                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {untargetedKeywords.map((keyword) => (
                                    <Link
                                        key={keyword.id}
                                        href={`/projects/${project.id}/keywords/${keyword.id}`}
                                    >
                                        <Badge
                                            variant="secondary"
                                            className="cursor-pointer transition-colors hover:bg-primary hover:text-primary-foreground"
                                        >
                                            {keyword.keyword}
                                        </Badge>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Getting Started (shown when no content and no keywords) */}
                {!hasContent && project.keywords_count === 0 && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="mb-4 rounded-full bg-primary/10 p-4">
                                <Sparkles className="h-8 w-8 text-primary" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Get Started</h3>
                            <p className="mb-6 max-w-md text-sm text-muted-foreground">
                                Start by adding keywords you want to target, then use the Content Planner
                                to schedule and generate SEO-optimized articles automatically.
                            </p>
                            <div className="flex gap-3">
                                <Button asChild>
                                    <Link href={`/projects/${project.id}/keywords/create`}>
                                        <Plus className="mr-1.5 h-4 w-4" />
                                        Add Keywords
                                    </Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={`/projects/${project.id}/planner`}>
                                        <CalendarDays className="mr-1.5 h-4 w-4" />
                                        Open Planner
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
