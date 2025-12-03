import {
    edit as editArticle,
    show as showArticle,
} from '@/actions/App/Http/Controllers/ArticleController';
import {
    autoSchedule,
    createKeyword,
    destroy,
    generate,
    index,
    publish,
    schedule,
    store,
    unschedule,
    update,
} from '@/actions/App/Http/Controllers/ContentPlannerController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { axios } from '@/lib/axios';
import {
    getToneLabel,
    getWordCountLabel,
    toneOptions,
    wordCountOptions,
} from '@/lib/content-options';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    addDays,
    addMonths,
    addWeeks,
    format,
    getDay,
    isBefore,
    isSameDay,
    isSameMonth,
    isToday,
    parseISO,
    startOfDay,
    startOfMonth,
    startOfWeek,
    subMonths,
    subWeeks,
} from 'date-fns';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    CalendarPlus,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Circle,
    Eye,
    FileText,
    GripVertical,
    HelpCircle,
    Inbox,
    Loader2,
    Pencil,
    Plus,
    Send,
    Settings,
    Sparkles,
    Webhook,
    X,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface ContentType {
    value: string;
    label: string;
    description: string;
    icon: string;
    wordCount: number;
}

interface ContentStatusType {
    value: string;
    label: string;
    color: string;
    icon: string;
}

interface Keyword {
    id: number;
    keyword: string;
    volume: string | null;
    difficulty: string | null;
    articles_count?: number;
    scheduled_contents_count?: number;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    status: string;
    word_count: number | null;
    keyword: Keyword | null;
    created_at: string;
}

interface ScheduledContent {
    id: number;
    project_id: number;
    keyword_id: number | null;
    article_id: number | null;
    title: string | null;
    content_type: string;
    status: string;
    scheduled_date: string | null;
    scheduled_time: string | null;
    position: number;
    target_word_count: number | null;
    tone: string | null;
    notes: string | null;
    keyword?: Keyword;
    article?: Article;
}

interface Project {
    id: number;
    name: string;
    default_word_count: number;
    default_tone: string;
}

interface Stats {
    total: number;
    backlog: number;
    scheduled: number;
    generating: number;
    in_review: number;
    approved: number;
    published: number;
    failed: number;
    overdue: number;
    due_today: number;
}

interface Integration {
    id: number;
    type: string;
    name: string;
}

interface Props {
    project: Project;
    scheduledContents: ScheduledContent[];
    backlog: ScheduledContent[];
    allKeywords: Keyword[];
    unscheduledArticles: Article[];
    stats: Stats;
    activeIntegrations: Integration[];
    view: 'month' | 'week' | 'day';
    currentDate: string;
    startDate: string;
    endDate: string;
    contentTypes: ContentType[];
    contentStatuses: ContentStatusType[];
}

const statusColors: Record<string, string> = {
    backlog: 'bg-slate-100 border-slate-300 text-slate-700',
    scheduled: 'bg-blue-50 border-blue-300 text-blue-700',
    queued: 'bg-amber-50 border-amber-300 text-amber-700',
    generating: 'bg-yellow-50 border-yellow-300 text-yellow-700',
    enriching: 'bg-purple-50 border-purple-300 text-purple-700',
    in_review: 'bg-orange-50 border-orange-300 text-orange-700',
    approved: 'bg-green-50 border-green-300 text-green-700',
    publishing_queued: 'bg-blue-50 border-blue-300 text-blue-700',
    published: 'bg-emerald-50 border-emerald-300 text-emerald-700',
    failed: 'bg-red-50 border-red-300 text-red-700',
};

const difficultyConfig: Record<
    string,
    { level: number; color: string; label: string }
> = {
    low: { level: 1, color: 'bg-green-500', label: 'Low' },
    medium: { level: 2, color: 'bg-yellow-500', label: 'Medium' },
    high: { level: 3, color: 'bg-red-500', label: 'High' },
};

const volumeConfig: Record<
    string,
    { level: number; color: string; label: string }
> = {
    low: { level: 1, color: 'bg-slate-400', label: 'Low' },
    medium: { level: 2, color: 'bg-slate-500', label: 'Medium' },
    high: { level: 3, color: 'bg-slate-600', label: 'High' },
};

function MiniProgressBar({
    level,
    maxLevel = 3,
    color,
}: {
    level: number;
    maxLevel?: number;
    color: string;
}) {
    return (
        <div className="flex gap-0.5">
            {Array.from({ length: maxLevel }).map((_, i) => (
                <div
                    key={i}
                    className={cn(
                        'h-1.5 w-1.5 rounded-sm',
                        i < level ? color : 'bg-muted',
                    )}
                />
            ))}
        </div>
    );
}

const contentTypeColors: Record<string, string> = {
    blog_post: 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-300',
    listicle: 'bg-violet-100 text-violet-700 border-violet-300',
    how_to: 'bg-blue-100 text-blue-700 border-blue-300',
    comparison: 'bg-amber-100 text-amber-700 border-amber-300',
    case_study: 'bg-cyan-100 text-cyan-700 border-cyan-300',
    review: 'bg-yellow-100 text-yellow-700 border-yellow-300',
    news_article: 'bg-rose-100 text-rose-700 border-rose-300',
    pillar_content: 'bg-emerald-100 text-emerald-700 border-emerald-300',
};

