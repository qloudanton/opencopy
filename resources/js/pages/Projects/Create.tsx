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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { axios } from '@/lib/axios';
import { toneOptions, wordCountOptions } from '@/lib/content-options';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { AxiosError } from 'axios';
import {
    ChevronRight,
    CircleHelp,
    Info,
    Loader2,
    Plus,
    Sparkles,
    X,
} from 'lucide-react';
import * as React from 'react';

interface KeywordSuggestion {
    keyword: string;
    search_intent: string;
    difficulty: 'low' | 'medium' | 'high';
    volume: 'low' | 'medium' | 'high';
    selected?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: 'Create', href: '/projects/create' },
];

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

const countryOptions = [
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
    { value: 'Israel', label: 'Israel' },
];

const MAX_AUDIENCES = 7;
const MAX_COMPETITORS = 7;
const MAX_KEYWORDS = 20;

const difficultyConfig: Record<string, { level: number; color: string }> = {
    low: { level: 1, color: 'bg-green-500' },
    medium: { level: 2, color: 'bg-yellow-500' },
    high: { level: 3, color: 'bg-red-500' },
};

const volumeConfig: Record<string, { level: number; color: string }> = {
    low: { level: 1, color: 'bg-slate-400' },
    medium: { level: 2, color: 'bg-slate-500' },
    high: { level: 3, color: 'bg-slate-600' },
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
                    className={`h-2 w-2 rounded-sm ${i < level ? color : 'bg-muted'}`}
                />
            ))}
        </div>
    );
}

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        website_url: '',
        description: '',
        primary_language: 'English',
        target_region: 'United States',
        target_audience: '',
        target_audiences: [] as string[],
        competitors: [] as string[],
        keywords: [] as Array<{
            keyword: string;
            search_intent: string;
            difficulty?: string;
            volume?: string;
        }>,
        // Content preferences
        default_tone: 'professional',
        default_word_count: 1500,
        include_cta: true,
        cta_product_name: '',
        cta_website_url: '',
        cta_action_text: 'Learn more',
    });

    const [activeTab, setActiveTab] = React.useState('business');
    const [analyzing, setAnalyzing] = React.useState(false);
    const [analyzeError, setAnalyzeError] = React.useState<string | null>(null);
    const [generatingAudiences, setGeneratingAudiences] = React.useState(false);
    const [audiencesError, setAudiencesError] = React.useState<string | null>(
        null,
    );
    const [generatingCompetitors, setGeneratingCompetitors] =
        React.useState(false);
    const [competitorsError, setCompetitorsError] = React.useState<
        string | null
    >(null);
    const [generatingKeywords, setGeneratingKeywords] = React.useState(false);
    const [keywordsError, setKeywordsError] = React.useState<string | null>(
        null,
    );
    const [keywordSuggestions, setKeywordSuggestions] = React.useState<
        KeywordSuggestion[]
    >([]);
    const [newAudience, setNewAudience] = React.useState('');
    const [newCompetitor, setNewCompetitor] = React.useState('');
    const [newKeyword, setNewKeyword] = React.useState('');
    const [ctaProductNameEdited, setCtaProductNameEdited] =
        React.useState(false);
    const [ctaWebsiteUrlEdited, setCtaWebsiteUrlEdited] = React.useState(false);

    // Auto-fill CTA fields based on project name and website URL
    React.useEffect(() => {
        if (data.name && !ctaProductNameEdited) {
            setData('cta_product_name', data.name);
        }
    }, [data.name, ctaProductNameEdited]);

    React.useEffect(() => {
        if (data.website_url && !ctaWebsiteUrlEdited) {
            setData('cta_website_url', data.website_url);
        }
    }, [data.website_url, ctaWebsiteUrlEdited]);

    async function handleAnalyzeWebsite() {
        if (!data.website_url) return;

        setAnalyzing(true);
        setAnalyzeError(null);

        try {
            const response = await axios.post('/projects/analyze-website', {
                url: data.website_url,
            });

            if (response.data.success) {
                const analysis = response.data.data;
                setData((prev) => ({
                    ...prev,
                    name: analysis.name || prev.name,
                    description: analysis.description || prev.description,
                    primary_language:
                        analysis.language || prev.primary_language,
                    target_region: analysis.country || prev.target_region,
                    target_audience:
                        analysis.target_audience || prev.target_audience,
                }));
            } else {
                setAnalyzeError(
                    response.data.message ||
                        'Failed to analyze website. Please try again.',
                );
            }
        } catch (error) {
            if (error instanceof AxiosError) {
                if (error.response?.status === 419) {
                    setAnalyzeError(
                        'Session expired. Please refresh the page and try again.',
                    );
                } else if (error.response?.data?.message) {
                    setAnalyzeError(error.response.data.message);
                } else {
                    setAnalyzeError(
                        'Failed to analyze website. Please try again.',
                    );
                }
            } else {
                setAnalyzeError('Network error. Please try again.');
            }
        } finally {
            setAnalyzing(false);
        }
    }

    async function handleGenerateAudiences() {
        if (!data.description) {
            setAudiencesError('Please fill in the business description first.');
            return;
        }

        setGeneratingAudiences(true);
        setAudiencesError(null);

        try {
            const response = await axios.post('/projects/generate-audiences', {
                description: data.description,
            });

            if (response.data.success) {
                const audiences = response.data.data as string[];
                setData((prev) => ({
                    ...prev,
                    target_audiences: [
                        ...new Set([...prev.target_audiences, ...audiences]),
                    ].slice(0, MAX_AUDIENCES),
                }));
            } else {
                setAudiencesError(
                    response.data.message ||
                        'Failed to generate audiences. Please try again.',
                );
            }
        } catch (error) {
            if (error instanceof AxiosError) {
                if (error.response?.status === 419) {
                    setAudiencesError(
                        'Session expired. Please refresh the page and try again.',
                    );
                } else if (error.response?.data?.message) {
                    setAudiencesError(error.response.data.message);
                } else {
                    setAudiencesError(
                        'Failed to generate audiences. Please try again.',
                    );
                }
            } else {
                setAudiencesError('Network error. Please try again.');
            }
        } finally {
            setGeneratingAudiences(false);
        }
    }

    async function handleGenerateCompetitors() {
        if (!data.description) {
            setCompetitorsError(
                'Please fill in the business description first.',
            );
            return;
        }

        setGeneratingCompetitors(true);
        setCompetitorsError(null);

        try {
            const response = await axios.post(
                '/projects/generate-competitors',
                {
                    description: data.description,
                },
            );

            if (response.data.success) {
                const competitors = response.data.data as string[];
                setData((prev) => ({
                    ...prev,
                    competitors: [
                        ...new Set([...prev.competitors, ...competitors]),
                    ].slice(0, MAX_COMPETITORS),
                }));
            } else {
                setCompetitorsError(
                    response.data.message ||
                        'Failed to generate competitors. Please try again.',
                );
            }
        } catch (error) {
            if (error instanceof AxiosError) {
                if (error.response?.status === 419) {
                    setCompetitorsError(
                        'Session expired. Please refresh the page and try again.',
                    );
                } else if (error.response?.data?.message) {
                    setCompetitorsError(error.response.data.message);
                } else {
                    setCompetitorsError(
                        'Failed to generate competitors. Please try again.',
                    );
                }
            } else {
                setCompetitorsError('Network error. Please try again.');
            }
        } finally {
            setGeneratingCompetitors(false);
        }
    }

    function addAudience() {
        if (!newAudience.trim()) return;
        if (data.target_audiences.length >= MAX_AUDIENCES) return;
        if (data.target_audiences.includes(newAudience.trim())) return;

        setData((prev) => ({
            ...prev,
            target_audiences: [...prev.target_audiences, newAudience.trim()],
        }));
        setNewAudience('');
    }

    function removeAudience(audience: string) {
        setData((prev) => ({
            ...prev,
            target_audiences: prev.target_audiences.filter(
                (a) => a !== audience,
            ),
        }));
    }

    function addCompetitor() {
        if (!newCompetitor.trim()) return;
        if (data.competitors.length >= MAX_COMPETITORS) return;
        if (data.competitors.includes(newCompetitor.trim())) return;

        setData((prev) => ({
            ...prev,
            competitors: [...prev.competitors, newCompetitor.trim()],
        }));
        setNewCompetitor('');
    }

    function removeCompetitor(competitor: string) {
        setData((prev) => ({
            ...prev,
            competitors: prev.competitors.filter((c) => c !== competitor),
        }));
    }

    async function handleGenerateKeywords() {
        if (!data.description) {
            setKeywordsError('Please fill in the business description first.');
            return;
        }

        setGeneratingKeywords(true);
        setKeywordsError(null);

        try {
            const response = await axios.post('/projects/generate-keywords', {
                description: data.description,
                target_audiences: data.target_audiences,
                competitors: data.competitors,
            });

            if (response.data.success) {
                const keywords = response.data.data as KeywordSuggestion[];
                // Mark all as selected by default in suggestions
                setKeywordSuggestions(
                    keywords.map((k) => ({ ...k, selected: true })),
                );
                // Also add them directly to the form data
                const existing = new Set(data.keywords.map((k) => k.keyword));
                const newKeywords = keywords
                    .filter((k) => !existing.has(k.keyword))
                    .map((k) => ({
                        keyword: k.keyword,
                        search_intent: k.search_intent,
                        difficulty: k.difficulty,
                        volume: k.volume,
                    }));
                setData((prev) => ({
                    ...prev,
                    keywords: [...prev.keywords, ...newKeywords].slice(
                        0,
                        MAX_KEYWORDS,
                    ),
                }));
            } else {
                setKeywordsError(
                    response.data.message ||
                        'Failed to generate keywords. Please try again.',
                );
            }
        } catch (error) {
            if (error instanceof AxiosError) {
                if (error.response?.status === 419) {
                    setKeywordsError(
                        'Session expired. Please refresh the page and try again.',
                    );
                } else if (error.response?.data?.message) {
                    setKeywordsError(error.response.data.message);
                } else {
                    setKeywordsError(
                        'Failed to generate keywords. Please try again.',
                    );
                }
            } else {
                setKeywordsError('Network error. Please try again.');
            }
        } finally {
            setGeneratingKeywords(false);
        }
    }

    function toggleKeywordSuggestion(keyword: string) {
        const suggestion = keywordSuggestions.find(
            (k) => k.keyword === keyword,
        );
        if (!suggestion) return;

        const isCurrentlySelected = suggestion.selected;

        // Update the suggestions UI
        setKeywordSuggestions((prev) =>
            prev.map((k) =>
                k.keyword === keyword ? { ...k, selected: !k.selected } : k,
            ),
        );

        // Update the form data accordingly
        if (isCurrentlySelected) {
            // Remove from form data
            setData((prev) => ({
                ...prev,
                keywords: prev.keywords.filter((k) => k.keyword !== keyword),
            }));
        } else {
            // Add to form data
            setData((prev) => ({
                ...prev,
                keywords: [
                    ...prev.keywords,
                    {
                        keyword: suggestion.keyword,
                        search_intent: suggestion.search_intent,
                        difficulty: suggestion.difficulty,
                        volume: suggestion.volume,
                    },
                ].slice(0, MAX_KEYWORDS),
            }));
        }
    }

    function addSelectedKeywords() {
        const selected = keywordSuggestions
            .filter((k) => k.selected)
            .map((k) => ({
                keyword: k.keyword,
                search_intent: k.search_intent,
                difficulty: k.difficulty,
                volume: k.volume,
            }));

        const existing = new Set(data.keywords.map((k) => k.keyword));
        const newKeywords = selected.filter((k) => !existing.has(k.keyword));

        setData((prev) => ({
            ...prev,
            keywords: [...prev.keywords, ...newKeywords].slice(0, MAX_KEYWORDS),
        }));
        setKeywordSuggestions([]);
    }

    function addKeyword() {
        if (!newKeyword.trim()) return;
        if (data.keywords.length >= MAX_KEYWORDS) return;
        if (data.keywords.some((k) => k.keyword === newKeyword.trim())) return;

        setData((prev) => ({
            ...prev,
            keywords: [
                ...prev.keywords,
                { keyword: newKeyword.trim(), search_intent: 'informational' },
            ],
        }));
        setNewKeyword('');
    }

    function removeKeyword(keyword: string) {
        setData((prev) => ({
            ...prev,
            keywords: prev.keywords.filter((k) => k.keyword !== keyword),
        }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/projects');
    }

    function canProceedToAudience() {
        return data.name.trim() !== '';
    }

    function canProceedToKeywords() {
        return canProceedToAudience();
    }

    function canProceedToContent() {
        return canProceedToKeywords();
    }

    function handleNextTab() {
        if (activeTab === 'business' && canProceedToAudience()) {
            setActiveTab('audience');
        } else if (activeTab === 'audience' && canProceedToKeywords()) {
            setActiveTab('keywords');
        } else if (activeTab === 'keywords' && canProceedToContent()) {
            setActiveTab('content');
        }
    }

    function handlePreviousTab() {
        if (activeTab === 'content') {
            setActiveTab('keywords');
        } else if (activeTab === 'keywords') {
            setActiveTab('audience');
        } else if (activeTab === 'audience') {
            setActiveTab('business');
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Project" />
            <div className="mx-auto flex h-full max-w-5xl flex-1 flex-col gap-6 p-4 py-8">
                <form onSubmit={handleSubmit}>
                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                        {/* Custom Stepper with Arrows */}
                        <div className="mx-auto mb-8 flex max-w-2xl items-center justify-center">
                            {[
                                {
                                    value: 'business',
                                    label: 'Business',
                                    canAccess: true,
                                },
                                {
                                    value: 'audience',
                                    label: 'Audience',
                                    canAccess: canProceedToAudience(),
                                },
                                {
                                    value: 'keywords',
                                    label: 'Keywords',
                                    canAccess: canProceedToKeywords(),
                                },
                                {
                                    value: 'content',
                                    label: 'Content',
                                    canAccess: canProceedToContent(),
                                },
                            ].map((step, index, arr) => (
                                <React.Fragment key={step.value}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            step.canAccess &&
                                            setActiveTab(step.value)
                                        }
                                        disabled={!step.canAccess}
                                        className={cn(
                                            'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all',
                                            activeTab === step.value
                                                ? 'bg-primary text-primary-foreground shadow-md'
                                                : step.canAccess
                                                  ? 'bg-muted text-foreground hover:bg-muted/80'
                                                  : 'cursor-not-allowed bg-muted/50 text-muted-foreground',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                                                activeTab === step.value
                                                    ? 'bg-primary-foreground/20 text-primary-foreground'
                                                    : step.canAccess
                                                      ? 'bg-foreground/10 text-foreground'
                                                      : 'bg-muted-foreground/20 text-muted-foreground',
                                            )}
                                        >
                                            {index + 1}
                                        </span>
                                        {step.label}
                                    </button>
                                    {index < arr.length - 1 && (
                                        <ChevronRight className="mx-2 h-5 w-5 text-muted-foreground/50" />
                                    )}
                                </React.Fragment>
                            ))}
                        </div>

                        <TabsContent value="business">
                            <div className="mx-auto max-w-3xl space-y-6">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold">
                                        About your business
                                    </h1>
                                    <p className="mt-2 text-muted-foreground">
                                        Provide your business information to
                                        personalize content generation and SEO
                                        strategies
                                    </p>
                                </div>

                                <Card>
                                    <CardHeader className="sr-only">
                                        <CardTitle>Business Details</CardTitle>
                                        <CardDescription>
                                            Enter your business information
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6 pt-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="website_url">
                                                Website URL
                                            </Label>
                                            <Input
                                                id="website_url"
                                                type="url"
                                                value={data.website_url}
                                                onChange={(e) =>
                                                    setData(
                                                        'website_url',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="https://www.example.com"
                                                className="bg-muted/50"
                                            />
                                            <InputError
                                                message={errors.website_url}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Business name
                                            </Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) =>
                                                    setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Acme Inc"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="primary_language">
                                                    Language
                                                </Label>
                                                <Select
                                                    value={
                                                        data.primary_language
                                                    }
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'primary_language',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger id="primary_language">
                                                        <SelectValue placeholder="Select language" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {languageOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        errors.primary_language
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="target_region">
                                                    Country
                                                </Label>
                                                <Select
                                                    value={data.target_region}
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'target_region',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger id="target_region">
                                                        <SelectValue placeholder="Select country" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {countryOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        errors.target_region
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor="description">
                                                    Description
                                                </Label>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={
                                                        handleAnalyzeWebsite
                                                    }
                                                    disabled={
                                                        analyzing ||
                                                        !data.website_url
                                                    }
                                                >
                                                    {analyzing ? (
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Sparkles className="mr-2 h-4 w-4" />
                                                    )}
                                                    {analyzing
                                                        ? 'Analyzing...'
                                                        : 'Autocomplete With AI'}
                                                </Button>
                                            </div>
                                            <Textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) =>
                                                    setData(
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Describe your business, products, services, and target audience..."
                                                rows={8}
                                                className="resize-none"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                This description helps the AI
                                                generate more relevant and
                                                accurate content for your
                                                business.
                                            </p>
                                            <InputError
                                                message={errors.description}
                                            />
                                            {analyzeError && (
                                                <p className="text-sm text-destructive">
                                                    {analyzeError}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex justify-end pt-4">
                                            <Button
                                                type="button"
                                                onClick={handleNextTab}
                                                disabled={
                                                    !canProceedToAudience()
                                                }
                                                size="lg"
                                            >
                                                Continue
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        <TabsContent value="audience">
                            <div className="mx-auto max-w-3xl space-y-6">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold">
                                        Define your Target Audience and
                                        Competitors
                                    </h1>
                                    <p className="mt-2 text-muted-foreground">
                                        Understanding your audience and
                                        competition ensures we generate the most
                                        effective keywords
                                    </p>
                                </div>

                                {/* Target Audiences */}
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-lg">
                                                    Target Audiences
                                                </CardTitle>
                                                <Badge variant="secondary">
                                                    {
                                                        data.target_audiences
                                                            .length
                                                    }
                                                    /{MAX_AUDIENCES}
                                                </Badge>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={
                                                    handleGenerateAudiences
                                                }
                                                disabled={
                                                    generatingAudiences ||
                                                    !data.description
                                                }
                                            >
                                                {generatingAudiences ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Sparkles className="mr-2 h-4 w-4" />
                                                )}
                                                Autocomplete With AI
                                            </Button>
                                        </div>
                                        <CardDescription>
                                            Enter your target audience groups to
                                            create relevant content. Better
                                            audience understanding improves
                                            results
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex gap-2">
                                            <Input
                                                value={newAudience}
                                                onChange={(e) =>
                                                    setNewAudience(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter your target audience groups (e.g., Developers, Project Managers)"
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        addAudience();
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="button"
                                                onClick={addAudience}
                                                disabled={
                                                    !newAudience.trim() ||
                                                    data.target_audiences
                                                        .length >= MAX_AUDIENCES
                                                }
                                            >
                                                Add
                                            </Button>
                                        </div>

                                        {data.target_audiences.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {data.target_audiences.map(
                                                    (audience) => (
                                                        <Badge
                                                            key={audience}
                                                            variant="secondary"
                                                            className="gap-1 py-2 pr-2 pl-3 text-sm"
                                                        >
                                                            {audience}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeAudience(
                                                                        audience,
                                                                    )
                                                                }
                                                                className="ml-1 rounded-full p-0.5 hover:bg-muted-foreground/20"
                                                            >
                                                                <X className="h-3 w-3" />
                                                            </button>
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}

                                        {audiencesError && (
                                            <p className="text-sm text-destructive">
                                                {audiencesError}
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.target_audiences}
                                        />
                                    </CardContent>
                                </Card>

                                {/* Competitors */}
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-lg">
                                                    Competitors
                                                </CardTitle>
                                                <Badge variant="secondary">
                                                    {data.competitors.length}/
                                                    {MAX_COMPETITORS}
                                                </Badge>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={
                                                    handleGenerateCompetitors
                                                }
                                                disabled={
                                                    generatingCompetitors ||
                                                    !data.description
                                                }
                                            >
                                                {generatingCompetitors ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Sparkles className="mr-2 h-4 w-4" />
                                                )}
                                                Autocomplete With AI
                                            </Button>
                                        </div>
                                        <CardDescription>
                                            Enter competitors to discover the
                                            SEO keywords they rank for. Bigger
                                            competitors provide more valuable
                                            insights
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex gap-2">
                                            <Input
                                                value={newCompetitor}
                                                onChange={(e) =>
                                                    setNewCompetitor(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter competitor URLs or company names (e.g. https://revid.ai or revid.ai)"
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        addCompetitor();
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="button"
                                                onClick={addCompetitor}
                                                disabled={
                                                    !newCompetitor.trim() ||
                                                    data.competitors.length >=
                                                        MAX_COMPETITORS
                                                }
                                            >
                                                Add
                                            </Button>
                                        </div>

                                        {data.competitors.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {data.competitors.map(
                                                    (competitor) => (
                                                        <Badge
                                                            key={competitor}
                                                            variant="secondary"
                                                            className="gap-1 py-2 pr-2 pl-3 text-sm"
                                                        >
                                                            <img
                                                                src={`https://www.google.com/s2/favicons?domain=${competitor}&sz=16`}
                                                                alt=""
                                                                className="h-4 w-4"
                                                                onError={(
                                                                    e,
                                                                ) => {
                                                                    e.currentTarget.style.display =
                                                                        'none';
                                                                }}
                                                            />
                                                            {competitor}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeCompetitor(
                                                                        competitor,
                                                                    )
                                                                }
                                                                className="ml-1 rounded-full p-0.5 hover:bg-muted-foreground/20"
                                                            >
                                                                <X className="h-3 w-3" />
                                                            </button>
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}

                                        {competitorsError && (
                                            <p className="text-sm text-destructive">
                                                {competitorsError}
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.competitors}
                                        />
                                    </CardContent>
                                </Card>

                                <div className="flex justify-between pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handlePreviousTab}
                                        size="lg"
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleNextTab}
                                        size="lg"
                                    >
                                        Continue
                                    </Button>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="keywords">
                            <div className="space-y-6">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold">
                                        Choose Your Keywords
                                    </h1>
                                    <p className="mt-2 text-muted-foreground">
                                        Select keywords to target for content
                                        generation. AI will suggest relevant
                                        keywords based on your business.
                                    </p>
                                </div>

                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-lg">
                                                    Keywords
                                                </CardTitle>
                                                <Badge variant="secondary">
                                                    {data.keywords.length}/
                                                    {MAX_KEYWORDS}
                                                </Badge>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleGenerateKeywords}
                                                disabled={
                                                    generatingKeywords ||
                                                    !data.description
                                                }
                                            >
                                                {generatingKeywords ? (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Sparkles className="mr-2 h-4 w-4" />
                                                )}
                                                Suggest With AI
                                            </Button>
                                        </div>
                                        <CardDescription>
                                            We focus on long-tail keywords with
                                            lower competition for quick ranking
                                            wins. Higher priority = better
                                            opportunity.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* AI Suggestions */}
                                        {keywordSuggestions.length > 0 && (
                                            <div className="space-y-3 rounded-lg border p-4">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-sm font-medium">
                                                        AI Suggestions
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={
                                                            addSelectedKeywords
                                                        }
                                                        disabled={
                                                            !keywordSuggestions.some(
                                                                (k) =>
                                                                    k.selected,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="mr-1 h-4 w-4" />
                                                        Add Selected
                                                    </Button>
                                                </div>
                                                <table className="w-full">
                                                    <thead>
                                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                                            <th className="pr-2 pb-2 font-medium"></th>
                                                            <th className="pr-2 pb-2 font-medium">
                                                                Keyword
                                                            </th>
                                                            <th className="pr-2 pb-2 font-medium">
                                                                <span className="inline-flex items-center gap-1">
                                                                    Difficulty
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <CircleHelp className="h-3.5 w-3.5 cursor-help text-muted-foreground/70" />
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            AI
                                                                            estimate
                                                                            of
                                                                            how
                                                                            hard
                                                                            it
                                                                            is
                                                                            to
                                                                            rank
                                                                            for
                                                                            this
                                                                            keyword
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                </span>
                                                            </th>
                                                            <th className="pr-2 pb-2 font-medium">
                                                                <span className="inline-flex items-center gap-1">
                                                                    Volume
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <CircleHelp className="h-3.5 w-3.5 cursor-help text-muted-foreground/70" />
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            AI
                                                                            estimate
                                                                            of
                                                                            relative
                                                                            search
                                                                            volume
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                </span>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {keywordSuggestions.map(
                                                            (suggestion) => (
                                                                <tr
                                                                    key={
                                                                        suggestion.keyword
                                                                    }
                                                                    className="cursor-pointer border-b border-border/50 last:border-0 hover:bg-muted/50"
                                                                    onClick={() =>
                                                                        toggleKeywordSuggestion(
                                                                            suggestion.keyword,
                                                                        )
                                                                    }
                                                                >
                                                                    <td
                                                                        className="py-2 pr-2"
                                                                        onClick={(
                                                                            e,
                                                                        ) =>
                                                                            e.stopPropagation()
                                                                        }
                                                                    >
                                                                        <Checkbox
                                                                            checked={
                                                                                suggestion.selected
                                                                            }
                                                                            onCheckedChange={() =>
                                                                                toggleKeywordSuggestion(
                                                                                    suggestion.keyword,
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td className="py-2 pr-2 text-sm">
                                                                        {
                                                                            suggestion.keyword
                                                                        }
                                                                    </td>
                                                                    <td className="py-2 pr-2">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <MiniProgressBar
                                                                                level={
                                                                                    difficultyConfig[
                                                                                        suggestion
                                                                                            .difficulty
                                                                                    ]
                                                                                        ?.level ||
                                                                                    1
                                                                                }
                                                                                color={
                                                                                    difficultyConfig[
                                                                                        suggestion
                                                                                            .difficulty
                                                                                    ]
                                                                                        ?.color ||
                                                                                    'bg-slate-400'
                                                                                }
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                    <td className="py-2 pr-2">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <MiniProgressBar
                                                                                level={
                                                                                    volumeConfig[
                                                                                        suggestion
                                                                                            .volume
                                                                                    ]
                                                                                        ?.level ||
                                                                                    1
                                                                                }
                                                                                color={
                                                                                    volumeConfig[
                                                                                        suggestion
                                                                                            .volume
                                                                                    ]
                                                                                        ?.color ||
                                                                                    'bg-slate-400'
                                                                                }
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    * Difficulty and volume are
                                                    AI estimates to help
                                                    prioritize keywords. For
                                                    precise data, use a keyword
                                                    research tool.
                                                </p>
                                            </div>
                                        )}

                                        {/* Manual Input */}
                                        <div className="flex gap-2">
                                            <Input
                                                value={newKeyword}
                                                onChange={(e) =>
                                                    setNewKeyword(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter a keyword phrase"
                                                className="flex-1"
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        addKeyword();
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="button"
                                                onClick={addKeyword}
                                                disabled={
                                                    !newKeyword.trim() ||
                                                    data.keywords.length >=
                                                        MAX_KEYWORDS
                                                }
                                            >
                                                Add
                                            </Button>
                                        </div>

                                        {/* Selected Keywords */}
                                        {data.keywords.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {data.keywords.map((kw) => (
                                                    <Badge
                                                        key={kw.keyword}
                                                        variant="secondary"
                                                        className="gap-1 py-2 pr-2 pl-3 text-sm"
                                                    >
                                                        {kw.keyword}
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeKeyword(
                                                                    kw.keyword,
                                                                )
                                                            }
                                                            className="ml-1 rounded-full p-0.5 hover:bg-muted-foreground/20"
                                                        >
                                                            <X className="h-3 w-3" />
                                                        </button>
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}

                                        {keywordsError && (
                                            <p className="text-sm text-destructive">
                                                {keywordsError}
                                            </p>
                                        )}
                                        <InputError message={errors.keywords} />
                                    </CardContent>
                                </Card>

                                <div className="flex justify-between pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handlePreviousTab}
                                        size="lg"
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleNextTab}
                                        size="lg"
                                    >
                                        Continue
                                    </Button>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="content">
                            <div className="mx-auto max-w-3xl space-y-6">
                                <div className="text-center">
                                    <h1 className="text-2xl font-bold">
                                        Content Preferences
                                    </h1>
                                    <p className="mt-2 text-muted-foreground">
                                        Configure how your articles will be
                                        generated. These settings apply to all
                                        articles in this project.
                                    </p>
                                </div>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">
                                            Writing Style
                                        </CardTitle>
                                        <CardDescription>
                                            Set the default tone and length for
                                            generated articles
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="default_tone">
                                                    Tone of Voice
                                                </Label>
                                                <Select
                                                    value={data.default_tone}
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'default_tone',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger id="default_tone">
                                                        <SelectValue placeholder="Select tone" />
                                                    </SelectTrigger>
                                                    <SelectContent>
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
                                                <InputError
                                                    message={
                                                        errors.default_tone
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="default_word_count">
                                                    Target Word Count
                                                </Label>
                                                <Select
                                                    value={data.default_word_count.toString()}
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'default_word_count',
                                                            parseInt(value),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger id="default_word_count">
                                                        <SelectValue placeholder="Select length" />
                                                    </SelectTrigger>
                                                    <SelectContent>
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
                                                <InputError
                                                    message={
                                                        errors.default_word_count
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">
                                            Call to Action
                                        </CardTitle>
                                        <CardDescription>
                                            Optionally include a CTA in your
                                            articles to promote your product or
                                            service
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center space-x-2">
                                            <Checkbox
                                                id="include_cta"
                                                checked={data.include_cta}
                                                onCheckedChange={(checked) =>
                                                    setData(
                                                        'include_cta',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor="include_cta"
                                                className="cursor-pointer"
                                            >
                                                Include a call-to-action section
                                                in articles
                                            </Label>
                                        </div>

                                        {data.include_cta && (
                                            <div className="space-y-4 rounded-lg border p-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="cta_product_name">
                                                        Product/Service Name
                                                    </Label>
                                                    <Input
                                                        id="cta_product_name"
                                                        value={
                                                            data.cta_product_name
                                                        }
                                                        onChange={(e) => {
                                                            setCtaProductNameEdited(
                                                                true,
                                                            );
                                                            setData(
                                                                'cta_product_name',
                                                                e.target.value,
                                                            );
                                                        }}
                                                        placeholder="e.g., OpenCopy AI"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.cta_product_name
                                                        }
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="cta_website_url">
                                                        CTA Link URL
                                                    </Label>
                                                    <Input
                                                        id="cta_website_url"
                                                        type="url"
                                                        value={
                                                            data.cta_website_url
                                                        }
                                                        onChange={(e) => {
                                                            setCtaWebsiteUrlEdited(
                                                                true,
                                                            );
                                                            setData(
                                                                'cta_website_url',
                                                                e.target.value,
                                                            );
                                                        }}
                                                        placeholder="https://your-product.com/signup"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.cta_website_url
                                                        }
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="cta_action_text">
                                                        Button Text
                                                    </Label>
                                                    <Input
                                                        id="cta_action_text"
                                                        value={
                                                            data.cta_action_text
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'cta_action_text',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="e.g., Get Started Free, Learn More"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.cta_action_text
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/50">
                                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                                    <div className="text-sm text-blue-800 dark:text-blue-200">
                                        <p className="font-medium">
                                            More options available in Project
                                            Settings
                                        </p>
                                        <p className="mt-1 text-blue-700 dark:text-blue-300">
                                            After creating your project, you can
                                            configure featured images, internal
                                            linking, publishing integrations,
                                            and more.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex justify-between pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handlePreviousTab}
                                        size="lg"
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing || !data.name}
                                        size="lg"
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : 'Create Project'}
                                    </Button>
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>
                </form>
            </div>
        </AppLayout>
    );
}
