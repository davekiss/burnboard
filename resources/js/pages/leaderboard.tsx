import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    FlipNumber,
    BoardNumber,
    RankBadge,
    LiveIndicator,
    PeriodTabs,
    SponsoredSlotPlaceholder,
    ThemeToggle,
    VerifiedBadge,
} from '@/components/board';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Flame, Github, ArrowRight, Terminal, Copy, Check } from 'lucide-react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'motion/react';

interface LeaderboardEntry {
    rank: number;
    github_username: string;
    avatar_url: string;
    is_verified: boolean;
    verification_score: number;
    total_tokens: number;
    total_cost: number;
    lines_added: number;
    lines_removed: number;
    commits: number;
    pull_requests: number;
    sessions: number;
    active_time: number;
}

interface UserStats extends LeaderboardEntry {}

interface Props {
    leaderboard: LeaderboardEntry[];
    period: string;
    userStats: UserStats | null;
}

function formatTokens(tokens: number): string {
    if (tokens >= 1_000_000_000) {
        return `${(tokens / 1_000_000_000).toFixed(1)}B`;
    }
    if (tokens >= 1_000_000) {
        return `${(tokens / 1_000_000).toFixed(1)}M`;
    }
    if (tokens >= 1_000) {
        return `${(tokens / 1_000).toFixed(1)}K`;
    }
    return tokens.toString();
}

function formatCost(cost: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(cost);
}

