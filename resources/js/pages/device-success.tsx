import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/board';
import { Head, Link } from '@inertiajs/react';
import { Flame, CheckCircle, ArrowRight, Terminal } from 'lucide-react';

export default function DeviceSuccess() {
    return (
        <>
            <Head title="Success - Burnboard" />
            <div className="flex min-h-screen flex-col bg-background">
                {/* Header */}
                <header className="site-header">
                    <div className="mx-auto flex max-w-md items-center justify-between px-4 py-3">
                        <Link href="/" className="logo">
                            <Flame className="logo-icon" />
                            <span>burnboard</span>
                        </Link>
                        <ThemeToggle />
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex flex-1 flex-col items-center justify-center px-4 py-12">
                    <div className="w-full max-w-md text-center">
                        {/* Success Icon */}
                        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-positive/10">
                            <CheckCircle className="h-8 w-8 text-positive" />
                        </div>

                        {/* Title */}
                        <h1 className="mb-2 font-mono text-2xl font-bold">Successfully Connected!</h1>
                        <p className="text-sm text-muted-foreground">
                            Your Claude Code telemetry is now connected to Burnboard.
                        </p>

                        {/* Info Box */}
                        <div className="my-8 rounded-lg border border-border bg-card p-4">
                            <div className="flex items-start gap-3 text-left">
                                <Terminal className="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                                <div>
                                    <p className="font-mono text-sm font-medium">
                                        You can close this window
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Return to your terminal to continue. Your stats will appear on the leaderboard after your next Claude Code session.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Link href="/" className="flex-1">
                                <Button variant="outline" className="w-full font-mono text-xs">
                                    <Flame className="mr-1.5 h-3.5 w-3.5 text-burn" />
                                    View Leaderboard
                                </Button>
                            </Link>
                            <Link href="/dashboard" className="flex-1">
                                <Button className="w-full font-mono text-xs">
                                    Go to Dashboard
                                    <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
