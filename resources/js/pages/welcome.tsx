import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

// Icons for features
function CalendarIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M16 2v4" />
            <path d="M8 2v4" />
            <path d="M3 10h18" />
            <path d="M8 14h.01" />
            <path d="M12 14h.01" />
            <path d="M16 14h.01" />
            <path d="M8 18h.01" />
            <path d="M12 18h.01" />
            <path d="M16 18h.01" />
        </svg>
    );
}

function RocketIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z" />
            <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z" />
            <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0" />
            <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5" />
        </svg>
    );
}

function SparklesIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z" />
            <path d="M20 3v4" />
            <path d="M22 5h-4" />
            <path d="M4 17v2" />
            <path d="M5 18H3" />
        </svg>
    );
}

function ImageIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <circle cx="9" cy="9" r="2" />
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
        </svg>
    );
}

function LinkIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
        </svg>
    );
}

function ChartIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 3v16a2 2 0 0 0 2 2h16" />
            <path d="m19 9-5 5-4-4-3 3" />
        </svg>
    );
}

function CheckIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 6 9 17l-5-5" />
        </svg>
    );
}

function ArrowRightIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
        </svg>
    );
}

const features = [
    {
        icon: SparklesIcon,
        title: 'AI-Powered Writing',
        description: 'Generate SEO-optimized articles using OpenAI, Claude, or Ollama. Bring your own API key - no monthly fees.',
    },
    {
        icon: CalendarIcon,
        title: 'Content Planner',
        description: 'Plan and schedule your content weeks in advance. Drag-and-drop calendar makes content planning effortless.',
    },
    {
        icon: ImageIcon,
        title: 'Auto-Generated Images',
        description: 'AI creates featured images and inline visuals. Infographics, diagrams, and illustrations - all generated automatically.',
    },
    {
        icon: LinkIcon,
        title: 'Smart Internal Linking',
        description: 'Build your internal link database. AI naturally weaves relevant links into your content for better SEO.',
    },
    {
        icon: ChartIcon,
        title: 'SEO Scoring',
        description: 'Real-time SEO analysis with actionable recommendations. Optimize keywords, readability, and structure.',
    },
    {
        icon: RocketIcon,
        title: 'Auto-Publishing',
        description: 'Schedule articles to publish automatically. WordPress integration coming soon, webhooks for any CMS.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Add Your Keywords',
        description: 'Import your target keywords or let AI suggest topics based on your niche.',
    },
    {
        number: '02',
        title: 'Schedule Content',
        description: 'Drag keywords to your content calendar. Set publication dates that work for you.',
    },
    {
        number: '03',
        title: 'Generate & Publish',
        description: 'AI writes SEO-optimized articles. Review, edit, and publish with one click.',
    },
];

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="AI-Powered SEO Content Generation">
                <meta name="description" content="OpenCopy.AI - Self-hosted, open source AI content generation. Create SEO-optimized articles using OpenAI, Claude, or Ollama. Bring your own API key." />
            </Head>

            <div className="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900">
                {/* Navigation */}
                <nav className="fixed top-0 z-50 w-full border-b border-slate-200/50 bg-white/80 backdrop-blur-lg dark:border-slate-800/50 dark:bg-slate-950/80">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
                        <Link href="/" className="flex items-center gap-2">
                            <AppLogoIcon className="size-9" />
                            <span className="text-xl font-semibold text-slate-900 dark:text-white">
                                OpenCopy<span className="text-slate-400">.ai</span>
                            </span>
                        </Link>

                        <div className="flex items-center gap-3">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={dashboard()}>
                                        Dashboard
                                        <ArrowRightIcon className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild>
                                            <Link href={register()}>
                                                Get Started
                                                <ArrowRightIcon className="size-4" />
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-32">
                    {/* Background decoration */}
                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                        <div className="absolute -top-1/2 left-1/2 size-[800px] -translate-x-1/2 rounded-full bg-gradient-to-br from-[#00AAFF]/20 to-[#FFFB80]/20 blur-3xl" />
                        <div className="absolute top-1/4 right-0 size-[600px] rounded-full bg-gradient-to-bl from-[#FFFB80]/15 to-transparent blur-3xl" />
                    </div>

                    <div className="relative mx-auto max-w-7xl px-6">
                        <div className="mx-auto max-w-4xl text-center">
                            {/* Badge */}
                            <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <span className="flex size-2 rounded-full bg-green-500" />
                                <span className="text-slate-600 dark:text-slate-300">Open Source & Self-Hosted</span>
                            </div>

                            {/* Headline */}
                            <h1 className="mb-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                                AI Content Generation
                                <span className="mt-2 block bg-gradient-to-r from-[#00AAFF] to-[#394F87] bg-clip-text text-transparent">
                                    Without the Monthly Fees
                                </span>
                            </h1>

                            {/* Subheadline */}
                            <p className="mx-auto mb-10 max-w-2xl text-lg text-slate-600 sm:text-xl dark:text-slate-400">
                                Self-hosted SEO content platform. Generate optimized articles using OpenAI, Claude, or Ollama.
                                Bring your own API key - pay only for what you use.
                            </p>

                            {/* CTA Buttons */}
                            <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                                {auth.user ? (
                                    <Button size="lg" asChild className="h-12 px-8 text-base">
                                        <Link href={dashboard()}>
                                            Go to Dashboard
                                            <ArrowRightIcon className="size-5" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button size="lg" asChild className="h-12 px-8 text-base">
                                            <Link href={register()}>
                                                Start Free
                                                <ArrowRightIcon className="size-5" />
                                            </Link>
                                        </Button>
                                        <Button size="lg" variant="outline" asChild className="h-12 border-slate-300 px-8 text-base text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                            <a href="https://github.com/qloudanton/opencopy" target="_blank" rel="noopener noreferrer">
                                                <svg className="size-5" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                                </svg>
                                                View on GitHub
                                            </a>
                                        </Button>
                                    </>
                                )}
                            </div>

                            {/* Trust indicators */}
                            <div className="mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-slate-500 dark:text-slate-400">
                                <div className="flex items-center gap-2">
                                    <CheckIcon className="size-4 text-green-500" />
                                    <span>No subscription required</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <CheckIcon className="size-4 text-green-500" />
                                    <span>Your data, your server</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <CheckIcon className="size-4 text-green-500" />
                                    <span>Works with any AI provider</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="relative py-20 lg:py-32">
                    <div className="mx-auto max-w-7xl px-6">
                        <div className="mb-16 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">
                                Everything you need for content at scale
                            </h2>
                            <p className="mx-auto max-w-2xl text-lg text-slate-600 dark:text-slate-400">
                                From keyword research to auto-publishing, OpenCopy handles your entire content workflow.
                            </p>
                        </div>

                        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="group relative rounded-2xl border border-slate-200 bg-white p-8 shadow-sm transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                                >
                                    <div className="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#00AAFF]/10 to-[#394F87]/10 text-[#00AAFF] transition-colors group-hover:from-[#00AAFF]/20 group-hover:to-[#394F87]/20">
                                        <feature.icon className="size-6" />
                                    </div>
                                    <h3 className="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                        {feature.title}
                                    </h3>
                                    <p className="text-slate-600 dark:text-slate-400">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How It Works Section */}
                <section className="relative bg-slate-50 py-20 lg:py-32 dark:bg-slate-900/50">
                    <div className="mx-auto max-w-7xl px-6">
                        <div className="mb-16 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">
                                Three steps to content automation
                            </h2>
                            <p className="mx-auto max-w-2xl text-lg text-slate-600 dark:text-slate-400">
                                Set it up once, generate content forever.
                            </p>
                        </div>

                        <div className="grid gap-8 lg:grid-cols-3">
                            {steps.map((step, index) => (
                                <div key={step.number} className="relative">
                                    {/* Connector line */}
                                    {index < steps.length - 1 && (
                                        <div className="absolute top-12 left-1/2 hidden h-px w-full bg-gradient-to-r from-[#00AAFF]/50 to-[#FFFB80]/50 lg:block" />
                                    )}

                                    <div className="relative flex flex-col items-center text-center">
                                        <div className="mb-6 flex size-24 items-center justify-center rounded-full bg-gradient-to-br from-[#00AAFF] to-[#394F87] text-3xl font-bold text-white shadow-lg shadow-[#00AAFF]/25">
                                            {step.number}
                                        </div>
                                        <h3 className="mb-3 text-xl font-semibold text-slate-900 dark:text-white">
                                            {step.title}
                                        </h3>
                                        <p className="max-w-sm text-slate-600 dark:text-slate-400">
                                            {step.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="relative py-20 lg:py-32">
                    <div className="mx-auto max-w-7xl px-6">
                        <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 to-slate-800 px-8 py-16 text-center sm:px-16 lg:py-24 dark:from-slate-800 dark:to-slate-900">
                            {/* Background decoration */}
                            <div className="pointer-events-none absolute inset-0">
                                <div className="absolute top-0 left-1/4 size-64 rounded-full bg-[#00AAFF]/20 blur-3xl" />
                                <div className="absolute bottom-0 right-1/4 size-64 rounded-full bg-[#FFFB80]/20 blur-3xl" />
                            </div>

                            <div className="relative">
                                <h2 className="mb-4 text-3xl font-bold text-white sm:text-4xl">
                                    Ready to automate your content?
                                </h2>
                                <p className="mx-auto mb-8 max-w-xl text-lg text-slate-300">
                                    Join developers and content creators who use OpenCopy to generate
                                    SEO-optimized content without the overhead of expensive subscriptions.
                                </p>
                                <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                                    {auth.user ? (
                                        <Button size="lg" asChild className="h-12 bg-white px-8 text-base text-slate-900 hover:bg-slate-100">
                                            <Link href={dashboard()}>
                                                Open Dashboard
                                                <ArrowRightIcon className="size-5" />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <>
                                            <Button size="lg" asChild className="h-12 bg-white px-8 text-base text-slate-900 hover:bg-slate-100">
                                                <Link href={register()}>
                                                    Get Started Free
                                                    <ArrowRightIcon className="size-5" />
                                                </Link>
                                            </Button>
                                            <Button size="lg" asChild className="h-12 border border-slate-500 bg-transparent px-8 text-base text-white hover:bg-white/10">
                                                <a href="https://github.com/qloudanton/opencopy" target="_blank" rel="noopener noreferrer">
                                                    <svg className="size-5 text-yellow-400" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                    </svg>
                                                    Star on GitHub
                                                </a>
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-slate-200 py-12 dark:border-slate-800">
                    <div className="mx-auto max-w-7xl px-6">
                        <div className="flex flex-col items-center justify-between gap-6 sm:flex-row">
                            <div className="flex items-center gap-2">
                                <AppLogoIcon className="size-8" />
                                <span className="text-lg font-semibold text-slate-900 dark:text-white">
                                    OpenCopy<span className="text-slate-400">.ai</span>
                                </span>
                            </div>

                            <div className="flex items-center gap-6 text-sm text-slate-500 dark:text-slate-400">
                                <a
                                    href="https://github.com/qloudanton/opencopy"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="transition-colors hover:text-slate-900 dark:hover:text-white"
                                >
                                    GitHub
                                </a>
                                <span>Open Source under MIT License</span>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
