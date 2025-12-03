import { cn } from '@/lib/utils';
import {
    Bot,
    ChevronDown,
    ChevronUp,
    FileText,
    Link2,
    Loader2,
    Sparkles,
    Tags,
    Target,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from './ui/button';

interface SeoBreakdownCategory {
    score: number;
    max: number;
    details: Record<string, unknown>;
}

interface SeoBreakdown {
    keyword_optimization: SeoBreakdownCategory;
    content_structure: SeoBreakdownCategory;
    content_length: SeoBreakdownCategory;
    meta_quality: SeoBreakdownCategory;
    enrichment: SeoBreakdownCategory;
}

interface SeoScoreProps {
    score: number | null;
    breakdown?: SeoBreakdown;
    keyword?: string;
    onImprove?: (improvementType: string) => Promise<void>;
    isImproving?: boolean;
}

// Grade system for SEO scores
function getScoreGrade(score: number) {
    if (score >= 80)
        return {
            label: 'Excellent',
            description: 'Highly optimized for search',
            colorClass: 'text-green-600',
            bgClass: 'bg-green-500',
            ringClass: 'stroke-green-500',
        };
    if (score >= 60)
        return {
            label: 'Good',
            description: 'Well-optimized content',
            colorClass: 'text-lime-600',
            bgClass: 'bg-lime-500',
            ringClass: 'stroke-lime-500',
        };
    if (score >= 40)
        return {
            label: 'Needs Work',
            description: 'Consider improving key areas',
            colorClass: 'text-orange-500',
            bgClass: 'bg-orange-500',
            ringClass: 'stroke-orange-500',
        };
    return {
        label: 'Poor',
        description: 'Significant improvements needed',
        colorClass: 'text-red-500',
        bgClass: 'bg-red-500',
        ringClass: 'stroke-red-500',
    };
}

// Circular progress component
function CircularProgress({
    score,
    size = 140,
}: {
    score: number;
    size?: number;
}) {
    const strokeWidth = 10;
    const radius = (size - strokeWidth) / 2;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (score / 100) * circumference;
    const grade = getScoreGrade(score);

    return (
        <div className="flex flex-col items-center gap-2">
            <div className="relative" style={{ width: size, height: size }}>
                <svg
                    className="-rotate-90 transform"
                    width={size}
                    height={size}
                >
                    {/* Background circle */}
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        strokeWidth={strokeWidth}
                        className="fill-none stroke-muted"
                    />
                    {/* Progress circle */}
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        strokeWidth={strokeWidth}
                        strokeDasharray={circumference}
                        strokeDashoffset={offset}
                        strokeLinecap="round"
                        className={cn(
                            'fill-none transition-all duration-700 ease-out',
                            grade.ringClass,
                        )}
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-4xl font-bold tracking-tight">
                        {score}
                    </span>
                    <span className="text-sm text-muted-foreground">/100</span>
                </div>
            </div>
            <div className="text-center">
                <span className={cn('text-lg font-semibold', grade.colorClass)}>
                    {grade.label}
                </span>
                <p className="text-sm text-muted-foreground">
                    {grade.description}
                </p>
            </div>
        </div>
    );
}

// Category progress bar with metrics
function CategoryBar({
    label,
    score,
    max,
    icon: Icon,
    metrics,
}: {
    label: string;
    score: number;
    max: number;
    icon: React.ComponentType<{ className?: string }>;
    metrics?: string;
}) {
    const percentage = Math.round((score / max) * 100);
    const grade = getScoreGrade(percentage);

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                    <Icon className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium">{label}</span>
                </div>
                <span className={cn('font-semibold', grade.colorClass)}>
                    {score}/{max}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={cn(
                        'h-full rounded-full transition-all duration-500',
                        grade.bgClass,
                    )}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {metrics && (
                <p className="text-xs text-muted-foreground">{metrics}</p>
            )}
        </div>
    );
}

