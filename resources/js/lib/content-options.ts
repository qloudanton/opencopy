/**
 * Shared content generation options used across the application.
 * These are used in Project Settings (defaults) and Content Planner (per-article overrides).
 */

export interface ToneOption {
    value: string;
    label: string;
    description: string;
}

/**
 * Writing tone options for article generation.
 * Project Settings uses these as defaults.
 * Content Planner allows per-article overrides.
 */
export const toneOptions: ToneOption[] = [
    {
        value: 'professional',
        label: 'Professional',
        description: 'Formal, business-appropriate',
    },
    {
        value: 'casual',
        label: 'Casual',
        description: 'Relaxed, conversational',
    },
    {
        value: 'friendly',
        label: 'Friendly',
        description: 'Warm, approachable',
    },
    {
        value: 'technical',
        label: 'Technical',
        description: 'Precise, detailed',
    },
    {
        value: 'authoritative',
        label: 'Authoritative',
        description: 'Expert, confident',
    },
    {
        value: 'conversational',
        label: 'Conversational',
        description: 'Like talking to a friend',
    },
    {
        value: 'formal',
        label: 'Formal',
        description: 'Structured, official',
    },
    {
        value: 'informative',
        label: 'Informative',
        description: 'Educational, fact-focused',
    },
    {
        value: 'persuasive',
        label: 'Persuasive',
        description: 'Compelling, action-oriented',
    },
    {
        value: 'enthusiastic',
        label: 'Enthusiastic',
        description: 'Energetic, passionate',
    },
];

/**
 * Get the label for a tone value
 */
export function getToneLabel(value: string): string {
    return toneOptions.find((t) => t.value === value)?.label ?? value;
}

/**
 * Get the display label for a tone value (label + description)
 * e.g., "Professional - Formal, business-appropriate"
 */
export function getToneDisplayLabel(value: string): string {
    const tone = toneOptions.find((t) => t.value === value);
    if (!tone) return value;
    return `${tone.label} - ${tone.description}`;
}

export interface WordCountOption {
    value: number;
    label: string;
}

/**
 * Word count options for article generation.
 * Project Settings uses these as defaults.
 * Content Planner allows per-article overrides.
 */
export const wordCountOptions: WordCountOption[] = [
    { value: 800, label: 'Short (~800 words)' },
    { value: 1200, label: 'Medium (~1,200 words)' },
    { value: 1500, label: 'Standard (~1,500 words)' },
    { value: 2000, label: 'Long (~2,000 words)' },
    { value: 2500, label: 'Comprehensive (~2,500 words)' },
];

/**
 * Get the label for a word count value
 */
export function getWordCountLabel(value: number): string {
    return (
        wordCountOptions.find((w) => w.value === value)?.label ??
        `~${value.toLocaleString()} words`
    );
}
