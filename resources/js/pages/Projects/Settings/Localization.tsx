import FieldLabel from '@/components/field-label';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import ProjectSettingsLayout from '@/layouts/project-settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';

interface Project {
    id: number;
    name: string;
    primary_language: string;
    target_region: string | null;
}

interface Props {
    project: Project;
}

const languageOptions = [
    { value: 'English', label: 'English' },
    { value: 'Spanish', label: 'Spanish' },
    { value: 'French', label: 'French' },
    { value: 'German', label: 'German' },
    { value: 'Italian', label: 'Italian' },
    { value: 'Portuguese', label: 'Portuguese' },
    { value: 'Dutch', label: 'Dutch' },
    { value: 'Polish', label: 'Polish' },
    { value: 'Russian', label: 'Russian' },
    { value: 'Japanese', label: 'Japanese' },
    { value: 'Chinese', label: 'Chinese' },
    { value: 'Korean', label: 'Korean' },
    { value: 'Arabic', label: 'Arabic' },
    { value: 'Hindi', label: 'Hindi' },
];

const regionOptions = [
    { value: 'global', label: 'Global (no specific region)' },
    { value: 'Australia', label: 'Australia' },
    { value: 'United States', label: 'United States' },
    { value: 'United Kingdom', label: 'United Kingdom' },
    { value: 'Canada', label: 'Canada' },
    { value: 'Germany', label: 'Germany' },
    { value: 'France', label: 'France' },
    { value: 'Spain', label: 'Spain' },
    { value: 'Italy', label: 'Italy' },
    { value: 'Netherlands', label: 'Netherlands' },
    { value: 'Brazil', label: 'Brazil' },
    { value: 'Mexico', label: 'Mexico' },
    { value: 'India', label: 'India' },
    { value: 'Japan', label: 'Japan' },
    { value: 'China', label: 'China' },
    { value: 'South Korea', label: 'South Korea' },
    { value: 'Singapore', label: 'Singapore' },
    { value: 'New Zealand', label: 'New Zealand' },
    { value: 'Ireland', label: 'Ireland' },
    { value: 'Sweden', label: 'Sweden' },
    { value: 'Norway', label: 'Norway' },
    { value: 'Denmark', label: 'Denmark' },
    { value: 'Finland', label: 'Finland' },
    { value: 'Switzerland', label: 'Switzerland' },
    { value: 'Austria', label: 'Austria' },
    { value: 'Belgium', label: 'Belgium' },
    { value: 'Poland', label: 'Poland' },
    { value: 'Portugal', label: 'Portugal' },
    { value: 'South Africa', label: 'South Africa' },
    { value: 'United Arab Emirates', label: 'United Arab Emirates' },
];

export default function Localization({ project }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Settings', href: `/projects/${project.id}/settings` },
        {
            title: 'Localization',
            href: `/projects/${project.id}/settings/localization`,
        },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            primary_language: project.primary_language,
            target_region: project.target_region || 'global',
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/projects/${project.id}/settings/localization`, {
            data: {
                ...data,
                target_region:
                    data.target_region === 'global' ? null : data.target_region,
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Localization Settings - ${project.name}`} />

            <ProjectSettingsLayout
                projectId={project.id}
                projectName={project.name}
            >
                <HeadingSmall
                    title="Localization"
                    description="Language and regional settings for your content"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="primary_language"
                                label="Content Language"
                                tooltip="The language your articles will be written in. This affects vocabulary, grammar, and idiomatic expressions used by the AI."
                            />
                            <Select
                                value={data.primary_language}
                                onValueChange={(value) =>
                                    setData('primary_language', value)
                                }
                            >
                                <SelectTrigger className="w-full sm:w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {languageOptions.map((lang) => (
                                        <SelectItem
                                            key={lang.value}
                                            value={lang.value}
                                        >
                                            {lang.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.primary_language} />
                        </div>

                        <div className="space-y-2">
                            <FieldLabel
                                htmlFor="target_region"
                                label="Target Region"
                                tooltip="The geographic area you're targeting. This helps the AI use region-specific examples, spelling (e.g., 'colour' vs 'color'), currencies, and cultural references."
                            />
                            <Select
                                value={data.target_region}
                                onValueChange={(value) =>
                                    setData('target_region', value)
                                }
                            >
                                <SelectTrigger className="w-full sm:w-64">
                                    <SelectValue placeholder="Select a region" />
                                </SelectTrigger>
                                <SelectContent>
                                    {regionOptions.map((region) => (
                                        <SelectItem
                                            key={region.value}
                                            value={region.value}
                                        >
                                            {region.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.target_region} />
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">
                                Saved
                            </p>
                        </Transition>
                    </div>
                </form>
            </ProjectSettingsLayout>
        </AppLayout>
    );
}