// Helper to format category metrics
function formatCategoryMetrics(
    categoryKey: string,
    details: Record<string, unknown>,
): string {
    switch (categoryKey) {
        case 'keyword_optimization': {
            const density = details.keyword_density as number;
            const checks = [
                details.keyword_in_title && 'title',
                details.keyword_in_meta && 'meta',
                details.keyword_in_h2 && 'H2',
                details.keyword_in_first_150_words && 'intro',
            ].filter(Boolean);
            const densityStr = density ? `${density.toFixed(1)}% density` : '';
            const checksStr =
                checks.length > 0
                    ? `In: ${checks.join(', ')}`
                    : 'Not in title/meta/headings';
            return [checksStr, densityStr].filter(Boolean).join(' · ');
        }
        case 'content_structure': {
            const h2 = details.h2_count as number;
            const h3 = details.h3_count as number;
            const features = [
                `${h2} H2${h2 !== 1 ? 's' : ''}`,
                `${h3} H3${h3 !== 1 ? 's' : ''}`,
                details.has_lists && 'lists',
                details.has_tables && 'tables',
                details.has_faq && 'FAQ',
            ].filter(Boolean);
            return features.join(' · ');
        }
        case 'content_length': {
            const wordCount = details.word_count as number;
            const target = details.target_word_count as number;
            const ratio = details.target_ratio as number;
            const status =
                ratio >= 0.9 && ratio <= 1.2
                    ? '✓ on target'
                    : ratio < 0.9
                      ? `${Math.round((1 - ratio) * 100)}% below target`
                      : `${Math.round((ratio - 1) * 100)}% above target`;
            return `${wordCount.toLocaleString()} words · Target: ${target.toLocaleString()} · ${status}`;
        }
        case 'meta_quality': {
            const titleLen = details.title_length as number;
            const metaLen = details.meta_description_length as number;
            const titleStatus =
                titleLen >= 50 && titleLen <= 60
                    ? '✓'
                    : titleLen < 50
                      ? 'too short'
                      : 'too long';
            const metaStatus =
                metaLen >= 150 && metaLen <= 160
                    ? '✓'
                    : metaLen < 150
                      ? 'too short'
                      : 'too long';
            return `Title: ${titleLen} chars (50-60 ${titleStatus}) · Meta: ${metaLen} chars (150-160 ${metaStatus})`;
        }
        case 'enrichment': {
            const features = [
                details.has_images ? '✓ images' : '✗ no images',
                details.has_links ? '✓ links' : '✗ no links',
            ];
            return features.join(' · ');
        }
        default:
            return '';
    }
}

