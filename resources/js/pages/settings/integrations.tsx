import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Check, CheckCircle, Loader2, XCircle, Youtube } from 'lucide-react';
import { toast } from 'sonner';

interface Settings {
    has_youtube_api_key: boolean;
}

interface Props {
    settings: Settings;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Integrations',
        href: '/settings/integrations',
    },
];

export default function Integrations({ settings }: Props) {
    const [isTesting, setIsTesting] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        youtube_api_key: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Integration settings updated successfully');
                setData('youtube_api_key', '');
            },
        });
    }

    async function handleTestYouTube() {
        setIsTesting(true);
        try {
            const response = await axios.post(
                '/settings/integrations/test-youtube',
            );
            if (response.data.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data.error);
            }
        } catch {
            toast.error('Failed to test YouTube connection');
        } finally {
            setIsTesting(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Integrations"
                        description="Connect third-party services to enhance your content"
                    />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* YouTube Integration */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Youtube className="h-4 w-4" />
                                    YouTube
                                    {settings.has_youtube_api_key ? (
                                        <span className="ml-2 flex items-center gap-1 text-xs font-normal text-green-600">
                                            <CheckCircle className="h-3.5 w-3.5" />
                                            Connected
                                        </span>
                                    ) : (
                                        <span className="ml-2 flex items-center gap-1 text-xs font-normal text-muted-foreground">
                                            <XCircle className="h-3.5 w-3.5" />
                                            Not connected
                                        </span>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    Automatically find and embed relevant
                                    YouTube videos in your articles
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="youtube_api_key">
                                        YouTube Data API Key
                                        {settings.has_youtube_api_key && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                (leave blank to keep current)
                                            </span>
                                        )}
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="youtube_api_key"
                                            type="password"
                                            value={data.youtube_api_key}
                                            onChange={(e) =>
                                                setData(
                                                    'youtube_api_key',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                settings.has_youtube_api_key
                                                    ? '••••••••••••••••'
                                                    : 'AIza...'
                                            }
                                        />
                                        {settings.has_youtube_api_key && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={handleTestYouTube}
                                                disabled={isTesting}
                                            >
                                                {isTesting ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Check className="h-4 w-4" />
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Get your API key from the{' '}
                                        <a
                                            href="https://console.cloud.google.com/apis/credentials"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary underline"
                                        >
                                            Google Cloud Console
                                        </a>
                                        . Enable the YouTube Data API v3.
                                    </p>
                                    <InputError
                                        message={errors.youtube_api_key}
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
