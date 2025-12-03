import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

import InputError from '@/components/input-error';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import {
    ArrowDownUp,
    CheckCircle,
    ChevronDown,
    ChevronRight,
    Copy,
    Globe,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    Send,
    Trash2,
    Webhook,
    XCircle,
} from 'lucide-react';

interface Integration {
    id: number;
    type: string;
    name: string;
    is_active: boolean;
    has_credentials: boolean;
    last_connected_at: string | null;
    publications_count: number;
    created_at: string;
}

interface CredentialField {
    type: string;
    label: string;
    required: boolean;
}

interface IntegrationType {
    value: string;
    label: string;
    description: string;
    icon: string;
    is_available: boolean;
    credentials: Record<string, CredentialField>;
}

interface Project {
    id: number;
    name: string;
}

interface TestDebugResult {
    success: boolean;
    message: string;
    request: {
        method: string;
        url: string | null;
        headers: Record<string, string>;
        body: Record<string, unknown> | null;
    };
    response: {
        status_code: number | null;
        status_text: string | null;
        headers: Record<string, string> | null;
        body: Record<string, unknown> | null;
    };
}

interface Props {
    project: Project;
    integrations: Integration[];
    availableTypes: IntegrationType[];
}

export default function IntegrationsIndex({
    project,
    integrations,
    availableTypes,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingIntegration, setEditingIntegration] =
        useState<Integration | null>(null);
    const [testingId, setTestingId] = useState<number | null>(null);
    const [testResult, setTestResult] = useState<{
        id: number;
        success: boolean;
        message: string;
    } | null>(null);
    const [debugDialogOpen, setDebugDialogOpen] = useState(false);
    const [debugResult, setDebugResult] = useState<TestDebugResult | null>(
        null,
    );
    const [testingIntegrationName, setTestingIntegrationName] = useState('');
    const { csrf_token } = usePage<{ csrf_token: string }>().props;

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        type: string;
        name: string;
        is_active: boolean;
        endpoint_url: string;
        access_token: string;
        timeout: number;
        retry_times: number;
        // WordPress
        site_url: string;
        username: string;
        application_password: string;
        // Webflow
        api_token: string;
        site_id: string;
        collection_id: string;
        // Shopify
        store_url: string;
        blog_id: string;
        // Wix
        api_key: string;
    }>({
        type: '',
        name: '',
        is_active: true,
        endpoint_url: '',
        access_token: '',
        timeout: 30,
        retry_times: 3,
        site_url: '',
        username: '',
        application_password: '',
        api_token: '',
        site_id: '',
        collection_id: '',
        store_url: '',
        blog_id: '',
        api_key: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Integrations', href: `/projects/${project.id}/integrations` },
    ];

    const selectedType = availableTypes.find((t) => t.value === data.type);

    function openAddDialog() {
        reset();
        setEditingIntegration(null);
        setIsDialogOpen(true);
    }

    function openEditDialog(integration: Integration) {
        setEditingIntegration(integration);
        setData({
            ...data,
            type: integration.type,
            name: integration.name,
            is_active: integration.is_active,
            // Clear sensitive fields - user must re-enter
            endpoint_url: '',
            access_token: '',
            site_url: '',
            username: '',
            application_password: '',
            api_token: '',
            site_id: '',
            collection_id: '',
            store_url: '',
            blog_id: '',
            api_key: '',
        });
        setIsDialogOpen(true);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (editingIntegration) {
            put(
                `/projects/${project.id}/integrations/${editingIntegration.id}`,
                {
                    onSuccess: () => {
                        setIsDialogOpen(false);
                        reset();
                    },
                },
            );
        } else {
            post(`/projects/${project.id}/integrations`, {
                onSuccess: () => {
                    setIsDialogOpen(false);
                    reset();
                },
            });
        }
    }

    function handleDelete(integration: Integration) {
        if (confirm(`Are you sure you want to remove "${integration.name}"?`)) {
            router.delete(
                `/projects/${project.id}/integrations/${integration.id}`,
            );
        }
    }

    function handleToggle(integration: Integration) {
        router.post(
            `/projects/${project.id}/integrations/${integration.id}/toggle`,
        );
    }

    async function handleTest(integration: Integration) {
        setTestingId(integration.id);
        setTestResult(null);
        setTestingIntegrationName(integration.name);

        try {
            const response = await axios.post<TestDebugResult>(
                `/projects/${project.id}/integrations/${integration.id}/test`,
                {},
                {
                    headers: {
                        'X-CSRF-TOKEN': csrf_token,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            );

            const result = response.data;
            setTestResult({
                id: integration.id,
                success: result.success,
                message: result.message,
            });
            setDebugResult(result);
            setDebugDialogOpen(true);
        } catch (error) {
            let message = 'Failed to test connection';
            if (axios.isAxiosError(error) && error.response?.data?.message) {
                message = error.response.data.message;
            }
            setTestResult({
                id: integration.id,
                success: false,
                message,
            });
            setDebugResult(null);
        } finally {
            setTestingId(null);
        }
    }

    function handleTypeChange(value: string) {
        const type = availableTypes.find((t) => t.value === value);
        setData({
            ...data,
            type: value,
            name: type?.label ?? '',
        });
    }

    function getTypeIcon(type: string) {
        switch (type) {
            case 'webhook':
                return <Webhook className="h-5 w-5" />;
            case 'wordpress':
                return <Globe className="h-5 w-5" />;
            default:
                return <Globe className="h-5 w-5" />;
        }
    }

    function getTypeLabel(type: string) {
        return availableTypes.find((t) => t.value === type)?.label ?? type;
    }

    function renderCredentialFields() {
        if (!selectedType) return null;

        return Object.entries(selectedType.credentials).map(([key, field]) => (
            <div key={key} className="space-y-2">
                <Label htmlFor={key}>
                    {field.label}
                    {editingIntegration && field.type === 'password' && (
                        <span className="ml-2 text-xs text-muted-foreground">
                            (leave blank to keep current)
                        </span>
                    )}
                </Label>
                <Input
                    id={key}
                    type={field.type === 'password' ? 'password' : 'text'}
                    value={
                        ((data as Record<string, unknown>)[key] as string) ?? ''
                    }
                    onChange={(e) =>
                        setData(
                            key as keyof typeof data,
                            e.target.value as never,
                        )
                    }
                    placeholder={
                        editingIntegration && field.type === 'password'
                            ? '••••••••'
                            : undefined
                    }
                    required={!editingIntegration && field.required}
                />
                <InputError message={(errors as Record<string, string>)[key]} />
            </div>
        ));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Integrations - ${project.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Integrations</h1>
                        <p className="text-muted-foreground">
                            Publish your content to external platforms
                        </p>
                    </div>
                    <Button onClick={openAddDialog}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Integration
                    </Button>
                </div>

                {integrations.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Webhook className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-medium">
                                No integrations configured
                            </h3>
                            <p className="mb-4 text-center text-muted-foreground">
                                Add an integration to automatically publish your
                                articles to external platforms.
                            </p>
                            <Button onClick={openAddDialog}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add your first integration
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {integrations.map((integration) => (
                            <Card key={integration.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            {getTypeIcon(integration.type)}
                                            <div>
                                                <CardTitle className="text-base">
                                                    {integration.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    {getTypeLabel(
                                                        integration.type,
                                                    )}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {testResult?.id ===
                                                integration.id && (
                                                <Badge
                                                    variant={
                                                        testResult.success
                                                            ? 'default'
                                                            : 'destructive'
                                                    }
                                                    className="gap-1"
                                                >
                                                    {testResult.success ? (
                                                        <CheckCircle className="h-3 w-3" />
                                                    ) : (
                                                        <XCircle className="h-3 w-3" />
                                                    )}
                                                    {testResult.message}
                                                </Badge>
                                            )}
                                            {!integration.is_active && (
                                                <Badge variant="secondary">
                                                    Disabled
                                                </Badge>
                                            )}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                    >
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            handleTest(
                                                                integration,
                                                            )
                                                        }
                                                        disabled={
                                                            testingId ===
                                                            integration.id
                                                        }
                                                    >
                                                        {testingId ===
                                                        integration.id ? (
                                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Send className="mr-2 h-4 w-4" />
                                                        )}
                                                        Test Connection
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            openEditDialog(
                                                                integration,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            handleToggle(
                                                                integration,
                                                            )
                                                        }
                                                    >
                                                        <Power className="mr-2 h-4 w-4" />
                                                        {integration.is_active
                                                            ? 'Disable'
                                                            : 'Enable'}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            handleDelete(
                                                                integration,
                                                            )
                                                        }
                                                        className="text-destructive"
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Remove
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                        <span>
                                            {integration.publications_count}{' '}
                                            publications
                                        </span>
                                        {integration.last_connected_at && (
                                            <span>
                                                Last connected:{' '}
                                                {new Date(
                                                    integration.last_connected_at,
                                                ).toLocaleDateString()}
                                            </span>
                                        )}
                                        <span
                                            className={`flex items-center gap-1 ${integration.has_credentials ? 'text-green-600' : 'text-yellow-600'}`}
                                        >
                                            {integration.has_credentials ? (
                                                <>
                                                    <CheckCircle className="h-3 w-3" />
                                                    Configured
                                                </>
                                            ) : (
                                                <>
                                                    <XCircle className="h-3 w-3" />
                                                    Needs configuration
                                                </>
                                            )}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>
                            {editingIntegration
                                ? 'Edit Integration'
                                : 'Add Integration'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingIntegration
                                ? 'Update your integration settings.'
                                : 'Configure a new integration for publishing.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        {!editingIntegration && (
                            <div className="space-y-2">
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={handleTypeChange}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select integration type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                                disabled={!type.is_available}
                                            >
                                                <div className="flex items-center gap-2">
                                                    {type.label}
                                                    {!type.is_available && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            Coming Soon
                                                        </Badge>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedType && (
                                    <p className="text-sm text-muted-foreground">
                                        {selectedType.description}
                                    </p>
                                )}
                                <InputError message={errors.type} />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="name">Display Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="My Webhook"
                            />
                            <InputError message={errors.name} />
                        </div>

                        {(selectedType || editingIntegration) &&
                            renderCredentialFields()}

                        {data.type === 'webhook' && (
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="timeout">
                                        Timeout (seconds)
                                    </Label>
                                    <Input
                                        id="timeout"
                                        type="number"
                                        min={5}
                                        max={120}
                                        value={data.timeout}
                                        onChange={(e) =>
                                            setData(
                                                'timeout',
                                                parseInt(e.target.value) || 30,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="retry_times">
                                        Retry Attempts
                                    </Label>
                                    <Input
                                        id="retry_times"
                                        type="number"
                                        min={0}
                                        max={5}
                                        value={data.retry_times}
                                        onChange={(e) =>
                                            setData(
                                                'retry_times',
                                                parseInt(e.target.value) || 3,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}

                        <div className="flex items-center space-x-2">
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked)
                                }
                            />
                            <Label htmlFor="is_active">Active</Label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editingIntegration
                                    ? 'Update'
                                    : 'Add Integration'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Debug Dialog */}
            <Dialog open={debugDialogOpen} onOpenChange={setDebugDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-hidden sm:max-w-[800px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ArrowDownUp className="h-5 w-5" />
                            Test Connection: {testingIntegrationName}
                        </DialogTitle>
                        <DialogDescription>
                            {debugResult && (
                                <Badge
                                    variant={
                                        debugResult.success
                                            ? 'default'
                                            : 'destructive'
                                    }
                                    className="mt-2"
                                >
                                    {debugResult.success ? (
                                        <CheckCircle className="mr-1 h-3 w-3" />
                                    ) : (
                                        <XCircle className="mr-1 h-3 w-3" />
                                    )}
                                    {debugResult.message}
                                </Badge>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {debugResult && (
                        <Tabs defaultValue="request" className="w-full">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="request">
                                    Request
                                </TabsTrigger>
                                <TabsTrigger value="response">
                                    Response
                                    {debugResult.response.status_code && (
                                        <Badge
                                            variant={
                                                debugResult.response
                                                    .status_code < 400
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                            className="ml-2"
                                        >
                                            {debugResult.response.status_code}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent
                                value="request"
                                className="max-h-[50vh] overflow-y-auto"
                            >
                                <div className="space-y-4">
                                    <DebugSection title="URL">
                                        <code className="block rounded bg-muted px-3 py-2 text-sm">
                                            <span className="font-semibold text-blue-600 dark:text-blue-400">
                                                {debugResult.request.method}
                                            </span>{' '}
                                            {debugResult.request.url}
                                        </code>
                                    </DebugSection>

                                    <DebugSection title="Headers">
                                        <HeadersTable
                                            headers={
                                                debugResult.request.headers
                                            }
                                        />
                                    </DebugSection>

                                    <DebugSection title="Body">
                                        <JsonViewer
                                            data={debugResult.request.body}
                                        />
                                    </DebugSection>
                                </div>
                            </TabsContent>

                            <TabsContent
                                value="response"
                                className="max-h-[50vh] overflow-y-auto"
                            >
                                <div className="space-y-4">
                                    <DebugSection title="Status">
                                        <code className="block rounded bg-muted px-3 py-2 text-sm">
                                            <StatusBadge
                                                code={
                                                    debugResult.response
                                                        .status_code
                                                }
                                                text={
                                                    debugResult.response
                                                        .status_text
                                                }
                                            />
                                        </code>
                                    </DebugSection>

                                    {debugResult.response.headers && (
                                        <DebugSection title="Headers">
                                            <HeadersTable
                                                headers={
                                                    debugResult.response.headers
                                                }
                                            />
                                        </DebugSection>
                                    )}

                                    <DebugSection title="Body">
                                        <JsonViewer
                                            data={debugResult.response.body}
                                        />
                                    </DebugSection>
                                </div>
                            </TabsContent>
                        </Tabs>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDebugDialogOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

// Helper Components for Debug View

function DebugSection({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    const [isOpen, setIsOpen] = useState(true);

    return (
        <div className="rounded-lg border">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm font-medium hover:bg-muted/50"
            >
                {title}
                {isOpen ? (
                    <ChevronDown className="h-4 w-4" />
                ) : (
                    <ChevronRight className="h-4 w-4" />
                )}
            </button>
            {isOpen && <div className="border-t px-3 py-2">{children}</div>}
        </div>
    );
}

function HeadersTable({ headers }: { headers: Record<string, string> }) {
    const entries = Object.entries(headers);

    if (entries.length === 0) {
        return <p className="text-sm text-muted-foreground">No headers</p>;
    }

    return (
        <div className="overflow-hidden rounded border">
            <table className="w-full text-sm">
                <tbody className="divide-y">
                    {entries.map(([key, value]) => (
                        <tr key={key} className="hover:bg-muted/50">
                            <td className="px-3 py-1.5 font-mono text-xs font-medium whitespace-nowrap text-muted-foreground">
                                {key}
                            </td>
                            <td className="px-3 py-1.5 font-mono text-xs break-all">
                                {value}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function JsonViewer({ data }: { data: unknown }) {
    const [copied, setCopied] = useState(false);

    if (data === null || data === undefined) {
        return <p className="text-sm text-muted-foreground">No data</p>;
    }

    const jsonString = JSON.stringify(data, null, 2);

    function handleCopy() {
        navigator.clipboard.writeText(jsonString);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <div className="relative">
            <button
                onClick={handleCopy}
                className="absolute top-2 right-2 rounded p-1 hover:bg-muted"
                title="Copy to clipboard"
            >
                <Copy className="h-4 w-4" />
            </button>
            {copied && (
                <span className="absolute top-2 right-8 text-xs text-green-600">
                    Copied!
                </span>
            )}
            <pre className="max-h-[300px] overflow-auto rounded bg-muted p-3 font-mono text-xs">
                <code>{jsonString}</code>
            </pre>
        </div>
    );
}

function StatusBadge({
    code,
    text,
}: {
    code: number | null;
    text: string | null;
}) {
    if (code === null) {
        return <span className="text-muted-foreground">No response</span>;
    }

    let colorClass = 'text-gray-600';
    if (code >= 200 && code < 300) {
        colorClass = 'text-green-600 dark:text-green-400';
    } else if (code >= 400 && code < 500) {
        colorClass = 'text-yellow-600 dark:text-yellow-400';
    } else if (code >= 500) {
        colorClass = 'text-red-600 dark:text-red-400';
    }

    return (
        <span className={colorClass}>
            <span className="font-semibold">{code}</span>
            {text && <span className="ml-2">{text}</span>}
        </span>
    );
}