// Quick wins - prioritized improvements
function QuickWins({
    breakdown,
    onImprove,
    isImproving,
}: {
    breakdown: SeoBreakdown;
    onImprove?: (improvementType: string) => Promise<void>;
    isImproving?: boolean;
}) {
    const [improvingType, setImprovingType] = useState<string | null>(null);

    const improvements: Array<{
        label: string;
        points: number;
        category: string;
        improvementType: string;
    }> = [];

    // Keyword optimization improvements
    if (!breakdown.keyword_optimization.details.keyword_in_title) {
        improvements.push({
            label: 'Add keyword to title',
            points: 10,
            category: 'keyword',
            improvementType: 'add_keyword_to_title',
        });
    }
    if (!breakdown.keyword_optimization.details.keyword_in_meta) {
        improvements.push({
            label: 'Add keyword to meta description',
            points: 8,
            category: 'keyword',
            improvementType: 'add_keyword_to_meta',
        });
    }
    if (!breakdown.keyword_optimization.details.keyword_in_first_150_words) {
        improvements.push({
            label: 'Mention keyword in first 150 words',
            points: 7,
            category: 'keyword',
            improvementType: 'add_keyword_to_intro',
        });
    }
    if (!breakdown.keyword_optimization.details.keyword_in_h2) {
        improvements.push({
            label: 'Include keyword in an H2 heading',
            points: 5,
            category: 'keyword',
            improvementType: 'add_keyword_to_h2',
        });
    }

    // Content structure improvements
    const h2Count = breakdown.content_structure.details.h2_count as number;
    if (h2Count < 3) {
        improvements.push({
            label: `Add ${3 - h2Count} more H2 heading${3 - h2Count > 1 ? 's' : ''}`,
            points: 4,
            category: 'structure',
            improvementType: 'add_h2_headings',
        });
    }
    if (!breakdown.content_structure.details.has_tables) {
        improvements.push({
            label: 'Add a comparison or data table',
            points: 4,
            category: 'structure',
            improvementType: 'add_table',
        });
    }
    if (!breakdown.content_structure.details.has_lists) {
        improvements.push({
            label: 'Add bullet or numbered lists',
            points: 4,
            category: 'structure',
            improvementType: 'add_lists',
        });
    }
    if (!breakdown.content_structure.details.has_faq) {
        improvements.push({
            label: 'Add an FAQ section',
            points: 4,
            category: 'structure',
            improvementType: 'add_faq_section',
        });
    }

    // Meta improvements
    const titleLength = breakdown.meta_quality.details.title_length as number;
    if (titleLength < 50 || titleLength > 60) {
        improvements.push({
            label:
                titleLength < 50
                    ? 'Make title longer (aim for 50-60 chars)'
                    : 'Shorten title (aim for 50-60 chars)',
            points: 3,
            category: 'meta',
            improvementType: 'optimize_title_length',
        });
    }
    const metaLength = breakdown.meta_quality.details
        .meta_description_length as number;
    if (metaLength < 150 || metaLength > 160) {
        improvements.push({
            label:
                metaLength < 150
                    ? 'Expand meta description (aim for 150-160 chars)'
                    : 'Shorten meta description (aim for 150-160 chars)',
            points: 3,
            category: 'meta',
            improvementType: 'optimize_meta_length',
        });
    }

    // Enrichment improvements (no AI support for these yet)
    if (!breakdown.enrichment.details.has_images) {
        improvements.push({
            label: 'Add images or image placeholders',
            points: 4,
            category: 'enrichment',
            improvementType: '', // No AI support
        });
    }
    if (!breakdown.enrichment.details.has_links) {
        improvements.push({
            label: 'Add internal or external links',
            points: 4,
            category: 'enrichment',
            improvementType: '', // No AI support
        });
    }

    // Sort by points and take top 5
    const topImprovements = improvements
        .sort((a, b) => b.points - a.points)
        .slice(0, 5);

    const handleImprove = async (improvementType: string) => {
        if (!onImprove || !improvementType) return;
        setImprovingType(improvementType);
        try {
            await onImprove(improvementType);
        } finally {
            setImprovingType(null);
        }
    };

    if (topImprovements.length === 0) {
        return (
            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
                <p className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                    <Sparkles className="h-4 w-4" />
                    All optimizations complete!
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <h4 className="flex items-center gap-2 text-sm font-semibold">
                <Sparkles className="h-4 w-4 text-amber-500" />
                Top Improvements
            </h4>
            <div className="space-y-2">
                {topImprovements.map((item, index) => (
                    <div
                        key={index}
                        className="flex items-center justify-between gap-2 rounded-lg border bg-card p-3 text-sm"
                    >
                        <span className="flex-1 text-muted-foreground">
                            {item.label}
                        </span>
                        <span className="font-medium text-green-600">
                            +{item.points} pts
                        </span>
                        {onImprove && item.improvementType && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 p-0"
                                onClick={() =>
                                    handleImprove(item.improvementType)
                                }
                                disabled={isImproving || improvingType !== null}
                                title="Fix with AI"
                            >
                                {improvingType === item.improvementType ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Bot className="h-4 w-4" />
                                )}
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

// Detailed checklist (collapsible)
function DetailedChecklist({ breakdown }: { breakdown: SeoBreakdown }) {
    const [isOpen, setIsOpen] = useState(false);

    const checks = [
        {
            label: 'Keyword in title',
            passed: breakdown.keyword_optimization.details
                .keyword_in_title as boolean,
        },
        {
            label: 'Keyword in meta description',
            passed: breakdown.keyword_optimization.details
                .keyword_in_meta as boolean,
        },
        {
            label: 'Keyword in first 150 words',
            passed: breakdown.keyword_optimization.details
                .keyword_in_first_150_words as boolean,
        },
        {
            label: 'Keyword in H2 heading',
            passed: breakdown.keyword_optimization.details
                .keyword_in_h2 as boolean,
        },
        {
            label: 'Good keyword density (0.5-2.5%)',
            passed:
                (breakdown.keyword_optimization.details
                    .keyword_density as number) >= 0.5 &&
                (breakdown.keyword_optimization.details
                    .keyword_density as number) <= 2.5,
        },
        {
            label: 'Has 3+ H2 headings',
            passed:
                (breakdown.content_structure.details.h2_count as number) >= 3,
        },
        {
            label: 'Has H3 subheadings',
            passed:
                (breakdown.content_structure.details.h3_count as number) >= 1,
        },
        {
            label: 'Uses bullet/numbered lists',
            passed: breakdown.content_structure.details.has_lists as boolean,
        },
        {
            label: 'Uses tables',
            passed: breakdown.content_structure.details.has_tables as boolean,
        },
        {
            label: 'Has FAQ section',
            passed: breakdown.content_structure.details.has_faq as boolean,
        },
        {
            label: 'Title length (50-60 chars)',
            passed:
                (breakdown.meta_quality.details.title_length as number) >= 50 &&
                (breakdown.meta_quality.details.title_length as number) <= 60,
        },
        {
            label: 'Meta description (150-160 chars)',
            passed:
                (breakdown.meta_quality.details
                    .meta_description_length as number) >= 150 &&
                (breakdown.meta_quality.details
                    .meta_description_length as number) <= 160,
        },
        {
            label: 'Has images',
            passed: breakdown.enrichment.details.has_images as boolean,
        },
        {
            label: 'Has links',
            passed: breakdown.enrichment.details.has_links as boolean,
        },
    ];

    const passedCount = checks.filter((c) => c.passed).length;

    return (
        <div className="border-t pt-4">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <span>
                    Detailed Checklist ({passedCount}/{checks.length} passed)
                </span>
                {isOpen ? (
                    <ChevronUp className="h-4 w-4" />
                ) : (
                    <ChevronDown className="h-4 w-4" />
                )}
            </button>
            {isOpen && (
                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                    {checks.map(({ label, passed }) => (
                        <div
                            key={label}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-2 py-1 text-sm',
                                passed
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <div
                                className={cn(
                                    'h-2 w-2 rounded-full',
                                    passed ? 'bg-green-500' : 'bg-muted',
                                )}
                            />
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// Main SEO Score component
export function SeoScore({
    score,
    breakdown,
    onImprove,
    isImproving,
}: SeoScoreProps) {
    if (score === null) {
        return (
            <div className="flex h-40 items-center justify-center text-muted-foreground">
                No SEO score available
            </div>
        );
    }

    const categories = [
        {
            key: 'keyword_optimization',
            label: 'Keyword Optimization',
            icon: Target,
            data: breakdown?.keyword_optimization,
        },
        {
            key: 'content_structure',
            label: 'Content Structure',
            icon: FileText,
            data: breakdown?.content_structure,
        },
        {
            key: 'content_length',
            label: 'Content Length',
            icon: FileText,
            data: breakdown?.content_length,
        },
        {
            key: 'meta_quality',
            label: 'Meta Quality',
            icon: Tags,
            data: breakdown?.meta_quality,
        },
        {
            key: 'enrichment',
            label: 'Enrichment',
            icon: Link2,
            data: breakdown?.enrichment,
        },
    ];

    return (
        <div className="space-y-6">
            {/* Main score display */}
            <div className="grid gap-6 md:grid-cols-2">
                {/* Left: Circular score */}
                <div className="flex items-center justify-center">
                    <CircularProgress score={score} />
                </div>

                {/* Right: Category breakdown */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold text-muted-foreground">
                        Score Breakdown
                    </h4>
                    <div className="space-y-4">
                        {categories.map(
                            ({ key, label, icon, data }) =>
                                data && (
                                    <CategoryBar
                                        key={key}
                                        label={label}
                                        score={data.score}
                                        max={data.max}
                                        icon={icon}
                                        metrics={formatCategoryMetrics(
                                            key,
                                            data.details,
                                        )}
                                    />
                                ),
                        )}
                    </div>
                </div>
            </div>

            {/* Quick wins */}
            {breakdown && (
                <QuickWins
                    breakdown={breakdown}
                    onImprove={onImprove}
                    isImproving={isImproving}
                />
            )}

            {/* Detailed checklist */}
            {breakdown && <DetailedChecklist breakdown={breakdown} />}
        </div>
    );
}

// Mini score for stat cards
export function SeoScoreMini({ score }: { score: number | null }) {
    if (score === null) {
        return <span className="text-2xl font-bold">-</span>;
    }

    const grade = getScoreGrade(score);
    const size = 48;
    const strokeWidth = 4;
    const radius = (size - strokeWidth) / 2;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (score / 100) * circumference;

    return (
        <div className="flex items-center gap-3">
            <div className="relative" style={{ width: size, height: size }}>
                <svg
                    className="-rotate-90 transform"
                    width={size}
                    height={size}
                >
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        strokeWidth={strokeWidth}
                        className="fill-none stroke-muted"
                    />
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        strokeWidth={strokeWidth}
                        strokeDasharray={circumference}
                        strokeDashoffset={offset}
                        strokeLinecap="round"
                        className={cn(
                            'fill-none transition-all duration-500',
                            grade.ringClass,
                        )}
                    />
                </svg>
                <div className="absolute inset-0 flex items-center justify-center">
                    <span className="text-sm font-bold">{score}</span>
                </div>
            </div>
            <div className="flex flex-col">
                <span className={cn('text-sm font-semibold', grade.colorClass)}>
                    {grade.label}
                </span>
                <span className="text-xs text-muted-foreground">SEO Score</span>
            </div>
        </div>
    );
}
