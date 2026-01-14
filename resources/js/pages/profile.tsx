import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    FlipNumber,
    RankBadge,
    PeriodTabs,
    ThemeToggle,
    SponsoredSlotPlaceholder,
    VerifiedBadge,
    MetricLabel,
} from '@/components/board';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Clock,
    Code2,
    Flame,
    Github,
    GitCommit,
    GitPullRequest,
    Calendar,
    ArrowLeft,
    ExternalLink,
    Twitter,
} from 'lucide-react';

interface Stats {
    rank: number | null;
    github_username: string;
    avatar_url: string;
    is_verified: boolean;
    verification_score: number;
    total_tokens: number;
    input_tokens: number;
    output_tokens: number;
    cache_read_tokens: number;
    cache_creation_tokens: number;
    total_cost: number;
    cache_efficiency: number;
    cost_per_1k_output: number;
    lines_added: number;
    lines_removed: number;
    commits: number;
    pull_requests: number;
    sessions: number;
    active_time: number;
    tool_invocations: number;
}

interface ModelBreakdown {
    model: string;
    input_tokens: number;
    output_tokens: number;
    cost: number;
}

interface ProfileUser {
    github_username: string;
    avatar_url: string;
    twitter_handle: string | null;
    created_at: string;
    is_verified: boolean;
    verification_score: number;
}

interface Props {
    user: ProfileUser;
    stats: Stats;
    allTimeStats: Stats;
    modelBreakdown: ModelBreakdown[];
    period: string;
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

function formatTime(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
    });
}