export default function ContentPlannerIndex({
    project,
    scheduledContents,
    backlog,
    allKeywords,
    unscheduledArticles,
    stats,
    activeIntegrations,
    view,
    currentDate,
    contentTypes,
}: Props) {
    const [isAddDialogOpen, setIsAddDialogOpen] = React.useState(false);
    const [addDialogTab, setAddDialogTab] = React.useState<'new' | 'existing'>(
        'new',
    );
    const [selectedDate, setSelectedDate] = React.useState<Date | null>(null);
    const [draggedItem, setDraggedItem] =
        React.useState<ScheduledContent | null>(null);
    const [editingContent, setEditingContent] =
        React.useState<ScheduledContent | null>(null);
    const [dayViewDate, setDayViewDate] = React.useState<Date | null>(null);
    const [showCreateKeyword, setShowCreateKeyword] = React.useState(false);
    const [newKeywordText, setNewKeywordText] = React.useState('');
    const [creatingKeyword, setCreatingKeyword] = React.useState(false);
    const [localKeywords, setLocalKeywords] =
        React.useState<Keyword[]>(allKeywords);
    const [selectedArticleId, setSelectedArticleId] =
        React.useState<string>('');
    const [editForm, setEditForm] = React.useState({
        title: '',
        content_type: 'blog_post',
        scheduled_date: '',
        target_word_count: '',
        tone: '',
        notes: '',
    });
    const [isAutoScheduling, setIsAutoScheduling] = React.useState(false);

    const current = parseISO(currentDate);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Content Planner', href: `/projects/${project.id}/planner` },
    ];

    // Generate calendar days for month view
    const generateMonthDays = () => {
        const monthStart = startOfMonth(current);
        const calendarStart = startOfWeek(monthStart, { weekStartsOn: 1 });
        const days: Date[] = [];
        let day = calendarStart;
        for (let i = 0; i < 42; i++) {
            days.push(day);
            day = addDays(day, 1);
        }
        return days;
    };

    // Generate week days
    const generateWeekDays = () => {
        const weekStart = startOfWeek(current, { weekStartsOn: 1 });
        const days: Date[] = [];
        for (let i = 0; i < 7; i++) {
            days.push(addDays(weekStart, i));
        }
        return days;
    };

    const days = view === 'week' ? generateWeekDays() : generateMonthDays();

    // Get content for a specific day
    const getContentForDay = (date: Date) => {
        return scheduledContents.filter((content) => {
            if (!content.scheduled_date) return false;
            return isSameDay(parseISO(content.scheduled_date), date);
        });
    };

    // Check if content is overdue (scheduled date is in the past and not published)
    const isOverdue = (content: ScheduledContent) => {
        if (!content.scheduled_date) return false;
        if (content.status === 'published') return false;
        const scheduledDate = parseISO(content.scheduled_date);
        return isBefore(scheduledDate, startOfDay(new Date()));
    };

    // Check if content is due today
    const isDueToday = (content: ScheduledContent) => {
        if (!content.scheduled_date) return false;
        if (content.status === 'published') return false;
        return isToday(parseISO(content.scheduled_date));
    };

    // Check if content can be generated (has keyword, no article yet, not already queued/generating)
    const canGenerate = (content: ScheduledContent) => {
        return (
            content.keyword_id !== null &&
            content.article_id === null &&
            content.status !== 'queued' &&
            content.status !== 'generating' &&
            content.status !== 'published'
        );
    };

    // Check if content is currently being generated, queued, enriching, or publishing
    const isProcessing = (content: ScheduledContent) => {
        return (
            content.status === 'generating' ||
            content.status === 'queued' ||
            content.status === 'enriching' ||
            content.status === 'publishing_queued'
        );
    };

    // Get the appropriate label for the processing state
    const getProcessingLabel = (content: ScheduledContent) => {
        switch (content.status) {
            case 'queued':
                return 'Queued...';
            case 'generating':
                return 'Generating...';
            case 'enriching':
                return 'Enriching...';
            case 'publishing_queued':
                return 'Publishing...';
            default:
                return 'Processing...';
        }
    };

    // Handle generate content
    const handleGenerate = async (content: ScheduledContent) => {
        try {
            await axios.post(
                generate.url({ project: project.id, content: content.id }),
            );
            router.reload({ only: ['scheduledContents', 'backlog', 'stats'] });
        } catch (err: unknown) {
            console.error('Error generating content:', err);
            const error = err as { response?: { data?: { redirect?: string } } };
            if (error.response?.data?.redirect) {
                router.visit(error.response.data.redirect);
            }
        }
    };

    // Check if content can be published (has article, not already published or processing)
    const canPublish = (content: ScheduledContent) => {
        return (
            content.article_id !== null &&
            content.status !== 'published' &&
            content.status !== 'generating' &&
            content.status !== 'queued' &&
            content.status !== 'enriching' &&
            content.status !== 'publishing_queued'
        );
    };

    // Handle publish content
    const handlePublish = async (content: ScheduledContent) => {
        try {
            const response = await axios.post(
                publish.url({ project: project.id, content: content.id }),
            );
            if (response.data?.message) {
                toast.success(response.data.message);
            }
            router.reload({ only: ['scheduledContents', 'backlog', 'stats'] });
        } catch (err: unknown) {
            console.error('Error publishing content:', err);
            const error = err as { response?: { data?: { redirect?: string; error?: string } } };
            if (error.response?.data?.redirect) {
                toast.error(error.response?.data?.error || 'Failed to publish');
                router.visit(error.response.data.redirect);
            } else {
                toast.error(
                    error.response?.data?.error || 'Failed to publish article',
                );
            }
        }
    };

    // Navigation
    const navigatePrevious = () => {
        const newDate =
            view === 'week' ? subWeeks(current, 1) : subMonths(current, 1);
        router.get(
            index.url({ project: project.id }),
            { view, date: format(newDate, 'yyyy-MM-dd') },
            { preserveState: true },
        );
    };

    const navigateNext = () => {
        const newDate =
            view === 'week' ? addWeeks(current, 1) : addMonths(current, 1);
        router.get(
            index.url({ project: project.id }),
            { view, date: format(newDate, 'yyyy-MM-dd') },
            { preserveState: true },
        );
    };

    const navigateToday = () => {
        router.get(
            index.url({ project: project.id }),
            { view, date: format(new Date(), 'yyyy-MM-dd') },
            { preserveState: true },
        );
    };

    const changeView = (newView: string) => {
        router.get(
            index.url({ project: project.id }),
            { view: newView, date: currentDate },
            { preserveState: true },
        );
    };

    // Drag and Drop handlers - use refs for instant visual feedback
    const draggedOverRef = React.useRef<HTMLElement | null>(null);

    const handleDragStart = (e: React.DragEvent, content: ScheduledContent) => {
        setDraggedItem(content);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', content.id.toString());
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        const target = e.currentTarget as HTMLElement;
        // Clear previous highlight
        if (draggedOverRef.current && draggedOverRef.current !== target) {
            draggedOverRef.current.classList.remove('drag-over');
        }
        // Add highlight to current target
        target.classList.add('drag-over');
        draggedOverRef.current = target;
    };

    const handleDragEnd = () => {
        // Clear any remaining highlight
        if (draggedOverRef.current) {
            draggedOverRef.current.classList.remove('drag-over');
            draggedOverRef.current = null;
        }
        setDraggedItem(null);
    };

    const handleDrop = async (e: React.DragEvent, date: Date) => {
        e.preventDefault();
        // Clear highlight
        const target = e.currentTarget as HTMLElement;
        target.classList.remove('drag-over');
        draggedOverRef.current = null;

        if (!draggedItem) return;

        // Don't allow re-scheduling published content
        if (draggedItem.status === 'published') {
            setDraggedItem(null);
            return;
        }

        try {
            await axios.post(
                schedule.url({ project: project.id, content: draggedItem.id }),
                {
                    scheduled_date: format(date, 'yyyy-MM-dd'),
                },
            );
            router.reload({ only: ['scheduledContents', 'backlog', 'stats'] });
        } catch (error) {
            console.error('Error scheduling content:', error);
        }
        setDraggedItem(null);
    };

    const handleUnschedule = async (content: ScheduledContent) => {
        try {
            await axios.post(
                unschedule.url({ project: project.id, content: content.id }),
            );
            router.reload({ only: ['scheduledContents', 'backlog', 'stats'] });
        } catch (error) {
            console.error('Error unscheduling content:', error);
        }
    };

    const handleDelete = (content: ScheduledContent) => {
        if (
            !confirm('Are you sure you want to remove this from the planner?')
        ) {
            return;
        }
        router.delete(
            destroy.url({ project: project.id, content: content.id }),
            {
                preserveScroll: true,
            },
        );
    };

    // Edit content
    const openEditDialog = (content: ScheduledContent) => {
        setEditingContent(content);
        setEditForm({
            title: content.title || '',
            content_type: content.content_type,
            scheduled_date: content.scheduled_date
                ? format(parseISO(content.scheduled_date), 'yyyy-MM-dd')
                : '',
            target_word_count: content.target_word_count?.toString() || '',
            tone: content.tone || '',
            notes: content.notes || '',
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingContent) return;

        router.put(
            update.url({ project: project.id, content: editingContent.id }),
            {
                ...editForm,
                target_word_count: editForm.target_word_count
                    ? parseInt(editForm.target_word_count)
                    : null,
                scheduled_date: editForm.scheduled_date || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditingContent(null),
            },
        );
    };

    // Add content dialog
    const [newContent, setNewContent] = React.useState({
        keyword_id: '',
        title: '',
        content_type: 'blog_post',
        scheduled_date: '',
        tone: '',
        target_word_count: '',
        notes: '',
    });

    const handleAddContent = (e: React.FormEvent) => {
        e.preventDefault();

        if (addDialogTab === 'existing' && selectedArticleId) {
            // Schedule existing article
            router.post(store.url({ project: project.id }), {
                article_id: parseInt(selectedArticleId),
                scheduled_date: selectedDate
                    ? format(selectedDate, 'yyyy-MM-dd')
                    : newContent.scheduled_date || null,
            });
        } else {
            // Create new content
            router.post(store.url({ project: project.id }), {
                ...newContent,
                keyword_id: newContent.keyword_id || null,
                scheduled_date: selectedDate
                    ? format(selectedDate, 'yyyy-MM-dd')
                    : newContent.scheduled_date || null,
            });
        }

        setIsAddDialogOpen(false);
        setSelectedDate(null);
        setSelectedArticleId('');
        setNewContent({
            keyword_id: '',
            title: '',
            content_type: 'blog_post',
            scheduled_date: '',
            tone: '',
            target_word_count: '',
            notes: '',
        });
    };

    const openAddDialog = (date?: Date) => {
        setSelectedDate(date || null);
        setShowCreateKeyword(false);
        setNewKeywordText('');
        setAddDialogTab('new');
        setSelectedArticleId('');
        setIsAddDialogOpen(true);
    };

    const handleCreateKeyword = async () => {
        if (!newKeywordText.trim()) return;

        setCreatingKeyword(true);
        try {
            const response = await axios.post(
                createKeyword.url({ project: project.id }),
                { keyword: newKeywordText.trim() },
            );

            if (response.data.success) {
                const newKw = response.data.keyword;
                setLocalKeywords([...localKeywords, newKw]);
                setNewContent({
                    ...newContent,
                    keyword_id: newKw.id.toString(),
                });
                setShowCreateKeyword(false);
                setNewKeywordText('');
            }
        } catch (error) {
            console.error('Error creating keyword:', error);
        } finally {
            setCreatingKeyword(false);
        }
    };

    const handleAutoSchedule = async () => {
        setIsAutoScheduling(true);
        try {
            await axios.post(autoSchedule.url({ project: project.id }));
            router.reload({ only: ['scheduledContents', 'backlog', 'stats'] });
        } catch (error) {
            console.error('Error auto-scheduling content:', error);
        } finally {
            setIsAutoScheduling(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Content Planner - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col">
                {/* Header */}
                <div className="flex flex-col gap-4 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Content Planner
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Plan and organize your content calendar. Drag items
                            from the backlog to schedule them.
                        </p>
                    </div>

                    <Button size="sm" onClick={() => openAddDialog()}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Content
                    </Button>
                </div>

                {/* Stats Bar */}
                <div className="flex items-center justify-between border-b px-4 py-2 text-sm">
                    <div className="flex items-center gap-4">
                        <span className="text-muted-foreground">
                            <Inbox className="mr-1 inline h-4 w-4" />
                            Backlog: {stats.backlog}
                        </span>
                        <span className="text-blue-600">
                            <Calendar className="mr-1 inline h-4 w-4" />
                            Scheduled: {stats.scheduled}
                        </span>
                        {stats.overdue > 0 && (
                            <span className="text-red-600">
                                <AlertCircle className="mr-1 inline h-4 w-4" />
                                Overdue: {stats.overdue}
                            </span>
                        )}
                        {stats.due_today > 0 && (
                            <span className="text-orange-600">
                                Due Today: {stats.due_today}
                            </span>
                        )}
                    </div>

                    {/* Publishing Destination Indicator */}
                    <div className="flex items-center gap-2">
                        {activeIntegrations.length === 0 ? (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={`/projects/${project.id}/integrations`}
                                        className="flex items-center gap-1.5 text-amber-600 hover:text-amber-700"
                                    >
                                        <AlertTriangle className="h-4 w-4" />
                                        <span>No publish destination</span>
                                        <Settings className="h-3.5 w-3.5" />
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>
                                        Configure an integration to
                                        automatically publish articles
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={`/projects/${project.id}/integrations`}
                                        className="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <Webhook className="h-4 w-4" />
                                        <span>
                                            Publish to:{' '}
                                            {activeIntegrations[0].name}
                                            {activeIntegrations.length > 1 && (
                                                <span className="ml-1 text-xs">
                                                    +
                                                    {activeIntegrations.length -
                                                        1}{' '}
                                                    more
                                                </span>
                                            )}
                                        </span>
                                        <Settings className="h-3.5 w-3.5" />
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="mb-1 font-medium">
                                        Active integrations:
                                    </p>
                                    <ul className="text-xs">
                                        {activeIntegrations.map(
                                            (integration) => (
                                                <li key={integration.id}>
                                                    • {integration.name} (
                                                    {integration.type})
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                </div>

                {/* Calendar Navigation */}
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={navigatePrevious}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={navigateNext}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={navigateToday}
                        >
                            Today
                        </Button>
                    </div>
                    <h2 className="text-lg font-medium">
                        {view === 'week'
                            ? `Week of ${format(days[0], 'MMM d, yyyy')}`
                            : format(current, 'MMMM yyyy')}
                    </h2>
                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant={
                                        view === 'month' ? 'secondary' : 'ghost'
                                    }
                                    size="sm"
                                    onClick={() => changeView('month')}
                                >
                                    Month
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                View full month calendar
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant={
                                        view === 'week' ? 'secondary' : 'ghost'
                                    }
                                    size="sm"
                                    onClick={() => changeView('week')}
                                >
                                    Week
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                View current week with more detail
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex flex-1 overflow-hidden">
                    {/* Calendar Grid */}
                    <div className="flex-1 overflow-auto">
                        {/* Day Headers */}
                        <div className="grid grid-cols-7 border-b bg-muted/30">
                            {[
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                                'Sun',
                            ].map((day) => (
                                <div
                                    key={day}
                                    className="p-2 text-center text-sm font-medium text-muted-foreground"
                                >
                                    {day}
                                </div>
                            ))}
                        </div>

                        {/* Calendar Days */}
                        <div
                            className={cn(
                                'grid grid-cols-7',
                                view === 'month'
                                    ? 'auto-rows-[160px]'
                                    : 'auto-rows-[400px]',
                                draggedItem && 'is-dragging',
                            )}
                        >
                            {days.map((day, idx) => {
                                const dayContent = getContentForDay(day);
                                const isCurrentMonth = isSameMonth(
                                    day,
                                    current,
                                );
                                const isCurrentDay = isToday(day);
                                const dayOfWeek = getDay(day);
                                const isWeekend =
                                    dayOfWeek === 0 || dayOfWeek === 6;

                                return (
                                    <div
                                        key={idx}
                                        className={cn(
                                            'day-cell border-r border-b p-1',
                                            isWeekend && 'bg-muted/40',
                                            !isCurrentMonth &&
                                                !isWeekend &&
                                                'bg-muted/20',
                                            !isCurrentMonth &&
                                                isWeekend &&
                                                'bg-muted/50',
                                        )}
                                        onDragOver={handleDragOver}
                                        onDragEnter={handleDragEnter}
                                        onDrop={(e) => handleDrop(e, day)}
                                    >
                                        <div className="mb-1 flex items-center justify-between">
                                            <span
                                                className={cn(
                                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                                    isCurrentDay &&
                                                        'bg-primary text-primary-foreground',
                                                    !isCurrentMonth &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {format(day, 'd')}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                {dayContent.length >
                                                    (view === 'month'
                                                        ? 1
                                                        : 3) && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <button
                                                                onClick={() =>
                                                                    setDayViewDate(
                                                                        day,
                                                                    )
                                                                }
                                                                className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary hover:bg-primary/20"
                                                            >
                                                                +
                                                                {dayContent.length -
                                                                    (view ===
                                                                    'month'
                                                                        ? 1
                                                                        : 3)}{' '}
                                                                more
                                                            </button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Click to view all{' '}
                                                            {dayContent.length}{' '}
                                                            items
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                                <button
                                                    onClick={() =>
                                                        openAddDialog(day)
                                                    }
                                                    className="rounded p-0.5 opacity-0 group-hover:opacity-100 hover:bg-muted"
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>

                                        {/* Content items */}
                                        <div className="space-y-1.5 overflow-hidden">
                                            {dayContent
                                                .slice(
                                                    0,
                                                    view === 'month' ? 1 : 3,
                                                )
                                                .map((content) => {
                                                    const hasArticle =
                                                        content.article_id !==
                                                        null;
                                                    const isPublished =
                                                        content.status ===
                                                        'published';
                                                    const contentIsOverdue =
                                                        isOverdue(content);
                                                    const contentIsDueToday =
                                                        isDueToday(content);
                                                    const showCreateButton =
                                                        (contentIsOverdue ||
                                                            contentIsDueToday) &&
                                                        canGenerate(content);
                                                    const showProcessingButton =
                                                        isProcessing(content);

                                                    const contentCard = (
                                                        <div
                                                            key={content.id}
                                                            draggable={
                                                                !isPublished
                                                            }
                                                            onDragStart={(e) =>
                                                                !isPublished &&
                                                                handleDragStart(
                                                                    e,
                                                                    content,
                                                                )
                                                            }
                                                            onDragEnd={
                                                                handleDragEnd
                                                            }
                                                            onDoubleClick={() =>
                                                                openEditDialog(
                                                                    content,
                                                                )
                                                            }
                                                            className={cn(
                                                                'group rounded p-1.5 text-xs',
                                                                isPublished
                                                                    ? 'cursor-default'
                                                                    : 'cursor-move',
                                                                contentIsOverdue
                                                                    ? 'border border-red-400 bg-red-50 text-red-900 dark:border-red-600 dark:bg-red-950/50 dark:text-red-200'
                                                                    : statusColors[
                                                                          content
                                                                              .status
                                                                      ],
                                                                !contentIsOverdue &&
                                                                    (hasArticle ||
                                                                        isPublished)
                                                                    ? 'border'
                                                                    : !contentIsOverdue
                                                                      ? 'border border-dashed'
                                                                      : '',
                                                            )}
                                                        >
                                                            <div className="mb-3 flex items-start justify-between gap-1">
                                                                <div className="flex items-start gap-1">
                                                                    {isPublished ? (
                                                                        <CheckCircle className="mt-0.5 h-3 w-3 shrink-0 text-emerald-600" />
                                                                    ) : hasArticle ? (
                                                                        <FileText className="mt-0.5 h-3 w-3 shrink-0 text-green-600" />
                                                                    ) : (
                                                                        <Circle className="mt-0.5 h-3 w-3 shrink-0 text-muted-foreground" />
                                                                    )}
                                                                    <span className="line-clamp-3 leading-tight font-medium">
                                                                        {content.title ||
                                                                            content
                                                                                .keyword
                                                                                ?.keyword ||
                                                                            'Untitled'}
                                                                    </span>
                                                                </div>
                                                                {/* Hide edit/unschedule buttons for published content */}
                                                                {!isPublished && (
                                                                    <div className="flex shrink-0 items-center opacity-0 group-hover:opacity-100">
                                                                        <Tooltip>
                                                                            <TooltipTrigger
                                                                                asChild
                                                                            >
                                                                                <button
                                                                                    onClick={() =>
                                                                                        openEditDialog(
                                                                                            content,
                                                                                        )
                                                                                    }
                                                                                    className="rounded hover:bg-white/50"
                                                                                >
                                                                                    <Pencil className="h-3 w-3" />
                                                                                </button>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent>
                                                                                Edit
                                                                                content
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                        <Tooltip>
                                                                            <TooltipTrigger
                                                                                asChild
                                                                            >
                                                                                <button
                                                                                    onClick={() =>
                                                                                        handleUnschedule(
                                                                                            content,
                                                                                        )
                                                                                    }
                                                                                    className="rounded hover:bg-white/50"
                                                                                >
                                                                                    <X className="h-3 w-3" />
                                                                                </button>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent>
                                                                                Move
                                                                                to
                                                                                backlog
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="flex items-center gap-1.5">
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <Badge
                                                                            variant="outline"
                                                                            className={cn(
                                                                                'cursor-help px-1 py-0 text-[10px]',
                                                                                contentTypeColors[
                                                                                    content
                                                                                        .content_type
                                                                                ],
                                                                            )}
                                                                        >
                                                                            {contentTypes.find(
                                                                                (
                                                                                    t,
                                                                                ) =>
                                                                                    t.value ===
                                                                                    content.content_type,
                                                                            )
                                                                                ?.label ||
                                                                                content.content_type}
                                                                        </Badge>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        {
                                                                            contentTypes.find(
                                                                                (
                                                                                    t,
                                                                                ) =>
                                                                                    t.value ===
                                                                                    content.content_type,
                                                                            )
                                                                                ?.description
                                                                        }
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                                {content.keyword
                                                                    ?.difficulty && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <div className="cursor-help">
                                                                                <MiniProgressBar
                                                                                    level={
                                                                                        difficultyConfig[
                                                                                            content.keyword.difficulty.toLowerCase()
                                                                                        ]
                                                                                            ?.level ||
                                                                                        1
                                                                                    }
                                                                                    color={
                                                                                        difficultyConfig[
                                                                                            content.keyword.difficulty.toLowerCase()
                                                                                        ]
                                                                                            ?.color ||
                                                                                        'bg-slate-400'
                                                                                    }
                                                                                />
                                                                            </div>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            Difficulty:{' '}
                                                                            {difficultyConfig[
                                                                                content.keyword.difficulty.toLowerCase()
                                                                            ]
                                                                                ?.label ||
                                                                                content
                                                                                    .keyword
                                                                                    .difficulty}
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                                {content.keyword
                                                                    ?.volume && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <div className="cursor-help">
                                                                                <MiniProgressBar
                                                                                    level={
                                                                                        volumeConfig[
                                                                                            content.keyword.volume.toLowerCase()
                                                                                        ]
                                                                                            ?.level ||
                                                                                        1
                                                                                    }
                                                                                    color={
                                                                                        volumeConfig[
                                                                                            content.keyword.volume.toLowerCase()
                                                                                        ]
                                                                                            ?.color ||
                                                                                        'bg-slate-400'
                                                                                    }
                                                                                />
                                                                            </div>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            Volume:{' '}
                                                                            {volumeConfig[
                                                                                content.keyword.volume.toLowerCase()
                                                                            ]
                                                                                ?.label ||
                                                                                content
                                                                                    .keyword
                                                                                    .volume}
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                            </div>
                                                            {showCreateButton && (
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <button
                                                                            onClick={(
                                                                                e,
                                                                            ) => {
                                                                                e.stopPropagation();
                                                                                handleGenerate(
                                                                                    content,
                                                                                );
                                                                            }}
                                                                            className={cn(
                                                                                'mt-1.5 flex w-full items-center justify-center gap-1 rounded px-2 py-1 text-[10px] font-medium transition-colors',
                                                                                contentIsOverdue
                                                                                    ? 'bg-red-600 text-white hover:bg-red-700'
                                                                                    : 'bg-primary text-primary-foreground hover:bg-primary/90',
                                                                            )}
                                                                        >
                                                                            <Sparkles className="h-3 w-3" />
                                                                            Create
                                                                            &amp;
                                                                            Publish
                                                                        </button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        <p>
                                                                            Generate
                                                                            an
                                                                            article
                                                                            using
                                                                            AI
                                                                            and
                                                                            automatically
                                                                            publish
                                                                            it
                                                                            to
                                                                            your
                                                                            connected
                                                                            integrations
                                                                        </p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                            {showProcessingButton && (
                                                                <button
                                                                    disabled
                                                                    className="mt-1.5 flex w-full cursor-not-allowed items-center justify-center gap-1 rounded bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground"
                                                                >
                                                                    <Loader2 className="h-3 w-3 animate-spin" />
                                                                    {getProcessingLabel(
                                                                        content,
                                                                    )}
                                                                </button>
                                                            )}
                                                            {canPublish(
                                                                content,
                                                            ) && (
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <button
                                                                            onClick={(
                                                                                e,
                                                                            ) => {
                                                                                e.stopPropagation();
                                                                                handlePublish(
                                                                                    content,
                                                                                );
                                                                            }}
                                                                            className="mt-1.5 flex w-full items-center justify-center gap-1 rounded bg-emerald-600 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-emerald-700"
                                                                        >
                                                                            <Send className="h-3 w-3" />
                                                                            Publish
                                                                        </button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        <p>
                                                                            Publish
                                                                            this
                                                                            article
                                                                            to
                                                                            your
                                                                            connected
                                                                            integrations
                                                                        </p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                        </div>
                                                    );

                                                    if (contentIsOverdue) {
                                                        return (
                                                            <Tooltip
                                                                key={content.id}
                                                            >
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    {
                                                                        contentCard
                                                                    }
                                                                </TooltipTrigger>
                                                                <TooltipContent className="max-w-xs">
                                                                    <div className="flex items-start gap-2">
                                                                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                                                                        <div>
                                                                            <p className="font-medium">
                                                                                Overdue
                                                                            </p>
                                                                            <p className="text-xs text-muted-foreground">
                                                                                This
                                                                                content
                                                                                was
                                                                                scheduled
                                                                                for{' '}
                                                                                {format(
                                                                                    parseISO(
                                                                                        content.scheduled_date!,
                                                                                    ),
                                                                                    'MMM d, yyyy',
                                                                                )}{' '}
                                                                                but
                                                                                hasn't
                                                                                been
                                                                                published
                                                                                yet.
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        );
                                                    }

                                                    return contentCard;
                                                })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Backlog Sidebar */}
                    <div className="w-72 shrink-0 border-l bg-muted/30">
                        <div className="flex items-center justify-between border-b p-3">
                            <h3 className="flex items-center gap-2 font-medium">
                                <Inbox className="h-4 w-4" />
                                Backlog
                                {backlog.length > 0 && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-1 text-xs"
                                    >
                                        {backlog.length}
                                    </Badge>
                                )}
                            </h3>
                            <div className="flex items-center gap-1">
                                {backlog.length > 0 && (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleAutoSchedule}
                                                disabled={isAutoScheduling}
                                            >
                                                <CalendarPlus className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>
                                                Schedule all items (1 per day)
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                )}
                            </div>
                        </div>
                        <div className="h-[calc(100vh-300px)] overflow-auto p-2">
                            {backlog.length === 0 ? (
                                <div className="flex flex-col items-center justify-center p-6 text-center">
                                    <div className="mb-3 rounded-full bg-muted p-3">
                                        <Inbox className="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <p className="mb-1 text-sm font-medium">
                                        Backlog is empty
                                    </p>
                                    <p className="mb-4 text-xs text-muted-foreground">
                                        Add content ideas here to plan and
                                        schedule later
                                    </p>
                                    <Button
                                        size="sm"
                                        onClick={() => openAddDialog()}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Content
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <p className="px-1 text-xs text-muted-foreground">
                                        Drag items to calendar to schedule
                                    </p>
                                    {backlog.map((content) => {
                                        const hasArticle =
                                            content.article_id !== null;

                                        return (
                                            <div
                                                key={content.id}
                                                draggable
                                                onDragStart={(e) =>
                                                    handleDragStart(e, content)
                                                }
                                                onDragEnd={handleDragEnd}
                                                onDoubleClick={() =>
                                                    openEditDialog(content)
                                                }
                                                className={cn(
                                                    'group cursor-move rounded-lg bg-card p-2.5 shadow-sm transition-all hover:border-primary/50 hover:shadow-md',
                                                    hasArticle
                                                        ? 'border'
                                                        : 'border border-dashed',
                                                )}
                                            >
                                                <div className="mb-1.5 flex items-start justify-between gap-2">
                                                    <div className="flex items-start gap-1.5">
                                                        {hasArticle ? (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <FileText className="mt-0.5 h-3.5 w-3.5 shrink-0 text-green-600" />
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Article
                                                                    ready
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        ) : (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Circle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Needs
                                                                    generation
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                        <span className="text-sm leading-tight font-medium">
                                                            {content.title ||
                                                                content.keyword
                                                                    ?.keyword ||
                                                                'Untitled'}
                                                        </span>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <button
                                                                    onClick={() =>
                                                                        openEditDialog(
                                                                            content,
                                                                        )
                                                                    }
                                                                    className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                >
                                                                    <Pencil className="h-3 w-3" />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Edit content
                                                            </TooltipContent>
                                                        </Tooltip>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <button
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            content,
                                                                        )
                                                                    }
                                                                    className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                >
                                                                    <X className="h-3 w-3" />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Remove from
                                                                planner
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    'cursor-help text-xs',
                                                                    contentTypeColors[
                                                                        content
                                                                            .content_type
                                                                    ],
                                                                )}
                                                            >
                                                                {contentTypes.find(
                                                                    (t) =>
                                                                        t.value ===
                                                                        content.content_type,
                                                                )?.label ||
                                                                    content.content_type}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {
                                                                contentTypes.find(
                                                                    (t) =>
                                                                        t.value ===
                                                                        content.content_type,
                                                                )?.description
                                                            }
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    {content.keyword
                                                        ?.difficulty && (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <div className="cursor-help">
                                                                    <MiniProgressBar
                                                                        level={
                                                                            difficultyConfig[
                                                                                content.keyword.difficulty.toLowerCase()
                                                                            ]
                                                                                ?.level ||
                                                                            1
                                                                        }
                                                                        color={
                                                                            difficultyConfig[
                                                                                content.keyword.difficulty.toLowerCase()
                                                                            ]
                                                                                ?.color ||
                                                                            'bg-slate-400'
                                                                        }
                                                                    />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Difficulty:{' '}
                                                                {difficultyConfig[
                                                                    content.keyword.difficulty.toLowerCase()
                                                                ]?.label ||
                                                                    content
                                                                        .keyword
                                                                        .difficulty}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {content.keyword
                                                        ?.volume && (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <div className="cursor-help">
                                                                    <MiniProgressBar
                                                                        level={
                                                                            volumeConfig[
                                                                                content.keyword.volume.toLowerCase()
                                                                            ]
                                                                                ?.level ||
                                                                            1
                                                                        }
                                                                        color={
                                                                            volumeConfig[
                                                                                content.keyword.volume.toLowerCase()
                                                                            ]
                                                                                ?.color ||
                                                                            'bg-slate-400'
                                                                        }
                                                                    />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Volume:{' '}
                                                                {volumeConfig[
                                                                    content.keyword.volume.toLowerCase()
                                                                ]?.label ||
                                                                    content
                                                                        .keyword
                                                                        .volume}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    <GripVertical className="ml-auto h-4 w-4 text-muted-foreground/50" />
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {/* Add more section */}
                                    <div className="mt-3 border-t pt-3">
                                        <button
                                            onClick={() => openAddDialog()}
                                            className="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed p-2 text-xs text-muted-foreground transition-colors hover:border-primary hover:bg-primary/5 hover:text-primary"
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                            Add Content
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Add Content Dialog */}
            <Dialog open={isAddDialogOpen} onOpenChange={setIsAddDialogOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add to Calendar</DialogTitle>
                        <DialogDescription>
                            {selectedDate
                                ? `Schedule content for ${format(selectedDate, 'MMMM d, yyyy')}`
                                : 'Add new content to your backlog or schedule it directly'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddContent}>
                        <Tabs
                            value={addDialogTab}
                            onValueChange={(v) =>
                                setAddDialogTab(v as 'new' | 'existing')
                            }
                            className="w-full"
                        >
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="new" className="gap-2">
                                    <Sparkles className="h-4 w-4" />
                                    New Content
                                </TabsTrigger>
                                <TabsTrigger
                                    value="existing"
                                    className="gap-2"
                                    disabled={unscheduledArticles.length === 0}
                                >
                                    <FileText className="h-4 w-4" />
                                    Existing Article
                                    {unscheduledArticles.length > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-1 text-xs"
                                        >
                                            {unscheduledArticles.length}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="new" className="mt-4">
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="keyword">
                                                Keyword
                                            </Label>
                                            {!showCreateKeyword && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowCreateKeyword(
                                                            true,
                                                        )
                                                    }
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    + Create new
                                                </button>
                                            )}
                                        </div>
                                        {showCreateKeyword ? (
                                            <div className="flex gap-2">
                                                <Input
                                                    value={newKeywordText}
                                                    onChange={(e) =>
                                                        setNewKeywordText(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Enter keyword..."
                                                    className="flex-1"
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') {
                                                            e.preventDefault();
                                                            handleCreateKeyword();
                                                        }
                                                    }}
                                                />
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={
                                                        handleCreateKeyword
                                                    }
                                                    disabled={
                                                        creatingKeyword ||
                                                        !newKeywordText.trim()
                                                    }
                                                >
                                                    {creatingKeyword
                                                        ? 'Adding...'
                                                        : 'Add'}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        setShowCreateKeyword(
                                                            false,
                                                        );
                                                        setNewKeywordText('');
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        ) : (
                                            <Select
                                                value={
                                                    newContent.keyword_id ||
                                                    'none'
                                                }
                                                onValueChange={(value) =>
                                                    setNewContent({
                                                        ...newContent,
                                                        keyword_id:
                                                            value === 'none'
                                                                ? ''
                                                                : value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select a keyword" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">
                                                        No keyword
                                                    </SelectItem>
                                                    {localKeywords.map(
                                                        (keyword) => (
                                                            <SelectItem
                                                                key={keyword.id}
                                                                value={keyword.id.toString()}
                                                            >
                                                                <div className="flex w-full items-center justify-between gap-3">
                                                                    <span>
                                                                        {
                                                                            keyword.keyword
                                                                        }
                                                                    </span>
                                                                    <div className="flex items-center gap-2 text-muted-foreground">
                                                                        {(keyword.articles_count ??
                                                                            0) >
                                                                            0 && (
                                                                            <span className="text-xs">
                                                                                {
                                                                                    keyword.articles_count
                                                                                }{' '}
                                                                                article
                                                                                {(keyword.articles_count ??
                                                                                    0) !==
                                                                                1
                                                                                    ? 's'
                                                                                    : ''}
                                                                            </span>
                                                                        )}
                                                                        {(keyword.scheduled_contents_count ??
                                                                            0) >
                                                                            0 && (
                                                                            <span className="text-xs">
                                                                                {
                                                                                    keyword.scheduled_contents_count
                                                                                }{' '}
                                                                                scheduled
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="title">
                                            Custom Title (optional)
                                        </Label>
                                        <Input
                                            id="title"
                                            value={newContent.title}
                                            onChange={(e) =>
                                                setNewContent({
                                                    ...newContent,
                                                    title: e.target.value,
                                                })
                                            }
                                            placeholder="Leave blank to use keyword"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center gap-1.5">
                                            <Label htmlFor="content_type">
                                                Content Type
                                            </Label>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <HelpCircle className="h-3.5 w-3.5 cursor-help text-muted-foreground" />
                                                </TooltipTrigger>
                                                <TooltipContent className="max-w-xs">
                                                    Determines the article
                                                    structure and format. Each
                                                    type is optimized for
                                                    different goals like
                                                    tutorials, comparisons, or
                                                    listicles.
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <Select
                                            value={newContent.content_type}
                                            onValueChange={(value) =>
                                                setNewContent({
                                                    ...newContent,
                                                    content_type: value,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {contentTypes.map((type) => (
                                                    <SelectItem
                                                        key={type.value}
                                                        value={type.value}
                                                    >
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {newContent.content_type && (
                                            <div className="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/50">
                                                <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                    {
                                                        contentTypes.find(
                                                            (t) =>
                                                                t.value ===
                                                                newContent.content_type,
                                                        )?.label
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-blue-700 dark:text-blue-300">
                                                    {
                                                        contentTypes.find(
                                                            (t) =>
                                                                t.value ===
                                                                newContent.content_type,
                                                        )?.description
                                                    }
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Tone & Word Count */}
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="add-tone">
                                                Tone of Voice
                                            </Label>
                                            <Select
                                                value={
                                                    newContent.tone ||
                                                    'project-default'
                                                }
                                                onValueChange={(value) =>
                                                    setNewContent({
                                                        ...newContent,
                                                        tone:
                                                            value ===
                                                            'project-default'
                                                                ? ''
                                                                : value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select tone" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="project-default">
                                                        Use project default
                                                        <span className="ml-1 text-muted-foreground">
                                                            (
                                                            {getToneLabel(
                                                                project.default_tone,
                                                            )}
                                                            )
                                                        </span>
                                                    </SelectItem>
                                                    <div className="my-1 border-t" />
                                                    {toneOptions.map((tone) => (
                                                        <SelectItem
                                                            key={tone.value}
                                                            value={tone.value}
                                                        >
                                                            {tone.label} -{' '}
                                                            {tone.description}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="add-word-count">
                                                Target Words
                                            </Label>
                                            <Select
                                                value={
                                                    newContent.target_word_count ||
                                                    'default'
                                                }
                                                onValueChange={(value) =>
                                                    setNewContent({
                                                        ...newContent,
                                                        target_word_count:
                                                            value === 'default'
                                                                ? ''
                                                                : value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger id="add-word-count">
                                                    <SelectValue placeholder="Use project default" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="default">
                                                        Use project default (
                                                        {getWordCountLabel(
                                                            project.default_word_count,
                                                        )}
                                                        )
                                                    </SelectItem>
                                                    {wordCountOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={option.value.toString()}
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {!selectedDate && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="scheduled_date">
                                                Schedule Date (optional)
                                            </Label>
                                            <Input
                                                id="scheduled_date"
                                                type="date"
                                                value={
                                                    newContent.scheduled_date
                                                }
                                                onChange={(e) =>
                                                    setNewContent({
                                                        ...newContent,
                                                        scheduled_date:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            AI Instructions (optional)
                                        </Label>
                                        <Textarea
                                            id="notes"
                                            value={newContent.notes}
                                            onChange={(e) =>
                                                setNewContent({
                                                    ...newContent,
                                                    notes: e.target.value,
                                                })
                                            }
                                            placeholder="Enter instructions for AI..."
                                            rows={3}
                                        />
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="existing" className="mt-4">
                                <div className="grid gap-4">
                                    {unscheduledArticles.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center py-8 text-center">
                                            <FileText className="mb-3 h-10 w-10 text-muted-foreground/50" />
                                            <p className="text-sm font-medium">
                                                No unscheduled articles
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                All your articles are already
                                                scheduled or you haven&apos;t
                                                generated any yet.
                                            </p>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="grid gap-2">
                                                <Label>Select Article</Label>
                                                <Select
                                                    value={selectedArticleId}
                                                    onValueChange={
                                                        setSelectedArticleId
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Choose an article to schedule" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {unscheduledArticles.map(
                                                            (article) => (
                                                                <SelectItem
                                                                    key={
                                                                        article.id
                                                                    }
                                                                    value={article.id.toString()}
                                                                >
                                                                    <div className="flex flex-col">
                                                                        <span className="font-medium">
                                                                            {
                                                                                article.title
                                                                            }
                                                                        </span>
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {article
                                                                                .keyword
                                                                                ?.keyword &&
                                                                                `Keyword: ${article.keyword.keyword} • `}
                                                                            {article.word_count?.toLocaleString()}{' '}
                                                                            words
                                                                        </span>
                                                                    </div>
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            {selectedArticleId && (
                                                <div className="rounded-md border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950/50">
                                                    <div className="flex items-center gap-2">
                                                        <FileText className="h-4 w-4 text-green-600" />
                                                        <span className="text-sm font-medium text-green-900 dark:text-green-100">
                                                            Ready to publish
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-xs text-green-700 dark:text-green-300">
                                                        This article already
                                                        exists. It will be added
                                                        to your calendar ready
                                                        for publication.
                                                    </p>
                                                </div>
                                            )}

                                            {!selectedDate && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="article_scheduled_date">
                                                        Schedule Date (optional)
                                                    </Label>
                                                    <Input
                                                        id="article_scheduled_date"
                                                        type="date"
                                                        value={
                                                            newContent.scheduled_date
                                                        }
                                                        onChange={(e) =>
                                                            setNewContent({
                                                                ...newContent,
                                                                scheduled_date:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        Leave empty to add to
                                                        backlog
                                                    </p>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            </TabsContent>
                        </Tabs>

                        <DialogFooter className="mt-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsAddDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    addDialogTab === 'existing' &&
                                    !selectedArticleId
                                }
                            >
                                {selectedDate ? 'Schedule' : 'Add to Calendar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Content Dialog */}
            <Dialog
                open={!!editingContent}
                onOpenChange={(open) => !open && setEditingContent(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    {(() => {
                        const hasArticle =
                            editingContent?.article_id !== null &&
                            editingContent?.article_id !== undefined;
                        const isPublished =
                            editingContent?.status === 'published';
                        const article = editingContent?.article;

                        // Published state - read only
                        if (isPublished) {
                            return (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Content Details
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4">
                                        {/* Status Banner */}
                                        <div className="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/50">
                                            <CheckCircle className="h-5 w-5 text-emerald-600" />
                                            <div>
                                                <p className="font-medium text-emerald-900 dark:text-emerald-100">
                                                    Published
                                                </p>
                                                <p className="text-sm text-emerald-700 dark:text-emerald-300">
                                                    This content has been
                                                    published successfully.
                                                </p>
                                            </div>
                                        </div>

                                        {/* Article Card */}
                                        {article && (
                                            <div className="rounded-lg border bg-card p-4 shadow-sm">
                                                <div className="flex items-start gap-3">
                                                    <FileText className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="leading-tight font-medium">
                                                            {article.title}
                                                        </p>
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {
                                                                contentTypes.find(
                                                                    (t) =>
                                                                        t.value ===
                                                                        editingContent?.content_type,
                                                                )?.label
                                                            }
                                                            {article.word_count &&
                                                                ` • ${article.word_count.toLocaleString()} words`}
                                                        </p>
                                                        <div className="mt-3 flex items-center gap-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={showArticle.url(
                                                                        {
                                                                            project:
                                                                                project.id,
                                                                            article:
                                                                                article.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <Eye className="mr-1.5 h-3.5 w-3.5" />
                                                                    View Article
                                                                </Link>
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setEditingContent(null)
                                            }
                                        >
                                            Close
                                        </Button>
                                    </DialogFooter>
                                </>
                            );
                        }

                        // Ready state - has article
                        if (hasArticle && article) {
                            return (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>Edit Content</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={handleEditSubmit}>
                                        <div className="space-y-4 py-4">
                                            {/* Status Banner */}
                                            <div className="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/50">
                                                <CheckCircle className="h-5 w-5 text-green-600" />
                                                <div>
                                                    <p className="font-medium text-green-900 dark:text-green-100">
                                                        Ready
                                                    </p>
                                                    <p className="text-sm text-green-700 dark:text-green-300">
                                                        Article generated and
                                                        ready to publish.
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Article Card */}
                                            <div className="rounded-lg border bg-card p-4 shadow-sm">
                                                <div className="flex items-start gap-3">
                                                    <FileText className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="leading-tight font-medium">
                                                            {article.title}
                                                        </p>
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {
                                                                contentTypes.find(
                                                                    (t) =>
                                                                        t.value ===
                                                                        editingContent?.content_type,
                                                                )?.label
                                                            }
                                                            {article.word_count &&
                                                                ` • ${article.word_count.toLocaleString()} words`}
                                                            {article.status &&
                                                                ` • ${article.status.charAt(0).toUpperCase() + article.status.slice(1)}`}
                                                        </p>
                                                        <div className="mt-3 flex items-center gap-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={showArticle.url(
                                                                        {
                                                                            project:
                                                                                project.id,
                                                                            article:
                                                                                article.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <Eye className="mr-1.5 h-3.5 w-3.5" />
                                                                    View
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={editArticle.url(
                                                                        {
                                                                            project:
                                                                                project.id,
                                                                            article:
                                                                                article.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                                                    Edit Article
                                                                </Link>
                                                            </Button>
                                                            {editingContent &&
                                                                canPublish(
                                                                    editingContent,
                                                                ) && (
                                                                    <Button
                                                                        size="sm"
                                                                        className="bg-emerald-600 hover:bg-emerald-700"
                                                                        onClick={() => {
                                                                            handlePublish(
                                                                                editingContent,
                                                                            );
                                                                            closeEditDialog();
                                                                        }}
                                                                    >
                                                                        <Send className="mr-1.5 h-3.5 w-3.5" />
                                                                        Publish
                                                                    </Button>
                                                                )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Divider */}
                                            <div className="relative">
                                                <div className="absolute inset-0 flex items-center">
                                                    <div className="w-full border-t" />
                                                </div>
                                                <div className="relative flex justify-center text-xs uppercase">
                                                    <span className="bg-background px-2 text-muted-foreground">
                                                        Schedule
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Schedule Date */}
                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-scheduled-date">
                                                    Scheduled Date
                                                </Label>
                                                <Input
                                                    id="edit-scheduled-date"
                                                    type="date"
                                                    value={
                                                        editForm.scheduled_date
                                                    }
                                                    onChange={(e) =>
                                                        setEditForm({
                                                            ...editForm,
                                                            scheduled_date:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Leave empty to keep in
                                                    backlog.
                                                </p>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingContent(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button type="submit">
                                                Save Changes
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </>
                            );
                        }

                        // Planned state - no article, needs generation
                        return (
                            <>
                                <DialogHeader>
                                    <DialogTitle>Edit Content</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={handleEditSubmit}>
                                    <div className="space-y-4 py-4">
                                        {/* Status Banner */}
                                        <div className="flex items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                                            <Circle className="h-5 w-5 text-slate-500" />
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-slate-100">
                                                    Planned
                                                </p>
                                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                                    Configure how this article
                                                    will be generated.
                                                </p>
                                            </div>
                                        </div>

                                        {/* Keyword info */}
                                        {editingContent?.keyword && (
                                            <div className="rounded-md border bg-muted/50 px-3 py-2">
                                                <p className="text-xs text-muted-foreground">
                                                    Keyword
                                                </p>
                                                <p className="font-medium">
                                                    {
                                                        editingContent.keyword
                                                            .keyword
                                                    }
                                                </p>
                                            </div>
                                        )}

                                        {/* Divider */}
                                        <div className="relative">
                                            <div className="absolute inset-0 flex items-center">
                                                <div className="w-full border-t" />
                                            </div>
                                            <div className="relative flex justify-center text-xs uppercase">
                                                <span className="bg-background px-2 text-muted-foreground">
                                                    Generation Settings
                                                </span>
                                            </div>
                                        </div>

                                        {/* Title */}
                                        <div className="grid gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <Label htmlFor="edit-title">
                                                    Custom Title
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <HelpCircle className="h-3.5 w-3.5 cursor-help text-muted-foreground" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Override the keyword as
                                                        the article title.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Input
                                                id="edit-title"
                                                value={editForm.title}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        title: e.target.value,
                                                    })
                                                }
                                                placeholder={
                                                    editingContent?.keyword
                                                        ?.keyword ||
                                                    'Enter a title'
                                                }
                                            />
                                        </div>

                                        {/* Content Type */}
                                        <div className="grid gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <Label htmlFor="edit-content-type">
                                                    Content Type
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <HelpCircle className="h-3.5 w-3.5 cursor-help text-muted-foreground" />
                                                    </TooltipTrigger>
                                                    <TooltipContent className="max-w-xs">
                                                        Determines the article
                                                        structure and format.
                                                        Each type is optimized
                                                        for different goals like
                                                        tutorials, comparisons,
                                                        or listicles.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Select
                                                value={editForm.content_type}
                                                onValueChange={(value) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        content_type: value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {contentTypes.map(
                                                        (type) => (
                                                            <SelectItem
                                                                key={type.value}
                                                                value={
                                                                    type.value
                                                                }
                                                            >
                                                                {type.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {editForm.content_type && (
                                                <div className="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/50">
                                                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                        {
                                                            contentTypes.find(
                                                                (t) =>
                                                                    t.value ===
                                                                    editForm.content_type,
                                                            )?.label
                                                        }
                                                    </p>
                                                    <p className="mt-1 text-xs text-blue-700 dark:text-blue-300">
                                                        {
                                                            contentTypes.find(
                                                                (t) =>
                                                                    t.value ===
                                                                    editForm.content_type,
                                                            )?.description
                                                        }
                                                    </p>
                                                </div>
                                            )}
                                        </div>

                                        {/* Tone & Word Count */}
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-tone">
                                                    Tone of Voice
                                                </Label>
                                                <Select
                                                    value={
                                                        editForm.tone ||
                                                        'project-default'
                                                    }
                                                    onValueChange={(value) =>
                                                        setEditForm({
                                                            ...editForm,
                                                            tone:
                                                                value ===
                                                                'project-default'
                                                                    ? ''
                                                                    : value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select tone" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="project-default">
                                                            Use project default
                                                            <span className="ml-1 text-muted-foreground">
                                                                (
                                                                {getToneLabel(
                                                                    project.default_tone,
                                                                )}
                                                                )
                                                            </span>
                                                        </SelectItem>
                                                        <div className="my-1 border-t" />
                                                        {toneOptions.map(
                                                            (tone) => (
                                                                <SelectItem
                                                                    key={
                                                                        tone.value
                                                                    }
                                                                    value={
                                                                        tone.value
                                                                    }
                                                                >
                                                                    {tone.label}{' '}
                                                                    -{' '}
                                                                    {
                                                                        tone.description
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-word-count">
                                                    Target Words
                                                </Label>
                                                <Select
                                                    value={
                                                        editForm.target_word_count ||
                                                        'default'
                                                    }
                                                    onValueChange={(value) =>
                                                        setEditForm({
                                                            ...editForm,
                                                            target_word_count:
                                                                value ===
                                                                'default'
                                                                    ? ''
                                                                    : value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger id="edit-word-count">
                                                        <SelectValue placeholder="Use project default" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="default">
                                                            Use project default
                                                            (
                                                            {getWordCountLabel(
                                                                project.default_word_count,
                                                            )}
                                                            )
                                                        </SelectItem>
                                                        {wordCountOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={option.value.toString()}
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        {/* AI Instructions */}
                                        <div className="grid gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <Label htmlFor="edit-notes">
                                                    AI Instructions
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <HelpCircle className="h-3.5 w-3.5 cursor-help text-muted-foreground" />
                                                    </TooltipTrigger>
                                                    <TooltipContent className="max-w-xs">
                                                        Instructions passed to
                                                        the AI when generating.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Textarea
                                                id="edit-notes"
                                                value={editForm.notes}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        notes: e.target.value,
                                                    })
                                                }
                                                placeholder="Enter instructions for AI..."
                                                rows={3}
                                            />
                                        </div>

                                        {/* Divider */}
                                        <div className="relative">
                                            <div className="absolute inset-0 flex items-center">
                                                <div className="w-full border-t" />
                                            </div>
                                            <div className="relative flex justify-center text-xs uppercase">
                                                <span className="bg-background px-2 text-muted-foreground">
                                                    Schedule
                                                </span>
                                            </div>
                                        </div>

                                        {/* Schedule Date */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-scheduled-date">
                                                Scheduled Date
                                            </Label>
                                            <Input
                                                id="edit-scheduled-date"
                                                type="date"
                                                value={editForm.scheduled_date}
                                                onChange={(e) =>
                                                    setEditForm({
                                                        ...editForm,
                                                        scheduled_date:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setEditingContent(null)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button type="submit">
                                            Save Changes
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </>
                        );
                    })()}
                </DialogContent>
            </Dialog>

            {/* Day View Dialog */}
            <Dialog
                open={!!dayViewDate}
                onOpenChange={(open) => !open && setDayViewDate(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            {dayViewDate &&
                                format(dayViewDate, 'EEEE, MMMM d, yyyy')}
                        </DialogTitle>
                        <DialogDescription>
                            {dayViewDate &&
                                getContentForDay(dayViewDate).length}{' '}
                            scheduled items
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] space-y-2 overflow-auto py-4">
                        {dayViewDate &&
                            getContentForDay(dayViewDate).map((content) => {
                                const hasArticle = content.article_id !== null;
                                const isPublished =
                                    content.status === 'published';
                                const contentIsOverdue = isOverdue(content);
                                const contentIsDueToday = isDueToday(content);
                                const showCreateButton =
                                    (contentIsOverdue || contentIsDueToday) &&
                                    canGenerate(content);
                                const showProcessingButton =
                                    isProcessing(content);

                                return (
                                    <div
                                        key={content.id}
                                        className={cn(
                                            'group flex items-start justify-between gap-3 rounded-lg p-3',
                                            contentIsOverdue
                                                ? 'border border-red-400 bg-red-50 text-red-900 dark:border-red-600 dark:bg-red-950/50 dark:text-red-200'
                                                : statusColors[content.status],
                                            !contentIsOverdue &&
                                                (hasArticle || isPublished)
                                                ? 'border'
                                                : !contentIsOverdue
                                                  ? 'border border-dashed'
                                                  : '',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start gap-2">
                                                {contentIsOverdue ? (
                                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                                ) : isPublished ? (
                                                    <CheckCircle className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                                ) : hasArticle ? (
                                                    <FileText className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                                ) : (
                                                    <Circle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                )}
                                                <div>
                                                    <p className="leading-tight font-medium">
                                                        {content.title ||
                                                            content.keyword
                                                                ?.keyword ||
                                                            'Untitled'}
                                                    </p>
                                                    {contentIsOverdue && (
                                                        <p className="text-xs text-red-600 dark:text-red-400">
                                                            Overdue - was
                                                            scheduled for{' '}
                                                            {format(
                                                                parseISO(
                                                                    content.scheduled_date!,
                                                                ),
                                                                'MMM d, yyyy',
                                                            )}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'text-xs',
                                                        contentTypeColors[
                                                            content.content_type
                                                        ],
                                                    )}
                                                >
                                                    {contentTypes.find(
                                                        (t) =>
                                                            t.value ===
                                                            content.content_type,
                                                    )?.label ||
                                                        content.content_type}
                                                </Badge>
                                                {content.target_word_count && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {content.target_word_count.toLocaleString()}{' '}
                                                        words
                                                    </span>
                                                )}
                                                {content.tone && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {content.tone}
                                                    </span>
                                                )}
                                            </div>
                                            {content.notes && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {content.notes}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            {showCreateButton && (
                                                <Button
                                                    size="sm"
                                                    className={cn(
                                                        contentIsOverdue &&
                                                            'bg-red-600 hover:bg-red-700',
                                                    )}
                                                    onClick={() =>
                                                        handleGenerate(content)
                                                    }
                                                >
                                                    <Sparkles className="mr-1 h-3 w-3" />
                                                    Create & Publish
                                                </Button>
                                            )}
                                            {showProcessingButton && (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled
                                                >
                                                    <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                                                    {getProcessingLabel(content)}
                                                </Button>
                                            )}
                                            {canPublish(content) && (
                                                <Button
                                                    size="sm"
                                                    className="bg-emerald-600 hover:bg-emerald-700"
                                                    onClick={() =>
                                                        handlePublish(content)
                                                    }
                                                >
                                                    <Send className="mr-1 h-3 w-3" />
                                                    Publish
                                                </Button>
                                            )}
                                            {/* Show View Article button for published content with article */}
                                            {isPublished && hasArticle && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showArticle.url({
                                                            project: project.id,
                                                            article:
                                                                content.article_id!,
                                                        })}
                                                    >
                                                        <Eye className="mr-1 h-3 w-3" />
                                                        View Article
                                                    </Link>
                                                </Button>
                                            )}
                                            {/* Hide edit/unschedule buttons for published content */}
                                            {!isPublished && (
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setDayViewDate(
                                                                null,
                                                            );
                                                            openEditDialog(
                                                                content,
                                                            );
                                                        }}
                                                    >
                                                        <Pencil className="mr-1 h-3 w-3" />
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleUnschedule(
                                                                content,
                                                            )
                                                        }
                                                    >
                                                        <X className="mr-1 h-3 w-3" />
                                                        Unschedule
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        {dayViewDate &&
                            getContentForDay(dayViewDate).length === 0 && (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Calendar className="mb-3 h-10 w-10 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        No content scheduled for this day
                                    </p>
                                </div>
                            )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDayViewDate(null)}
                        >
                            Close
                        </Button>
                        <Button
                            onClick={() => {
                                if (dayViewDate) {
                                    openAddDialog(dayViewDate);
                                    setDayViewDate(null);
                                }
                            }}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Content
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
