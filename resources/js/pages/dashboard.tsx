import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { ThemeToggle } from '@/components/board';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    Flame,
    Trash2,
    ExternalLink,
    Terminal,
    AlertTriangle,
    Settings,
    Twitter,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Props {
    apiToken: string;
    hasMetrics: boolean;
    twitterHandle: string | null;
}

export default function Dashboard({ apiToken, hasMetrics, twitterHandle }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [copied, setCopied] = useState(false);
    const [copiedEnv, setCopiedEnv] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [profileSaved, setProfileSaved] = useState(false);

    const profileForm = useForm({
        twitter_handle: twitterHandle ?? '',
    });

    const handleProfileSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.patch('/dashboard/profile', {
            preserveScroll: true,
            onSuccess: () => {
                setProfileSaved(true);
                setTimeout(() => setProfileSaved(false), 2000);
            },
        });
    };

    const appUrl = typeof window !== 'undefined' ? window.location.origin : '';
    const setupCommand = `curl -sSL ${appUrl}/join | bash`;

    const envVars = `export CLAUDE_CODE_ENABLE_TELEMETRY=1
export OTEL_METRICS_EXPORTER=otlp
export OTEL_LOGS_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_PROTOCOL=http/json
export OTEL_EXPORTER_OTLP_ENDPOINT=${appUrl}/api
export OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer ${apiToken}"`;

    const copyCommand = async () => {
        await navigator.clipboard.writeText(setupCommand);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const copyEnvVars = async () => {
        await navigator.clipboard.writeText(envVars);
        setCopiedEnv(true);
        setTimeout(() => setCopiedEnv(false), 2000);
    };

    const handleDeleteStats = () => {
        router.delete('/dashboard/stats', {
            onSuccess: () => setShowDeleteConfirm(false),
        });
    };

    return (
        <>
            <Head title="Dashboard - Burnboard" />
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
                            {auth.user?.github_username && (
                                <Link href={`/u/${auth.user.github_username}`}>
                                    <Button variant="outline" size="sm" className="font-mono text-xs">
                                        <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                                        View Profile
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-4 py-8">
                    {/* Page Header */}
                    <div className="mb-8">
                        <h1 className="font-mono text-2xl font-bold">Dashboard</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage your Burnboard connection and account
                        </p>
                    </div>

                    {/* Setup Instructions */}
                    <div className="mb-8 rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-4">
                            <div className="flex items-center gap-2">
                                <Terminal className="h-5 w-5 text-burn" />
                                <h2 className="font-mono font-semibold">Setup Instructions</h2>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Connect your Claude Code telemetry to Burnboard
                            </p>
                        </div>
                        <div className="space-y-6 p-4">
                            {/* Quick Setup */}
                            <div>
                                <span className="section-header">Quick Setup</span>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Run this command in your terminal:
                                </p>
                                <div className="flex items-center gap-2">
                                    <div className="code-block flex-1">
                                        <span className="command">curl</span> -sSL {appUrl}/join | <span className="command">bash</span>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={copyCommand}
                                        className="shrink-0"
                                    >
                                        {copied ? (
                                            <Check className="h-4 w-4 text-positive" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>

                            {/* Manual Setup */}
                            <div>
                                <span className="section-header">Manual Setup</span>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Or configure manually with these environment variables:
                                </p>
                                <div className="relative">
                                    <pre className="code-block overflow-x-auto text-xs">
                                        <code>{envVars}</code>
                                    </pre>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={copyEnvVars}
                                        className="absolute right-2 top-2 font-mono text-xs"
                                    >
                                        {copiedEnv ? (
                                            <>
                                                <Check className="mr-1.5 h-3 w-3 text-positive" />
                                                Copied
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="mr-1.5 h-3 w-3" />
                                                Copy
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>

                            {/* API Token Display */}
                            <div className="rounded-md border border-dashed border-border bg-muted/30 p-3">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Settings className="h-3.5 w-3.5" />
                                    <span className="font-mono">Your API Token:</span>
                                    <code className="font-mono text-foreground">
                                        {apiToken.substring(0, 8)}...{apiToken.substring(apiToken.length - 4)}
                                    </code>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Profile Settings */}
                    <div className="mb-8 rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-4">
                            <div className="flex items-center gap-2">
                                <User className="h-5 w-5 text-burn" />
                                <h2 className="font-mono font-semibold">Profile</h2>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Customize how others see you on your public profile
                            </p>
                        </div>
                        <form onSubmit={handleProfileSubmit} className="p-4">
                            <div className="max-w-sm">
                                <Label htmlFor="twitter_handle" className="text-sm font-medium">
                                    X (Twitter) handle
                                </Label>
                                <div className="relative mt-2">
                                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                        @
                                    </span>
                                    <Input
                                        id="twitter_handle"
                                        className="pl-7"
                                        value={profileForm.data.twitter_handle}
                                        onChange={(e) => profileForm.setData('twitter_handle', e.target.value)}
                                        placeholder="username"
                                        maxLength={15}
                                    />
                                </div>
                                <InputError className="mt-2" message={profileForm.errors.twitter_handle} />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    This will be displayed on your public profile
                                </p>
                            </div>
                            <div className="mt-4 flex items-center gap-3">
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={profileForm.processing}
                                    className="font-mono text-xs"
                                >
                                    Save
                                </Button>
                                {profileSaved && (
                                    <span className="flex items-center gap-1 text-sm text-positive">
                                        <Check className="h-4 w-4" />
                                        Saved
                                    </span>
                                )}
                            </div>
                        </form>
                    </div>

                    {/* Danger Zone */}
                    <div className="rounded-lg border border-negative/30 bg-negative/5">
                        <div className="border-b border-negative/30 p-4">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-negative" />
                                <h2 className="font-mono font-semibold text-negative">Danger Zone</h2>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Irreversible actions for your account
                            </p>
                        </div>
                        <div className="space-y-4 p-4">
                            {/* Delete Stats */}
                            {hasMetrics && (
                                <div className="flex items-center justify-between rounded-md border border-negative/20 bg-background p-4">
                                    <div>
                                        <p className="font-mono text-sm font-medium">Delete all stats</p>
                                        <p className="text-xs text-muted-foreground">
                                            Remove all your metrics data from Burnboard
                                        </p>
                                    </div>
                                    {showDeleteConfirm ? (
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setShowDeleteConfirm(false)}
                                                className="font-mono text-xs"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleDeleteStats}
                                                className="font-mono text-xs"
                                            >
                                                Confirm Delete
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="border-negative/50 font-mono text-xs text-negative hover:bg-negative/10"
                                            onClick={() => setShowDeleteConfirm(true)}
                                        >
                                            <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                            Delete Stats
                                        </Button>
                                    )}
                                </div>
                            )}

                            {/* Delete Account */}
                            <div className="flex items-center justify-between rounded-md border border-negative/20 bg-background p-4">
                                <div>
                                    <p className="font-mono text-sm font-medium">Delete account</p>
                                    <p className="text-xs text-muted-foreground">
                                        Permanently delete your account and all data
                                    </p>
                                </div>
                                <Link href="/settings/profile">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-negative/50 font-mono text-xs text-negative hover:bg-negative/10"
                                    >
                                        <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                        Delete Account
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-border py-6">
                    <div className="mx-auto max-w-4xl px-4 text-center">
                        <p className="font-mono text-xs text-muted-foreground">
                            <Link href="/" className="text-burn hover:underline">
                                Back to Leaderboard
                            </Link>
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