export default function Profile({
    user,
    stats,
    allTimeStats,
    modelBreakdown,
    period,
}: Props) {
    const { auth } = usePage<SharedData>().props;

    const handlePeriodChange = (newPeriod: string) => {
        router.get(`/u/${user.github_username}`, { period: newPeriod }, { preserveState: true });
    };

    const hasData = stats.total_tokens > 0;
    const hasAllTimeData = allTimeStats.total_tokens > 0;

    return (
        <>
            <Head title={`@${user.github_username} - Burnboard`} />
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

                <main className="mx-auto max-w-4xl px-4 py-8">
                    {/* Back Link */}
                    <Link
                        href="/"
                        className="mb-6 inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Leaderboard
                    </Link>

                    {/* Profile Header */}
                    <div className="mb-8 flex flex-col gap-6 sm:flex-row sm:items-start">
                        <Avatar className="h-20 w-20 border-2 border-border sm:h-24 sm:w-24">
                            <AvatarImage src={user.avatar_url} alt={user.github_username} />
                            <AvatarFallback className="text-2xl font-bold">
                                {user.github_username[0].toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="font-mono text-2xl font-bold sm:text-3xl">
                                    <span className="opacity-50">@</span>{user.github_username}
                                </h1>
                                {user.is_verified && (
                                    <VerifiedBadge
                                        isVerified={user.is_verified}
                                        score={user.verification_score}
                                        showScore
                                        size="md"
                                    />
                                )}
                                {stats.rank && <RankBadge rank={stats.rank} />}
                            </div>
                            <div className="mt-2 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                <a
                                    href={`https://github.com/${user.github_username}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 hover:text-foreground transition-colors"
                                >
                                    <Github className="h-4 w-4" />
                                    GitHub
                                    <ExternalLink className="h-3 w-3 opacity-50" />
                                </a>
                                {user.twitter_handle && (
                                    <a
                                        href={`https://x.com/${user.twitter_handle}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1.5 hover:text-foreground transition-colors"
                                    >
                                        <Twitter className="h-4 w-4" />
                                        @{user.twitter_handle}
                                        <ExternalLink className="h-3 w-3 opacity-50" />
                                    </a>
                                )}
                                <span className="inline-flex items-center gap-1.5">
                                    <Calendar className="h-4 w-4" />
                                    Joined {formatDate(user.created_at)}
                                </span>
                            </div>
                            {hasAllTimeData && (
                                <div className="mt-4 flex flex-wrap items-center gap-4 text-sm">
                                    <span>
                                        <span className="value-medium text-burn">
                                            {formatTokens(allTimeStats.total_tokens)}
                                        </span>{' '}
                                        <span className="text-muted-foreground">tokens all time</span>
                                    </span>
                                    <span>
                                        <span className="value-medium">
                                            {formatCost(allTimeStats.total_cost)}
                                        </span>{' '}
                                        <span className="text-muted-foreground">spent</span>
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Period Tabs */}
                    <div className="mb-6">
                        <PeriodTabs value={period} onChange={handlePeriodChange} />
                    </div>

                    {!hasData ? (
                        <div className="board-container">
                            <div className="empty-state" style={{ background: 'var(--board-row)' }}>
                                <Flame className="empty-state-icon" />
                                <p className="font-mono text-sm" style={{ color: 'var(--board-text-dim)' }}>
                                    No activity for this period.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Primary Stats Grid */}
                            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="tokens" label="Tokens Burned" className="section-header mb-0" />
                                        <Flame className="h-4 w-4 text-burn" />
                                    </div>
                                    <FlipNumber
                                        value={stats.total_tokens}
                                        format="compact"
                                        className="text-xl"
                                    />
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="total_cost" label="Cost" className="section-header mb-0" />
                                        <span className="text-sm opacity-60">$</span>
                                    </div>
                                    <div className="value-large">
                                        {formatCost(stats.total_cost)}
                                    </div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="lines_changed" label="Lines Changed" className="section-header mb-0" />
                                        <Code2 className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex items-baseline gap-2">
                                        <span className="value-large text-positive">
                                            +{formatTokens(stats.lines_added)}
                                        </span>
                                        <span className="value-small text-negative">
                                            -{formatTokens(stats.lines_removed)}
                                        </span>
                                    </div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="active_time" label="Active Time" className="section-header mb-0" />
                                        <Clock className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="value-large">
                                        {formatTime(stats.active_time)}
                                    </div>
                                </div>
                            </div>

                            {/* Efficiency Metrics */}
                            <div className="mb-6 grid gap-4 sm:grid-cols-2">
                                <div className="stat-card relative overflow-hidden">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="cache_efficiency" label="Cache Efficiency" className="section-header mb-0" />
                                        <span className="font-mono text-xs text-muted-foreground">%</span>
                                    </div>
                                    <div className="flex items-end gap-3">
                                        <div className="value-large text-positive">
                                            {stats.cache_efficiency}%
                                        </div>
                                        <div className="mb-1 flex-1">
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-positive transition-all duration-500"
                                                    style={{ width: `${Math.min(stats.cache_efficiency, 100)}%` }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="mt-2 font-mono text-xs text-muted-foreground">
                                        {formatTokens(stats.cache_read_tokens)} cached / {formatTokens(stats.input_tokens + stats.cache_read_tokens + stats.cache_creation_tokens)} total
                                    </div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="cost_per_1k" label="Cost per 1K Output" className="section-header mb-0" />
                                        <span className="font-mono text-xs text-muted-foreground">$/1K</span>
                                    </div>
                                    <div className="value-large">
                                        ${stats.cost_per_1k_output.toFixed(4)}
                                    </div>
                                    <div className="mt-2 font-mono text-xs text-muted-foreground">
                                        {formatTokens(stats.output_tokens)} output tokens
                                    </div>
                                </div>
                            </div>

                            {/* Token Breakdown */}
                            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                                <div className="stat-card">
                                    <div className="mb-2 flex items-center justify-between">
                                        <MetricLabel metric="input_tokens" label="Input" className="section-header mb-0 text-xs" />
                                    </div>
                                    <div className="value-medium">{formatTokens(stats.input_tokens)}</div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-2 flex items-center justify-between">
                                        <MetricLabel metric="output_tokens" label="Output" className="section-header mb-0 text-xs" />
                                    </div>
                                    <div className="value-medium">{formatTokens(stats.output_tokens)}</div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-2 flex items-center justify-between">
                                        <MetricLabel metric="cache_read" label="Cache Read" className="section-header mb-0 text-xs" />
                                    </div>
                                    <div className="value-medium text-positive">{formatTokens(stats.cache_read_tokens)}</div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-2 flex items-center justify-between">
                                        <MetricLabel metric="cache_creation" label="Cache Write" className="section-header mb-0 text-xs" />
                                    </div>
                                    <div className="value-medium">{formatTokens(stats.cache_creation_tokens)}</div>
                                </div>
                            </div>

                            {/* Activity Stats */}
                            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="commits" label="Commits" className="section-header mb-0" />
                                        <GitCommit className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="value-large">{stats.commits}</div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="pull_requests" label="Pull Requests" className="section-header mb-0" />
                                        <GitPullRequest className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div className="value-large">{stats.pull_requests}</div>
                                </div>
                                <div className="stat-card">
                                    <div className="mb-3 flex items-center justify-between">
                                        <MetricLabel metric="sessions" label="Sessions" className="section-header mb-0" />
                                        <span className="text-sm opacity-60">#</span>
                                    </div>
                                    <div className="value-large">{stats.sessions}</div>
                                </div>
                            </div>

                            {/* Model Breakdown */}
                            {modelBreakdown.length > 0 && (
                                <div className="board-container">
                                    <div className="board-header">
                                        <MetricLabel metric="model_breakdown" label="Model Usage" />
                                    </div>
                                    {modelBreakdown.map((model, index) => (
                                        <div
                                            key={model.model}
                                            className="board-row grid grid-cols-[1fr_auto]"
                                            style={{ animationDelay: `${index * 0.05}s` }}
                                        >
                                            <div>
                                                <p
                                                    className="font-mono font-medium"
                                                    style={{ color: 'var(--board-text)' }}
                                                >
                                                    {model.model}
                                                </p>
                                                <p
                                                    className="mt-0.5 font-mono text-xs"
                                                    style={{ color: 'var(--board-text-dim)' }}
                                                >
                                                    {formatTokens(model.input_tokens)} in / {formatTokens(model.output_tokens)} out
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <span
                                                    className="value-medium"
                                                    style={{ color: 'var(--board-text)' }}
                                                >
                                                    {formatCost(model.cost)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}

                    {/* Sponsored Slot */}
                    <div className="mt-8">
                        <SponsoredSlotPlaceholder />
                    </div>
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