export default function Leaderboard({ leaderboard, period, userStats }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [copied, setCopied] = useState(false);
    const [isPolling, setIsPolling] = useState(false);

    // Poll for updates every 10 seconds
    usePoll(10000, {
        onStart: () => setIsPolling(true),
        onFinish: () => setIsPolling(false),
    });

    const handlePeriodChange = (newPeriod: string) => {
        router.get('/', { period: newPeriod }, { preserveState: true });
    };

    const appUrl = typeof window !== 'undefined' ? window.location.origin : '';
    const setupCommand = `curl -sSL ${appUrl}/join | bash`;

    const copyToClipboard = async () => {
        await navigator.clipboard.writeText(setupCommand);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <>
            <Head title="Burnboard - AI Coding Telemetry" />
            <div className="min-h-screen bg-background">
                {/* Header */}
                <header className="site-header">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
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
                                <a href="/auth/github">
                                    <Button size="sm" className="font-mono text-xs">
                                        <Github className="mr-1.5 h-3.5 w-3.5" />
                                        Sign in
                                    </Button>
                                </a>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-4 py-8">
                    {/* Hero Section */}
                    <div className="mb-8 text-center">
                        <h1 className="mb-3 font-mono text-3xl font-bold tracking-tight md:text-4xl">
                            See who's <span className="text-burn">cookin'</span> with AI
                        </h1>
                        <p className="text-muted-foreground">
                            Track your token usage across Claude Code, OpenAI Codex, and OpenCode{' '}
                            <span className="text-xs opacity-60">(coming soon)</span>
                        </p>
                    </div>

                    {/* User Stats Banner (if logged in with stats) */}
                    {userStats && (
                        <div className="mb-6 rounded-lg border border-burn/30 bg-burn-muted/20 p-4">
                            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-10 w-10 border border-burn/30">
                                            <AvatarImage src={userStats.avatar_url} />
                                            <AvatarFallback className="bg-burn/20 text-burn">
                                                {userStats.github_username[0].toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="username font-medium">
                                                    {userStats.github_username}
                                                </span>
                                                {userStats.is_verified && (
                                                    <VerifiedBadge
                                                        isVerified={userStats.is_verified}
                                                        score={userStats.verification_score}
                                                        size="sm"
                                                    />
                                                )}
                                                {userStats.rank && (
                                                    <RankBadge rank={userStats.rank} />
                                                )}
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                Your position
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-6">
                                    <div className="text-center">
                                        <div className="value-medium text-burn">
                                            {formatTokens(userStats.total_tokens)}
                                        </div>
                                        <div className="text-xs text-muted-foreground">tokens</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="value-medium">
                                            {formatCost(userStats.total_cost)}
                                        </div>
                                        <div className="text-xs text-muted-foreground">spent</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="value-medium">
                                            <span className="text-positive">+{userStats.lines_added.toLocaleString()}</span>
                                        </div>
                                        <div className="text-xs text-muted-foreground">lines</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Controls Row */}
                    <div className="mb-4 flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <PeriodTabs value={period} onChange={handlePeriodChange} />
                        <LiveIndicator isPolling={isPolling} />
                    </div>

                    {/* The Board */}
                    <div className="board-container">
                        {/* Board Header */}
                        <div className="board-header grid grid-cols-[4rem_1fr_7rem_6rem_8rem_5rem] gap-4">
                            <span>Rank</span>
                            <span>User</span>
                            <span className="text-right">Tokens</span>
                            <span className="text-right">Cost</span>
                            <span className="hidden text-right md:block">Lines +/-</span>
                            <span className="hidden text-right md:block">Commits</span>
                        </div>

                        {/* Board Body */}
                        {leaderboard.length === 0 ? (
                            <div className="empty-state" style={{ background: 'var(--board-row)' }}>
                                <Flame className="empty-state-icon" />
                                <p className="mb-4 font-mono text-sm" style={{ color: 'var(--board-text-dim)' }}>
                                    No data yet. Be the first to join!
                                </p>
                                <div className="code-block inline-block">
                                    <span className="command">curl</span> -sSL {appUrl}/join | <span className="command">bash</span>
                                </div>
                            </div>
                        ) : (
                            <div>
                                {/* Sponsored slot position 1 */}
                                {leaderboard.length > 3 && (
                                    <SponsoredSlotPlaceholder className="mx-4 my-2" />
                                )}

                                <AnimatePresence mode="popLayout">
                                    {leaderboard.map((entry, index) => (
                                        <motion.div
                                            key={entry.github_username}
                                            layout
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, scale: 0.95 }}
                                            transition={{
                                                layout: { type: 'spring', stiffness: 350, damping: 30 },
                                                opacity: { duration: 0.2 },
                                            }}
                                        >
                                            <Link
                                                href={`/u/${entry.github_username}`}
                                                className="board-row grid grid-cols-[4rem_1fr_7rem_6rem_8rem_5rem] gap-4"
                                            >
                                                {/* Rank */}
                                                <div className="flex items-center">
                                                    <RankBadge rank={entry.rank} />
                                                </div>

                                                {/* User */}
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-8 w-8 border" style={{ borderColor: 'var(--board-divider)' }}>
                                                        <AvatarImage src={entry.avatar_url} alt={entry.github_username} />
                                                        <AvatarFallback style={{ background: 'var(--board-row-alt)', color: 'var(--board-text-dim)' }}>
                                                            {entry.github_username[0].toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span
                                                        className="username truncate transition-colors hover:text-burn"
                                                        style={{ color: 'var(--board-text)' }}
                                                    >
                                                        {entry.github_username}
                                                    </span>
                                                    {entry.is_verified && (
                                                        <VerifiedBadge
                                                            isVerified={entry.is_verified}
                                                            score={entry.verification_score}
                                                            size="sm"
                                                        />
                                                    )}
                                                </div>

                                                {/* Tokens */}
                                                <div className="flex items-center justify-end">
                                                    <FlipNumber
                                                        value={entry.total_tokens}
                                                        format="compact"
                                                        className="text-sm"
                                                    />
                                                </div>

                                                {/* Cost */}
                                                <div className="flex items-center justify-end">
                                                    <span className="value-small" style={{ color: 'var(--board-text)' }}>
                                                        {formatCost(entry.total_cost)}
                                                    </span>
                                                </div>

                                                {/* Lines */}
                                                <div className="hidden items-center justify-end gap-1 md:flex">
                                                    <span className="value-small text-positive">
                                                        +{entry.lines_added.toLocaleString()}
                                                    </span>
                                                    <span style={{ color: 'var(--board-text-dim)' }}>/</span>
                                                    <span className="value-small text-negative">
                                                        -{entry.lines_removed.toLocaleString()}
                                                    </span>
                                                </div>

                                                {/* Commits */}
                                                <div className="hidden items-center justify-end md:flex">
                                                    <span className="value-small" style={{ color: 'var(--board-text-dim)' }}>
                                                        {entry.commits}
                                                    </span>
                                                </div>
                                            </Link>
                                        </motion.div>
                                    ))}
                                </AnimatePresence>

                                {/* Sponsored slot position 2 */}
                                {leaderboard.length > 8 && (
                                    <SponsoredSlotPlaceholder className="mx-4 my-2" />
                                )}
                            </div>
                        )}
                    </div>

                    {/* Join CTA (for non-authenticated users) */}
                    {!auth.user && (
                        <div className="mt-8 rounded-lg border border-border bg-card p-6 text-center">
                            <Terminal className="mx-auto mb-3 h-8 w-8 text-burn" />
                            <h2 className="mb-2 font-mono text-lg font-semibold">
                                Start Tracking
                            </h2>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Run this command to connect Claude Code or OpenAI Codex
                            </p>
                            <div className="mx-auto flex max-w-lg items-center gap-2">
                                <div className="code-block flex-1 text-left">
                                    <span className="command">curl</span> -sSL {appUrl}/join | <span className="command">bash</span>
                                </div>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={copyToClipboard}
                                    className="shrink-0"
                                >
                                    {copied ? (
                                        <Check className="h-4 w-4 text-positive" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </Button>
                            </div>
                            <p className="mt-4 text-xs text-muted-foreground">
                                The script will open GitHub to authenticate, then configure your shell automatically.
                            </p>
                            <p className="mt-3 text-xs text-muted-foreground">
                                <Link href="/how-it-works" className="text-burn hover:underline">
                                    How does this work?
                                </Link>
                                <span className="mx-2 opacity-30">|</span>
                                Already joined?{' '}
                                <a href="/auth/github" className="text-burn hover:underline">
                                    Sign in
                                </a>
                            </p>
                        </div>
                    )}

                    {/* Sponsored Banner at bottom */}
                    <div className="mt-8">
                        <SponsoredSlotPlaceholder />
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-border py-6">
                    <div className="mx-auto max-w-6xl px-4 text-center">
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
                            <Link href="/how-it-works" className="text-burn hover:underline">
                                How it works
                            </Link>
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
