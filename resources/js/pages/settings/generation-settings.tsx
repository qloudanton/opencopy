import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Image, Loader2, Sparkles, Type } from 'lucide-react';
import { toast } from 'sonner';

interface AiProvider {
    id: number;
    name: string;
    provider: string;
    model: string;
}

interface Settings {
    default_text_provider_id: number | null;
    default_image_provider_id: number | null;
}

interface Props {
    settings: Settings;
    textProviders: AiProvider[];
    imageProviders: AiProvider[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Account Defaults',
        href: '/settings/generation',
    },
];

export default function GenerationSettings({
    settings,
    textProviders,
    imageProviders,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        default_text_provider_id:
            settings.default_text_provider_id?.toString() || '',
        default_image_provider_id:
            settings.default_image_provider_id?.toString() || '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/settings/generation', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Generation settings updated successfully');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Account Defaults" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Account Defaults"
                        description="Set default AI providers for new projects. Individual projects can override these settings."
                    />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Default Providers */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Sparkles className="h-4 w-4" />
                                    Default AI Providers
                                </CardTitle>
                                <CardDescription>
                                    These providers are used when a project
                                    doesn't have its own provider configured
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="text_provider"
                                        className="flex items-center gap-2"
                                    >
                                        <Type className="h-4 w-4" />
                                        Text Generation
                                    </Label>
                                    {textProviders.length > 0 ? (
                                        <Select
                                            value={
                                                data.default_text_provider_id ||
                                                'none'
                                            }
                                            onValueChange={(value) =>
                                                setData(
                                                    'default_text_provider_id',
                                                    value === 'none'
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Use default provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    Use default provider
                                                </SelectItem>
                                                {textProviders.map(
                                                    (provider) => (
                                                        <SelectItem
                                                            key={provider.id}
                                                            value={provider.id.toString()}
                                                        >
                                                            {provider.name} (
                                                            {provider.model})
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No providers with text generation
                                            capability configured. Enable
                                            "Supports text" on an AI provider
                                            first.
                                        </p>
                                    )}
                                    <InputError
                                        message={
                                            errors.default_text_provider_id
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label
                                        htmlFor="image_provider"
                                        className="flex items-center gap-2"
                                    >
                                        <Image className="h-4 w-4" />
                                        Image Generation
                                    </Label>
                                    {imageProviders.length > 0 ? (
                                        <Select
                                            value={
                                                data.default_image_provider_id ||
                                                'none'
                                            }
                                            onValueChange={(value) =>
                                                setData(
                                                    'default_image_provider_id',
                                                    value === 'none'
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Use default provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    Use default provider
                                                </SelectItem>
                                                {imageProviders.map(
                                                    (provider) => (
                                                        <SelectItem
                                                            key={provider.id}
                                                            value={provider.id.toString()}
                                                        >
                                                            {provider.name} (
                                                            {provider.model})
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No providers with image generation
                                            capability configured. Enable
                                            "Supports images" on an AI provider
                                            first.
                                        </p>
                                    )}
                                    <InputError
                                        message={
                                            errors.default_image_provider_id
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Saving...
                                    </>
                                ) : (
                                    'Save Settings'
                                )}
                            </Button>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
