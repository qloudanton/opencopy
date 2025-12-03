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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { axios } from '@/lib/axios';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Globe,
    Link2,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import * as React from 'react';
import { useState } from 'react';

interface ProjectPage {
    id: number;
    url: string;
    title: string | null;
    page_type: string;
    keywords: string[];
    priority: number;
    link_count: number;
    is_active: boolean;
    last_modified_at: string | null;
    last_fetched_at: string | null;
}

interface PaginatedPages {
    data: ProjectPage[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Stats {
    total: number;
    active: number;
    by_type: {
        blog: number;
        product: number;
        service: number;
        landing: number;
        other: number;
    };
    total_links_distributed: number;
    last_fetched: string | null;
}

interface PageType {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    page_type: string;
    is_active: string | null;
}

interface Project {
    id: number;
    name: string;
    sitemap_url: string | null;
}

interface Props {
    project: Project;
    pages: PaginatedPages;
    stats: Stats;
    filters: Filters;
    pageTypes: PageType[];
}

interface PageFormData {
    url: string;
    title: string;
    page_type: string;
    priority: string;
    keywords: string[];
}

const pageTypeColors: Record<string, string> = {
    blog: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    product:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    service:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    landing:
        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    other: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

const defaultFormData: PageFormData = {
    url: '',
    title: '',
    page_type: 'blog',
    priority: '0.5',
    keywords: [],
};

export default function PagesIndex({
    project,
    pages,
    stats,
    filters,
    pageTypes,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [syncing, setSyncing] = useState(false);
    const [syncMessage, setSyncMessage] = useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);

    // Dialog state
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingPage, setEditingPage] = useState<ProjectPage | null>(null);
    const [formData, setFormData] = useState<PageFormData>(defaultFormData);
    const [keywordInput, setKeywordInput] = useState('');
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Internal Pages', href: `/projects/${project.id}/pages` },
    ];

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            `/projects/${project.id}/pages`,
            { search, page_type: filters.page_type },
            { preserveState: true },
        );
    }

    function handleFilterChange(key: string, value: string) {
        router.get(
            `/projects/${project.id}/pages`,
            { search, page_type: filters.page_type, [key]: value },
            { preserveState: true },
        );
    }

    async function handleSync() {
        setSyncing(true);
        setSyncMessage(null);

        try {
            const response = await axios.post(
                `/projects/${project.id}/pages/sync`,
            );
            const result = response.data;

            if (result.success) {
                setSyncMessage({ type: 'success', text: result.message });
                router.reload();
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
            setSyncMessage({ type: 'error', text: message });
        } finally {
            setSyncing(false);
        }
    }

    async function handleToggleActive(page: ProjectPage) {
        try {
            await axios.put(`/projects/${project.id}/pages/${page.id}`, {
                ...page,
                is_active: !page.is_active,
            });
            router.reload({ only: ['pages', 'stats'] });
        } catch {
            // Handle error silently
        }
    }

    async function handleDelete(page: ProjectPage) {
        if (!confirm('Remove this page from internal linking?')) return;

        try {
            await axios.delete(`/projects/${project.id}/pages/${page.id}`);
            router.reload({ only: ['pages', 'stats'] });
        } catch {
            // Handle error silently
        }
    }

    async function handleChangeType(page: ProjectPage, newType: string) {
        try {
            await axios.put(`/projects/${project.id}/pages/${page.id}`, {
                ...page,
                page_type: newType,
            });
            router.reload({ only: ['pages', 'stats'] });
        } catch {
            // Handle error silently
        }
    }

    async function handleChangePriority(
        page: ProjectPage,
        newPriority: string,
    ) {
        try {
            await axios.put(`/projects/${project.id}/pages/${page.id}`, {
                ...page,
                priority: parseFloat(newPriority),
            });
            router.reload({ only: ['pages', 'stats'] });
        } catch {
            // Handle error silently
        }
    }

    // Dialog functions
    function openAddDialog() {
        setEditingPage(null);
        setFormData(defaultFormData);
        setKeywordInput('');
        setFormError(null);
        setDialogOpen(true);
    }

    function openEditDialog(page: ProjectPage) {
        setEditingPage(page);
        setFormData({
            url: page.url,
            title: page.title || '',
            page_type: page.page_type,
            priority: page.priority.toString(),
            keywords: page.keywords || [],
        });
        setKeywordInput('');
        setFormError(null);
        setDialogOpen(true);
    }

    function handleAddKeyword() {
        const keyword = keywordInput.trim();
        if (keyword && !formData.keywords.includes(keyword)) {
            setFormData({
                ...formData,
                keywords: [...formData.keywords, keyword],
            });
        }
        setKeywordInput('');
    }

    function handleKeywordKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleAddKeyword();
        }
    }

    function handleRemoveKeyword(keyword: string) {
        setFormData({
            ...formData,
            keywords: formData.keywords.filter((k) => k !== keyword),
        });
    }

    async function handleSave() {
        setSaving(true);
        setFormError(null);

        try {
            const payload = {
                url: formData.url,
                title: formData.title || null,
                page_type: formData.page_type,
                priority: parseFloat(formData.priority),
                keywords: formData.keywords,
            };

            if (editingPage) {
                // Update existing page
                await axios.put(
                    `/projects/${project.id}/pages/${editingPage.id}`,
                    payload,
                );
            } else {
                // Create new page
                await axios.post(`/projects/${project.id}/pages`, payload);
            }

            setDialogOpen(false);
            router.reload({ only: ['pages', 'stats'] });
        } catch (error) {
            const message =
                axios.isAxiosError(error) && error.response?.data?.message
                    ? error.response.data.message
                    : 'Failed to save page';
            setFormError(message);
        } finally {
            setSaving(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Internal Pages - ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Internal Pages</h1>
                        <p className="text-muted-foreground">
                            Manage pages for internal linking
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={openAddDialog}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Page
                        </Button>
                        <Button
                            onClick={handleSync}
                            disabled={syncing || !project.sitemap_url}
                        >
                            {syncing ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="mr-2 h-4 w-4" />
                            )}
                            Sync Sitemap
                        </Button>
                    </div>
                </div>

                {/* Sync Message */}
                {syncMessage && (
                    <div
                        className={`rounded-lg p-3 text-sm ${
                            syncMessage.type === 'success'
                                ? 'bg-green-50 text-green-800 dark:bg-green-900/50 dark:text-green-200'
                                : 'bg-red-50 text-red-800 dark:bg-red-900/50 dark:text-red-200'
                        }`}
                    >
                        {syncMessage.text}
                    </div>
                )}

                {/* No Sitemap Warning */}
                {!project.sitemap_url && (
                    <Card className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50">
                        <CardContent className="flex items-center gap-3 p-4">
                            <Globe className="h-5 w-5 text-amber-600" />
                            <div className="flex-1">
                                <p className="font-medium text-amber-900 dark:text-amber-100">
                                    No sitemap configured
                                </p>
                                <p className="text-sm text-amber-700 dark:text-amber-300">
                                    Configure a sitemap URL in{' '}
                                    <Link
                                        href={`/projects/${project.id}/settings`}
                                        className="underline hover:no-underline"
                                    >
                                        project settings
                                    </Link>{' '}
                                    to automatically import pages, or add them
                                    manually.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Pages</CardDescription>
                            <CardTitle className="text-3xl">
                                {stats.total}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                {stats.active} active for linking
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Blog Posts</CardDescription>
                            <CardTitle className="text-3xl">
                                {stats.by_type.blog}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Prioritized for internal links
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Links Distributed</CardDescription>
                            <CardTitle className="text-3xl">
                                {stats.total_links_distributed}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Across all generated articles
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Last Synced</CardDescription>
                            <CardTitle className="text-lg">
                                {stats.last_fetched
                                    ? new Date(
                                          stats.last_fetched,
                                      ).toLocaleDateString()
                                    : 'Never'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                {stats.last_fetched
                                    ? new Date(
                                          stats.last_fetched,
                                      ).toLocaleTimeString()
                                    : 'Sync to import pages'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-4">
                    <form onSubmit={handleSearch} className="flex-1">
                        <div className="relative max-w-sm">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Search pages..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                    </form>
                    <Select
                        value={filters.page_type}
                        onValueChange={(value) =>
                            handleFilterChange('page_type', value)
                        }
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Filter by type" />
                        </SelectTrigger>
                        <SelectContent>
                            {pageTypes.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Pages Table */}
                <Card>
                    <CardContent className="p-0">
                        {pages.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="mb-4 h-12 w-12 text-muted-foreground/50" />
                                <h3 className="mb-1 text-lg font-medium">
                                    No pages found
                                </h3>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    {filters.search ||
                                    filters.page_type !== 'all'
                                        ? 'Try adjusting your filters'
                                        : 'Add pages manually or sync from sitemap'}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={openAddDialog}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Page
                                    </Button>
                                    {!filters.search &&
                                        filters.page_type === 'all' &&
                                        project.sitemap_url && (
                                            <Button
                                                onClick={handleSync}
                                                disabled={syncing}
                                            >
                                                {syncing ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <RefreshCw className="mr-2 h-4 w-4" />
                                                )}
                                                Sync Sitemap
                                            </Button>
                                        )}
                                </div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            Active
                                        </TableHead>
                                        <TableHead>Page</TableHead>
                                        <TableHead className="max-w-48">
                                            Keywords
                                        </TableHead>
                                        <TableHead className="w-28">
                                            Type
                                        </TableHead>
                                        <TableHead className="w-20 text-center">
                                            Links
                                        </TableHead>
                                        <TableHead className="w-20 text-center">
                                            Priority
                                        </TableHead>
                                        <TableHead className="w-12"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pages.data.map((page) => (
                                        <TableRow key={page.id}>
                                            <TableCell>
                                                <Switch
                                                    checked={page.is_active}
                                                    onCheckedChange={() =>
                                                        handleToggleActive(page)
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate font-medium">
                                                            {page.title ||
                                                                'Untitled'}
                                                        </span>
                                                        <a
                                                            href={page.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="shrink-0 text-muted-foreground hover:text-foreground"
                                                        >
                                                            <ExternalLink className="h-3.5 w-3.5" />
                                                        </a>
                                                    </div>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {page.url}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-48">
                                                {page.keywords &&
                                                page.keywords.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {page.keywords
                                                            .slice(0, 5)
                                                            .map(
                                                                (
                                                                    keyword,
                                                                    idx,
                                                                ) => (
                                                                    <Badge
                                                                        key={
                                                                            idx
                                                                        }
                                                                        variant="outline"
                                                                        className="text-xs font-normal"
                                                                    >
                                                                        {
                                                                            keyword
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        {page.keywords.length >
                                                            5 && (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs font-normal"
                                                                    >
                                                                        +
                                                                        {page
                                                                            .keywords
                                                                            .length -
                                                                            5}
                                                                    </Badge>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {page.keywords
                                                                        .slice(
                                                                            5,
                                                                        )
                                                                        .join(
                                                                            ', ',
                                                                        )}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Select
                                                    value={page.page_type}
                                                    onValueChange={(value) =>
                                                        handleChangeType(
                                                            page,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-7 w-24 text-xs">
                                                        <Badge
                                                            variant="secondary"
                                                            className={`${pageTypeColors[page.page_type]} text-xs`}
                                                        >
                                                            {page.page_type}
                                                        </Badge>
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="blog">
                                                            Blog
                                                        </SelectItem>
                                                        <SelectItem value="product">
                                                            Product
                                                        </SelectItem>
                                                        <SelectItem value="service">
                                                            Service
                                                        </SelectItem>
                                                        <SelectItem value="landing">
                                                            Landing
                                                        </SelectItem>
                                                        <SelectItem value="other">
                                                            Other
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className="flex items-center justify-center gap-1">
                                                            <Link2 className="h-3.5 w-3.5 text-muted-foreground" />
                                                            <span>
                                                                {
                                                                    page.link_count
                                                                }
                                                            </span>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Used in{' '}
                                                        {page.link_count}{' '}
                                                        article
                                                        {page.link_count !== 1
                                                            ? 's'
                                                            : ''}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Select
                                                    value={page.priority.toString()}
                                                    onValueChange={(value) =>
                                                        handleChangePriority(
                                                            page,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-7 w-20 text-xs">
                                                        <SelectValue>
                                                            {(
                                                                page.priority *
                                                                100
                                                            ).toFixed(0)}
                                                            %
                                                        </SelectValue>
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="1">
                                                            100%
                                                        </SelectItem>
                                                        <SelectItem value="0.9">
                                                            90%
                                                        </SelectItem>
                                                        <SelectItem value="0.8">
                                                            80%
                                                        </SelectItem>
                                                        <SelectItem value="0.7">
                                                            70%
                                                        </SelectItem>
                                                        <SelectItem value="0.6">
                                                            60%
                                                        </SelectItem>
                                                        <SelectItem value="0.5">
                                                            50%
                                                        </SelectItem>
                                                        <SelectItem value="0.4">
                                                            40%
                                                        </SelectItem>
                                                        <SelectItem value="0.3">
                                                            30%
                                                        </SelectItem>
                                                        <SelectItem value="0.2">
                                                            20%
                                                        </SelectItem>
                                                        <SelectItem value="0.1">
                                                            10%
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                openEditDialog(
                                                                    page,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="mr-2 h-4 w-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <a
                                                                href={page.url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <ExternalLink className="mr-2 h-4 w-4" />
                                                                Open Page
                                                            </a>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleDelete(
                                                                    page,
                                                                )
                                                            }
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Remove
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {pages.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing{' '}
                            {(pages.current_page - 1) * pages.per_page + 1} to{' '}
                            {Math.min(
                                pages.current_page * pages.per_page,
                                pages.total,
                            )}{' '}
                            of {pages.total} pages
                        </p>
                        <div className="flex gap-1">
                            {pages.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url && router.get(link.url)
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Add/Edit Page Dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingPage ? 'Edit Page' : 'Add Page'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingPage
                                ? 'Update the page details for internal linking.'
                                : 'Add a new page to use for internal linking.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        {formError && (
                            <div className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-900/50 dark:text-red-200">
                                {formError}
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="url">URL</Label>
                            <Input
                                id="url"
                                type="url"
                                placeholder="https://example.com/page"
                                value={formData.url}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        url: e.target.value,
                                    })
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="title">Title (optional)</Label>
                            <Input
                                id="title"
                                placeholder="Page title"
                                value={formData.title}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        title: e.target.value,
                                    })
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave empty to auto-generate from URL
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="page_type">Page Type</Label>
                                <Select
                                    value={formData.page_type}
                                    onValueChange={(value) =>
                                        setFormData({
                                            ...formData,
                                            page_type: value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="blog">
                                            Blog
                                        </SelectItem>
                                        <SelectItem value="product">
                                            Product
                                        </SelectItem>
                                        <SelectItem value="service">
                                            Service
                                        </SelectItem>
                                        <SelectItem value="landing">
                                            Landing
                                        </SelectItem>
                                        <SelectItem value="other">
                                            Other
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="priority">Priority</Label>
                                <Select
                                    value={formData.priority}
                                    onValueChange={(value) =>
                                        setFormData({
                                            ...formData,
                                            priority: value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">100%</SelectItem>
                                        <SelectItem value="0.9">90%</SelectItem>
                                        <SelectItem value="0.8">80%</SelectItem>
                                        <SelectItem value="0.7">70%</SelectItem>
                                        <SelectItem value="0.6">60%</SelectItem>
                                        <SelectItem value="0.5">50%</SelectItem>
                                        <SelectItem value="0.4">40%</SelectItem>
                                        <SelectItem value="0.3">30%</SelectItem>
                                        <SelectItem value="0.2">20%</SelectItem>
                                        <SelectItem value="0.1">10%</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="keywords">Keywords</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="keywords"
                                    placeholder="Add keyword and press Enter"
                                    value={keywordInput}
                                    onChange={(e) =>
                                        setKeywordInput(e.target.value)
                                    }
                                    onKeyDown={handleKeywordKeyDown}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleAddKeyword}
                                >
                                    Add
                                </Button>
                            </div>
                            {formData.keywords.length > 0 && (
                                <div className="flex flex-wrap gap-1 pt-2">
                                    {formData.keywords.map((keyword, idx) => (
                                        <Badge
                                            key={idx}
                                            variant="secondary"
                                            className="gap-1 pr-1"
                                        >
                                            {keyword}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemoveKeyword(keyword)
                                                }
                                                className="ml-1 rounded p-0.5 hover:bg-muted"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Keywords help match this page to relevant
                                articles
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSave}
                            disabled={saving || !formData.url}
                        >
                            {saving ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : null}
                            {editingPage ? 'Save Changes' : 'Add Page'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
