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
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { format, isToday, isTomorrow } from 'date-fns';
import {
    AlertCircle,
    Calendar,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
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
}

const statusConfig: Record<
    string,
    {
        label: string;
        color: string;
        icon: React.ComponentType<{ className?: string }>;
    }
> = {
    backlog: { label: 'Backlog', color: 'bg-slate-500', icon: Inbox },
    scheduled: { label: 'Scheduled', color: 'bg-blue-500', icon: Calendar },
    generating: { label: 'Generating', color: 'bg-yellow-500', icon: Sparkles },
    published: {
        label: 'Published',
        color: 'bg-emerald-500',
        icon: CheckCircle2,
    },
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

function getRunwayStatus(days: number): {
    color: string;
    bgColor: string;
    borderColor: string;
    iconBg: string;
    label: string;
} {
    if (days >= 14) {
        return {
            color: 'text-emerald-700 dark:text-emerald-400',
            bgColor:
                'bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-950/40 dark:to-emerald-900/20',
            borderColor: 'border-emerald-200 dark:border-emerald-800/50',
            iconBg: 'bg-emerald-100 dark:bg-emerald-900/50',
            label: "You're covered",
        };
    } else if (days >= 7) {
        return {
            color: 'text-amber-700 dark:text-amber-400',
            bgColor:
                'bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-950/40 dark:to-amber-900/20',
            borderColor: 'border-amber-200 dark:border-amber-800/50',
            iconBg: 'bg-amber-100 dark:bg-amber-900/50',
            label: 'Plan ahead soon',
        };
    } else if (days > 0) {
        return {
            color: 'text-orange-700 dark:text-orange-400',
            bgColor:
                'bg-gradient-to-br from-orange-50 to-orange-100/50 dark:from-orange-950/40 dark:to-orange-900/20',
            borderColor: 'border-orange-200 dark:border-orange-800/50',
            iconBg: 'bg-orange-100 dark:bg-orange-900/50',
            label: 'Running low',
        };
    }
    return {
        color: 'text-slate-600 dark:text-slate-400',
        bgColor:
            'bg-gradient-to-br from-slate-50 to-slate-100/50 dark:from-slate-900/40 dark:to-slate-800/20',
        borderColor: 'border-slate-200 dark:border-slate-700/50',
        iconBg: 'bg-slate-100 dark:bg-slate-800/50',
        label: 'No content scheduled',
    };
}

export default function Show({
    project,
    contentRunway,
    needsAttention,
    generating,
    upcomingContent,
    recentArticles,
    untargetedKeywords,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    const runwayStatus = getRunwayStatus(contentRunway.days_ahead);
    const hasContent =
        contentRunway.scheduled_count > 0 ||
        contentRunway.backlog_count > 0 ||
        contentRunway.published_count > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {project.name}
                            </h1>
                            {project.website_url && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <a
                                            href={project.website_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center justify-center rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            <ExternalLink className="h-4 w-4" />
                                        </a>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Visit website</p>
                                    </TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                        {project.description && (
                            <p className="line-clamp-2 max-w-2xl text-sm text-muted-foreground">
                                {project.description}
                            </p>
                        )}
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                    >
                        <Link href={`/projects/${project.id}/settings`}>
                            <Settings className="mr-2 h-4 w-4" />
                            Settings
                        </Link>
                    </Button>
                </div>

                {/* Quick Actions */}
                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" className="shadow-sm">
                        <Link href={`/projects/${project.id}/planner`}>
                            <CalendarDays className="mr-2 h-4 w-4" />
                            Content Planner
                        </Link>
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/projects/${project.id}/keywords`}>
                                <Key className="mr-2 h-4 w-4" />
                                Keywords
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/projects/${project.id}/articles`}>
                                <FileText className="mr-2 h-4 w-4" />
                                Articles
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/projects/${project.id}/integrations`}>
                                <Link2 className="mr-2 h-4 w-4" />
                                Integrations
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Content Runway */}
                <Card
                    className={cn(
                        'overflow-hidden border py-0',
                        runwayStatus.borderColor,
                        runwayStatus.bgColor,
                    )}
                >
                    <CardContent className="p-0">
                        <div className="flex flex-col lg:flex-row lg:items-stretch">
                            {/* Left: Days ahead */}
                            <div className="flex items-center gap-5 p-6 lg:p-8">
                                <div
                                    className={cn(
                                        'flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl',
                                        runwayStatus.iconBg,
                                    )}
                                >
                                    <CalendarCheck
                                        className={cn(
                                            'h-8 w-8',
                                            runwayStatus.color,
                                        )}
                                    />
                                </div>
                                <div>
                                    <div className="flex items-baseline gap-1.5">
                                        <span
                                            className={cn(
                                                'text-5xl font-bold tracking-tight',
                                                runwayStatus.color,
                                            )}
                                        >
                                            {contentRunway.days_ahead}
                                        </span>
                                        <span className="text-xl font-medium text-muted-foreground">
                                            {contentRunway.days_ahead === 1
                                                ? 'day'
                                                : 'days'}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        of content scheduled
                                    </p>
                                    <Badge
                                        variant="secondary"
                                        className={cn(
                                            'mt-2 font-medium',
                                            runwayStatus.color,
                                            runwayStatus.iconBg,
                                        )}
                                    >
                                        {runwayStatus.label}
                                    </Badge>
                                </div>
                            </div>

                            {/* Divider */}
                            <div className="hidden lg:block lg:w-px lg:self-stretch lg:bg-border/50" />

                            {/* Right: Details */}
                            <div className="flex flex-1 flex-col justify-center gap-4 border-t p-6 lg:border-t-0 lg:p-8">
                                {contentRunway.next_scheduled ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Next up
                                            </p>
                                            <p className="leading-snug font-medium">
                                                {
                                                    contentRunway.next_scheduled
                                                        .title
                                                }
                                            </p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                                                <Clock className="h-3.5 w-3.5" />
                                                {formatScheduledDate(
                                                    contentRunway.next_scheduled
                                                        .scheduled_date,
                                                )}
                                                {contentRunway.next_scheduled
                                                    .scheduled_time && (
                                                    <span>
                                                        at{' '}
                                                        {
                                                            contentRunway
                                                                .next_scheduled
                                                                .scheduled_time
                                                        }
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        {contentRunway.last_scheduled_date &&
                                            contentRunway.days_ahead > 0 && (
                                                <div>
                                                    <p className="mb-1 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                        Covered until
                                                    </p>
                                                    <p className="font-medium">
                                                        {formatFullDate(
                                                            contentRunway.last_scheduled_date,
                                                        )}
                                                    </p>
                                                </div>
                                            )}
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-start gap-3">
                                        <p className="text-sm text-muted-foreground">
                                            Schedule content to see your runway
                                        </p>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="shadow-sm"
                                        >
                                            <Link
                                                href={`/projects/${project.id}/planner`}
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                Plan Content
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Bottom stats */}
                        {hasContent && (
                            <div className="flex flex-wrap gap-x-6 gap-y-2 border-t border-border/50 bg-background/40 px-6 py-4 lg:px-8">
                                <div className="flex items-center gap-2 text-sm">
                                    <Calendar className="h-4 w-4 text-blue-500" />
                                    <span className="font-semibold tabular-nums">
                                        {contentRunway.scheduled_count}
                                    </span>
                                    <span className="text-muted-foreground">
                                        scheduled
                                    </span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <Inbox className="h-4 w-4 text-slate-500" />
                                    <span className="font-semibold tabular-nums">
                                        {contentRunway.backlog_count}
                                    </span>
                                    <span className="text-muted-foreground">
                                        in backlog
                                    </span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                    <span className="font-semibold tabular-nums">
                                        {contentRunway.published_count}
                                    </span>
                                    <span className="text-muted-foreground">
                                        published
                                    </span>
                                </div>
                                {contentRunway.failed_count > 0 && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <XCircle className="h-4 w-4 text-red-500" />
                                        <span className="font-semibold text-red-600 tabular-nums dark:text-red-400">
                                            {contentRunway.failed_count}
                                        </span>
                                        <span className="text-red-600 dark:text-red-400">
                                            failed
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Alerts Row */}
                {(needsAttention.length > 0 || generating.length > 0) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {/* Needs Attention */}
                        {needsAttention.length > 0 && (
                            <Card className="overflow-hidden border-red-200 bg-gradient-to-br from-red-50 to-red-100/30 dark:border-red-900/50 dark:from-red-950/30 dark:to-red-900/10">
                                <CardHeader className="pb-3">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/50">
                                            <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base text-red-700 dark:text-red-400">
                                                Needs Attention
                                            </CardTitle>
                                            <CardDescription className="text-red-600/70 dark:text-red-400/70">
                                                {needsAttention.length} item
                                                {needsAttention.length !== 1
                                                    ? 's'
                                                    : ''}{' '}
                                                require action
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    <div className="space-y-2">
                                        {needsAttention.map((content) => (
                                            <div
                                                key={content.id}
                                                className="flex items-center justify-between gap-3 rounded-lg border border-red-200/50 bg-white/60 p-3 backdrop-blur-sm dark:border-red-900/30 dark:bg-red-950/20"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {content.title ||
                                                            content.keyword
                                                                ?.keyword ||
                                                            'Untitled'}
                                                    </span>
                                                    {content.error_message && (
                                                        <p className="mt-0.5 truncate text-xs text-red-600 dark:text-red-400">
                                                            {
                                                                content.error_message
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <Badge
                                                    variant="destructive"
                                                    className="shrink-0 bg-red-600 text-white"
                                                >
                                                    {content.status === 'failed'
                                                        ? 'Failed'
                                                        : 'Overdue'}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="mt-4 w-full border-red-200 bg-white/80 hover:bg-white dark:border-red-900/50 dark:bg-red-950/30 dark:hover:bg-red-950/50"
                                    >
                                        <Link
                                            href={`/projects/${project.id}/planner`}
                                        >
                                            View in Planner
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {/* Currently Generating */}
                        {generating.length > 0 && (
                            <Card className="overflow-hidden border-amber-200 bg-gradient-to-br from-amber-50 to-amber-100/30 dark:border-amber-900/50 dark:from-amber-950/30 dark:to-amber-900/10">
                                <CardHeader className="pb-3">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/50">
                                            <Loader2 className="h-4 w-4 animate-spin text-amber-600 dark:text-amber-400" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base text-amber-700 dark:text-amber-400">
                                                Generating Now
                                            </CardTitle>
                                            <CardDescription className="text-amber-600/70 dark:text-amber-400/70">
                                                AI is creating your content
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    <div className="space-y-2">
                                        {generating.map((content) => (
                                            <div
                                                key={content.id}
                                                className="flex items-center gap-3 rounded-lg border border-amber-200/50 bg-white/60 p-3 backdrop-blur-sm dark:border-amber-900/30 dark:bg-amber-950/20"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {content.title ||
                                                            content.keyword
                                                                ?.keyword ||
                                                            'Untitled'}
                                                    </span>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                                    <Sparkles className="h-3.5 w-3.5" />
                                                    Generating...
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Upcoming Content */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between border-b pb-4">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    Upcoming Content
                                </CardTitle>
                                <CardDescription>
                                    Scheduled for the next 7 days
                                </CardDescription>
                            </div>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground"
                            >
                                <Link href={`/projects/${project.id}/planner`}>
                                    View all
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {upcomingContent.length === 0 ? (
                                <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
                                    <div className="mb-3 rounded-full bg-muted p-3">
                                        <CalendarDays className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <p className="mb-1 font-medium">
                                        No upcoming content
                                    </p>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Schedule content to see it here
                                    </p>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="shadow-sm"
                                    >
                                        <Link
                                            href={`/projects/${project.id}/planner`}
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Plan Content
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {upcomingContent.map((content) => {
                                        const config =
                                            statusConfig[content.status];
                                        const Icon = config?.icon || Calendar;
                                        return (
                                            <div
                                                key={content.id}
                                                className="flex items-center gap-4 px-6 py-3 transition-colors hover:bg-muted/30"
                                            >
                                                <div
                                                    className={cn(
                                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                                        config?.color ||
                                                            'bg-muted',
                                                    )}
                                                >
                                                    <Icon className="h-4 w-4 text-white" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {content.title ||
                                                            content.keyword
                                                                ?.keyword ||
                                                            'Untitled'}
                                                    </span>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1.5 rounded-md bg-muted/50 px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    {formatScheduledDate(
                                                        content.scheduled_date,
                                                    )}
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
                        <CardHeader className="flex flex-row items-center justify-between border-b pb-4">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    Recent Articles
                                </CardTitle>
                                <CardDescription>
                                    Latest generated content
                                </CardDescription>
                            </div>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground"
                            >
                                <Link href={`/projects/${project.id}/articles`}>
                                    View all
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentArticles.length === 0 ? (
                                <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
                                    <div className="mb-3 rounded-full bg-muted p-3">
                                        <FileText className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <p className="mb-1 font-medium">
                                        No articles yet
                                    </p>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Generate your first article
                                    </p>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="shadow-sm"
                                    >
                                        <Link
                                            href={`/projects/${project.id}/planner`}
                                        >
                                            <Sparkles className="mr-2 h-4 w-4" />
                                            Generate Content
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {recentArticles.map((article) => (
                                        <Link
                                            key={article.id}
                                            href={`/projects/${project.id}/articles/${article.id}`}
                                            className="flex items-center gap-4 px-6 py-3 transition-colors hover:bg-muted/30"
                                        >
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                <FileText className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">
                                                    {article.title}
                                                </span>
                                                {article.keyword && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {
                                                            article.keyword
                                                                .keyword
                                                        }
                                                    </span>
                                                )}
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0 text-xs capitalize"
                                            >
                                                {article.status}
                                            </Badge>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Bottom Row - Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Link href={`/projects/${project.id}/keywords`}>
                        <Card className="group relative overflow-hidden border transition-all hover:border-primary/50">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                                    <Key className="h-6 w-6 text-violet-600 dark:text-violet-400" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-3xl font-bold tracking-tight tabular-nums">
                                        {project.keywords_count}
                                    </div>
                                    <div className="text-sm font-medium text-muted-foreground">
                                        Keywords
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href={`/projects/${project.id}/articles`}>
                        <Card className="group relative overflow-hidden border transition-all hover:border-primary/50">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/30">
                                    <FileText className="h-6 w-6 text-sky-600 dark:text-sky-400" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-3xl font-bold tracking-tight tabular-nums">
                                        {project.articles_count}
                                    </div>
                                    <div className="text-sm font-medium text-muted-foreground">
                                        Articles
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href={`/projects/${project.id}/integrations`}>
                        <Card className="group relative overflow-hidden border transition-all hover:border-primary/50">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/30">
                                    <Link2 className="h-6 w-6 text-teal-600 dark:text-teal-400" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-3xl font-bold tracking-tight tabular-nums">
                                        {project.integrations_count}
                                    </div>
                                    <div className="text-sm font-medium text-muted-foreground">
                                        Integrations
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                {/* Untargeted Keywords */}
                {untargetedKeywords.length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between border-b pb-4">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    Ready to Write
                                </CardTitle>
                                <CardDescription>
                                    {untargetedKeywords.length} keyword
                                    {untargetedKeywords.length !== 1
                                        ? 's'
                                        : ''}{' '}
                                    without articles
                                </CardDescription>
                            </div>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground"
                            >
                                <Link href={`/projects/${project.id}/keywords`}>
                                    View all
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="pt-5">
                            <div className="flex flex-wrap gap-2">
                                {untargetedKeywords.map((keyword) => (
                                    <Link
                                        key={keyword.id}
                                        href={`/projects/${project.id}/keywords/${keyword.id}`}
                                    >
                                        <Badge
                                            variant="secondary"
                                            className="cursor-pointer px-3 py-1.5 text-sm font-medium transition-all hover:bg-primary hover:text-primary-foreground hover:shadow-sm"
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
                    <Card className="border-2 border-dashed bg-gradient-to-br from-muted/30 to-muted/10">
                        <CardContent className="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <div className="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
                                <Sparkles className="h-8 w-8 text-primary" />
                            </div>
                            <h3 className="mb-2 text-xl font-semibold">
                                Get Started
                            </h3>
                            <p className="mb-8 max-w-md text-muted-foreground">
                                Start by adding keywords you want to target,
                                then use the Content Planner to schedule and
                                generate SEO-optimized articles automatically.
                            </p>
                            <div className="flex flex-wrap justify-center gap-3">
                                <Button asChild size="lg" className="shadow-sm">
                                    <Link
                                        href={`/projects/${project.id}/keywords/create`}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Keywords
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" size="lg">
                                    <Link
                                        href={`/projects/${project.id}/planner`}
                                    >
                                        <CalendarDays className="mr-2 h-4 w-4" />
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
