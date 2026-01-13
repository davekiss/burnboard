import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/board';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Flame,
    Github,
    ArrowLeft,
    Shield,
    Eye,
    EyeOff,
    Terminal,
    Database,
    Lock,
    ExternalLink,
    CheckCircle2,
    XCircle,
} from 'lucide-react';

export default function HowItWorks() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="How It Works - Burnboard" />
            <div className="min-h-screen bg-background">
                {/* Header */}
                <header className="site-header">
                    <div className="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                        <Link href="/" className="logo">
                            <Flame className="logo-icon" />
                            <span>burnboard</span>
                        </Link>
                        <div className="flex items-center gap-3">
                            <ThemeToggle />
                            {auth.user ? (
                                <Link href="/dashboard">
                                    <Button variant="outline" size="sm" className="font-mono text-xs">
                                        Dashboard
                                    </Button>
                                </Link>
                            ) : (
                                <Link href="/auth/github">
                                    <Button size="sm" className="font-mono text-xs">
                                        <Github className="mr-1.5 h-3.5 w-3.5" />
                                        Sign in
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-4 py-8">
                    {/* Back Link */}
                    <Link
                        href="/"
                        className="mb-6 inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Leaderboard
                    </Link>

                    {/* Hero */}
                    <div className="mb-12">
                        <h1 className="mb-4 font-mono text-3xl font-bold tracking-tight md:text-4xl">
                            How <span className="text-burn">Burnboard</span> Works
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Transparent by design. Here's exactly what happens when you join.
                        </p>
                    </div>

                    {/* Open Source Banner */}
                    <div className="mb-10 rounded-lg border border-burn/30 bg-burn-muted/10 p-5">
                        <div className="flex items-start gap-4">
                            <div className="rounded-lg bg-burn/20 p-2.5">
                                <Github className="h-6 w-6 text-burn" />
                            </div>
                            <div className="flex-1">
                                <h2 className="mb-1 font-mono text-lg font-semibold">
                                    100% Open Source
                                </h2>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Every line of code is public. Inspect the setup script, the API,
                                    the data processing—everything. No black boxes.
                                </p>
                                <a
                                    href="https://github.com/davekiss/burnboard"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 font-mono text-sm text-burn hover:underline"
                                >
                                    View on GitHub
                                    <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>

                    {/* The Flow */}
                    <section className="mb-12">
                        <h2 className="mb-6 font-mono text-xl font-semibold">
                            The Setup Flow
                        </h2>
                        <div className="space-y-4">
                            <div className="flex gap-4">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-burn/20 font-mono text-sm font-bold text-burn">
                                    1
                                </div>
                                <div className="flex-1 pt-1">
                                    <h3 className="mb-1 font-mono font-medium">Run the setup command</h3>
                                    <p className="text-sm text-muted-foreground">
                                        The script is fetched and executed in your terminal. You can{' '}
                                        <a
                                            href="https://github.com/davekiss/burnboard/blob/main/app/Http/Controllers/SetupController.php"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-burn hover:underline"
                                        >
                                            read the full script here
                                        </a>
                                        .
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-burn/20 font-mono text-sm font-bold text-burn">
                                    2
                                </div>
                                <div className="flex-1 pt-1">
                                    <h3 className="mb-1 font-mono font-medium">Authenticate with GitHub</h3>
                                    <p className="text-sm text-muted-foreground">
                                        A browser window opens for you to sign in. We only request{' '}
                                        <strong>public profile access</strong>—your username and avatar.
                                        No repo access, no email, no private data.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-burn/20 font-mono text-sm font-bold text-burn">
                                    3
                                </div>
                                <div className="flex-1 pt-1">
                                    <h3 className="mb-1 font-mono font-medium">Tools configured automatically</h3>
                                    <p className="text-sm text-muted-foreground">
                                        The script configures your selected tools: environment variables
                                        for Claude Code, or config.toml for OpenAI Codex.
                                        All use OpenTelemetry to send metrics to Burnboard.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-burn/20 font-mono text-sm font-bold text-burn">
                                    4
                                </div>
                                <div className="flex-1 pt-1">
                                    <h3 className="mb-1 font-mono font-medium">Telemetry flows automatically</h3>
                                    <p className="text-sm text-muted-foreground">
                                        When you use your AI coding tools, they send usage metrics via
                                        OpenTelemetry to our API. Your stats appear on the leaderboard.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* What We Collect / Don't Collect */}
                    <section className="mb-12">
                        <h2 className="mb-6 font-mono text-xl font-semibold">
                            What Data Is Collected
                        </h2>
                        <div className="grid gap-6 md:grid-cols-2">
                            {/* Collected */}
                            <div className="rounded-lg border border-positive/30 bg-positive/5 p-5">
                                <div className="mb-4 flex items-center gap-2">
                                    <Eye className="h-5 w-5 text-positive" />
                                    <h3 className="font-mono font-semibold text-positive">Collected</h3>
                                </div>
                                <ul className="space-y-2.5">
                                    {[
                                        'Token counts (input, output, cache)',
                                        'API costs (calculated from tokens)',
                                        'Model names used (Opus, Sonnet, Haiku)',
                                        'Session duration',
                                        'Lines of code changed',
                                        'Commit and PR counts',
                                    ].map((item) => (
                                        <li key={item} className="flex items-start gap-2 text-sm">
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-positive" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Not Collected */}
                            <div className="rounded-lg border border-negative/30 bg-negative/5 p-5">
                                <div className="mb-4 flex items-center gap-2">
                                    <EyeOff className="h-5 w-5 text-negative" />
                                    <h3 className="font-mono font-semibold text-negative">Never Collected</h3>
                                </div>
                                <ul className="space-y-2.5">
                                    {[
                                        'Your code or file contents',
                                        'Prompts or conversations',
                                        'File names or paths',
                                        'Repository names',
                                        'API keys or secrets',
                                        'Anything from your GitHub repos',
                                    ].map((item) => (
                                        <li key={item} className="flex items-start gap-2 text-sm">
                                            <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-negative" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </section>

                    {/* Technical Details */}
                    <section className="mb-12">
                        <h2 className="mb-6 font-mono text-xl font-semibold">
                            Technical Details
                        </h2>
                        <div className="space-y-6">
                            <div className="rounded-lg border bg-card p-5">
                                <div className="mb-3 flex items-center gap-2">
                                    <Terminal className="h-5 w-5 text-muted-foreground" />
                                    <h3 className="font-mono font-medium">OpenTelemetry (OTLP)</h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Claude Code, OpenAI Codex, and OpenCode <span className="opacity-60">(coming soon)</span> support exporting telemetry via{' '}
                                    <a
                                        href="https://opentelemetry.io/"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-burn hover:underline"
                                    >
                                        OpenTelemetry
                                    </a>
                                    , an open standard for observability data. Burnboard simply receives
                                    this standard telemetry—we don't inject any custom code into your tools.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-card p-5">
                                <div className="mb-3 flex items-center gap-2">
                                    <Lock className="h-5 w-5 text-muted-foreground" />
                                    <h3 className="font-mono font-medium">GitHub OAuth Scopes</h3>
                                </div>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    We request minimal permissions:
                                </p>
                                <div className="rounded border bg-muted/50 p-3 font-mono text-xs">
                                    <span className="text-positive">read:user</span>
                                    <span className="text-muted-foreground"> — Public profile info (username, avatar)</span>
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    No repo access, no email, no private data. Just enough to identify you on the leaderboard.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-card p-5">
                                <div className="mb-3 flex items-center gap-2">
                                    <Database className="h-5 w-5 text-muted-foreground" />
                                    <h3 className="font-mono font-medium">Data Storage</h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Metrics are aggregated and stored with your user ID. You can delete
                                    all your data at any time from your dashboard. The database schema
                                    is{' '}
                                    <a
                                        href="https://github.com/davekiss/burnboard/tree/main/database/migrations"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-burn hover:underline"
                                    >
                                        publicly viewable
                                    </a>
                                    .
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* Verification */}
                    <section className="mb-12">
                        <h2 className="mb-6 font-mono text-xl font-semibold">
                            Verification System
                        </h2>
                        <div className="rounded-lg border bg-card p-5">
                            <div className="mb-3 flex items-center gap-2">
                                <Shield className="h-5 w-5 text-positive" />
                                <h3 className="font-mono font-medium">How Verified Badges Work</h3>
                            </div>
                            <p className="mb-3 text-sm text-muted-foreground">
                                Since telemetry is self-reported, it could theoretically be forged.
                                To combat this, we cross-reference reported metrics with public GitHub
                                activity:
                            </p>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                <li className="flex items-start gap-2">
                                    <span className="mt-1 h-1.5 w-1.5 rounded-full bg-burn" />
                                    Commits and PRs are compared against your public GitHub contributions
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-1 h-1.5 w-1.5 rounded-full bg-burn" />
                                    Line changes are compared against actual commit diffs
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-1 h-1.5 w-1.5 rounded-full bg-burn" />
                                    Users with 50%+ match get a verified badge
                                </li>
                            </ul>
                            <p className="mt-3 text-xs text-muted-foreground">
                                Note: Only public repository activity can be verified. Private repo
                                work may result in lower verification scores.
                            </p>
                        </div>
                    </section>

                    {/* FAQ */}
                    <section className="mb-12">
                        <h2 className="mb-6 font-mono text-xl font-semibold">
                            FAQ
                        </h2>
                        <div className="space-y-4">
                            <div className="rounded-lg border bg-card p-4">
                                <h3 className="mb-2 font-mono text-sm font-medium">Can you see my code or prompts?</h3>
                                <p className="text-sm text-muted-foreground">
                                    No. OpenTelemetry metrics contain only aggregate counts (tokens, costs, etc).
                                    Your actual code, prompts, and conversations never leave your machine.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <h3 className="mb-2 font-mono text-sm font-medium">Can you access my GitHub repos?</h3>
                                <p className="text-sm text-muted-foreground">
                                    No. We only request read:user scope, which provides your public username and avatar.
                                    We have no access to your repositories, private or public.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <h3 className="mb-2 font-mono text-sm font-medium">How do I uninstall?</h3>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Run this command to remove Burnboard from your shell config:
                                </p>
                                <div className="code-block text-left">
                                    <span className="command">curl</span> -sSL {typeof window !== 'undefined' ? window.location.origin : ''}/uninstall | <span className="command">bash</span>
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Then delete your data from the{' '}
                                    <Link href="/dashboard" className="text-burn hover:underline">dashboard</Link>.
                                </p>
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <h3 className="mb-2 font-mono text-sm font-medium">Is this affiliated with Anthropic or OpenAI?</h3>
                                <p className="text-sm text-muted-foreground">
                                    No. Burnboard is an independent, community-built project.
                                    It's not affiliated with or endorsed by Anthropic, OpenAI, or any AI company.
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* CTA */}
                    <section className="rounded-lg border border-burn/30 bg-burn-muted/10 p-6 text-center">
                        <h2 className="mb-2 font-mono text-lg font-semibold">
                            Ready to join?
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Start tracking your AI coding tool usage on the leaderboard
                        </p>
                        <Link href="/">
                            <Button className="font-mono text-xs">
                                <Flame className="mr-1.5 h-3.5 w-3.5" />
                                View Leaderboard
                            </Button>
                        </Link>
                    </section>
                </main>

                {/* Footer */}
                <footer className="border-t border-border py-6">
                    <div className="mx-auto max-w-4xl px-4 text-center">
                        <p className="font-mono text-xs text-muted-foreground">
                            Made by{' '}
                            <a
                                href="https://x.com/davekiss"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-burn hover:underline"
                            >
                                @davekiss
                            </a>
                            <span className="mx-2 opacity-30">|</span>
                            <a
                                href="https://github.com/davekiss/burnboard"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-burn hover:underline"
                            >
                                Open Source
                            </a>
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
