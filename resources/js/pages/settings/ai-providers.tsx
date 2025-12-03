import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import HeadingSmall from '@/components/heading-small';
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
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { MoreHorizontal, Plus, Star, Trash2 } from 'lucide-react';

interface AiProvider {
    id: number;
    provider: string;
    name: string;
    model: string;
    api_endpoint: string | null;
    is_default: boolean;
    is_active: boolean;
    supports_text: boolean;
    supports_image: boolean;
    has_api_key: boolean;
    created_at: string;
}

interface AvailableProvider {
    value: string;
    label: string;
    models: string[];
    requiresApiKey: boolean;
    supportsCustomEndpoint: boolean;
    defaultEndpoint?: string;
}

interface Props {
    providers: AiProvider[];
    availableProviders: AvailableProvider[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'AI Providers',
        href: '/settings/ai-providers',
    },
];

export default function AiProviders({ providers, availableProviders }: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingProvider, setEditingProvider] = useState<AiProvider | null>(
        null,
    );

    const { data, setData, post, put, processing, errors, reset } = useForm({
        provider: '',
        name: '',
        api_key: '',
        api_endpoint: '',
        model: '',
        is_default: false,
        is_active: true,
        supports_text: true,
        supports_image: false,
    });

    const selectedProviderConfig = availableProviders.find(
        (p) => p.value === data.provider,
    );

    function openAddDialog() {
        reset();
        setEditingProvider(null);
        setIsDialogOpen(true);
    }

    function openEditDialog(provider: AiProvider) {
        setEditingProvider(provider);
        setData({
            provider: provider.provider,
            name: provider.name,
            api_key: '',
            api_endpoint: provider.api_endpoint || '',
            model: provider.model,
            is_default: provider.is_default,
            is_active: provider.is_active,
            supports_text: provider.supports_text,
            supports_image: provider.supports_image,
        });
        setIsDialogOpen(true);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (editingProvider) {
            put(`/settings/ai-providers/${editingProvider.id}`, {
                onSuccess: () => {
                    setIsDialogOpen(false);
                    reset();
                },
            });
        } else {
            post('/settings/ai-providers', {
                onSuccess: () => {
                    setIsDialogOpen(false);
                    reset();
                },
            });
        }
    }

    function handleDelete(provider: AiProvider) {
        if (confirm(`Are you sure you want to remove "${provider.name}"?`)) {
            router.delete(`/settings/ai-providers/${provider.id}`);
        }
    }

    function handleSetDefault(provider: AiProvider) {
        router.post(`/settings/ai-providers/${provider.id}/default`);
    }

    function handleProviderChange(value: string) {
        const config = availableProviders.find((p) => p.value === value);
        setData({
            ...data,
            provider: value,
            name: config?.label || '',
            model: config?.models[0] || '',
            api_endpoint: config?.defaultEndpoint || '',
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Providers" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title="AI Providers"
                            description="Configure your AI providers for content generation"
                        />
                        <Button onClick={openAddDialog} size="sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Provider
                        </Button>
                    </div>

                    {providers.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <p className="mb-4 text-muted-foreground">
                                    No AI providers configured yet
                                </p>
                                <Button onClick={openAddDialog}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add your first provider
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-3">
                            {providers.map((provider) => (
                                <Card key={provider.id}>
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <CardTitle className="text-base">
                                                    {provider.name}
                                                </CardTitle>
                                                {provider.is_default && (
                                                    <Badge variant="secondary">
                                                        <Star className="mr-1 h-3 w-3" />
                                                        Default
                                                    </Badge>
                                                )}
                                                {!provider.is_active && (
                                                    <Badge variant="outline">
                                                        Disabled
                                                    </Badge>
                                                )}
                                            </div>
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
                                                            openEditDialog(
                                                                provider,
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </DropdownMenuItem>
                                                    {!provider.is_default && (
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleSetDefault(
                                                                    provider,
                                                                )
                                                            }
                                                        >
                                                            Set as default
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            handleDelete(
                                                                provider,
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
                                        <CardDescription>
                                            {provider.provider} /{' '}
                                            {provider.model}
                                            {provider.api_endpoint &&
                                                ` / ${provider.api_endpoint}`}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                            <span>
                                                API Key:{' '}
                                                {provider.has_api_key
                                                    ? 'Configured'
                                                    : 'Not set'}
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
                                {editingProvider
                                    ? 'Edit AI Provider'
                                    : 'Add AI Provider'}
                            </DialogTitle>
                            <DialogDescription>
                                {editingProvider
                                    ? 'Update your AI provider configuration.'
                                    : 'Configure a new AI provider for content generation.'}
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="provider">Provider</Label>
                                <Select
                                    value={data.provider}
                                    onValueChange={handleProviderChange}
                                    disabled={!!editingProvider}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableProviders.map((p) => (
                                            <SelectItem
                                                key={p.value}
                                                value={p.value}
                                            >
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.provider} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">Display Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="My OpenAI"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="model">Model</Label>
                                <Select
                                    value={data.model}
                                    onValueChange={(value) =>
                                        setData('model', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a model" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedProviderConfig?.models.map(
                                            (model) => (
                                                <SelectItem
                                                    key={model}
                                                    value={model}
                                                >
                                                    {model}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.model} />
                            </div>

                            {selectedProviderConfig?.requiresApiKey && (
                                <div className="space-y-2">
                                    <Label htmlFor="api_key">
                                        API Key
                                        {editingProvider && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                (leave blank to keep current)
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id="api_key"
                                        type="password"
                                        value={data.api_key}
                                        onChange={(e) =>
                                            setData('api_key', e.target.value)
                                        }
                                        placeholder={
                                            editingProvider
                                                ? '••••••••'
                                                : 'sk-...'
                                        }
                                    />
                                    <InputError message={errors.api_key} />
                                </div>
                            )}

                            {selectedProviderConfig?.supportsCustomEndpoint && (
                                <div className="space-y-2">
                                    <Label htmlFor="api_endpoint">
                                        API Endpoint
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="api_endpoint"
                                        value={data.api_endpoint}
                                        onChange={(e) =>
                                            setData(
                                                'api_endpoint',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            selectedProviderConfig.defaultEndpoint ||
                                            'https://api.example.com'
                                        }
                                    />
                                    <InputError message={errors.api_endpoint} />
                                </div>
                            )}

                            <div className="flex items-center justify-between">
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

                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="is_default"
                                        checked={data.is_default}
                                        onCheckedChange={(checked) =>
                                            setData('is_default', checked)
                                        }
                                    />
                                    <Label htmlFor="is_default">
                                        Set as default
                                    </Label>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-sm font-medium">
                                    Capabilities
                                </Label>
                                <div className="flex items-center gap-6">
                                    <div className="flex items-center space-x-2">
                                        <Switch
                                            id="supports_text"
                                            checked={data.supports_text}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'supports_text',
                                                    checked,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="supports_text"
                                            className="text-sm"
                                        >
                                            Supports text
                                        </Label>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Switch
                                            id="supports_image"
                                            checked={data.supports_image}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'supports_image',
                                                    checked,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="supports_image"
                                            className="text-sm"
                                        >
                                            Supports images
                                        </Label>
                                    </div>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Enable based on what this provider/model can
                                    generate
                                </p>
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
                                    {editingProvider
                                        ? 'Update'
                                        : 'Add Provider'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
