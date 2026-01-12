import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/board';
import { Head, Link, useForm } from '@inertiajs/react';
import { Flame, Terminal, ArrowRight } from 'lucide-react';
import { FormEvent } from 'react';

export default function DeviceVerify() {
    const { data, setData, post, processing, errors } = useForm({
        user_code: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/device');
    };

    // Format input as user types (add dash after 4 chars)
    const handleInputChange = (value: string) => {
        // Remove any existing dashes and non-alphanumeric chars
        const cleaned = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

        // Add dash after 4 characters if we have more than 4 chars
        if (cleaned.length > 4) {
            const formatted = cleaned.slice(0, 4) + '-' + cleaned.slice(4, 8);
            setData('user_code', formatted);
        } else {
            setData('user_code', cleaned);
        }
    };

    return (
        <>
            <Head title="Verify Device - Burnboard" />
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
                    <div className="w-full max-w-md">
                        {/* Icon */}
                        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-lg bg-burn-muted">
                            <Terminal className="h-8 w-8 text-burn" />
                        </div>

                        {/* Title */}
                        <div className="mb-8 text-center">
                            <h1 className="font-mono text-2xl font-bold">Device Authorization</h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Enter the code shown in your terminal to connect your Claude Code telemetry.
                            </p>
                        </div>

                        {/* Form */}
                        <form onSubmit={handleSubmit}>
                            <div className="space-y-6">
                                <div>
                                    <label
                                        htmlFor="user_code"
                                        className="section-header"
                                    >
                                        Device Code
                                    </label>
                                    <input
                                        id="user_code"
                                        type="text"
                                        placeholder="XXXX-XXXX"
                                        value={data.user_code}
                                        onChange={(e) => handleInputChange(e.target.value)}
                                        className="device-code-input w-full"
                                        maxLength={9}
                                        autoComplete="off"
                                        autoFocus
                                    />
                                    {errors.user_code && (
                                        <p className="mt-2 font-mono text-sm text-negative">
                                            {errors.user_code}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full font-mono"
                                    disabled={processing || data.user_code.length < 8}
                                >
                                    {processing ? (
                                        'Authorizing...'
                                    ) : (
                                        <>
                                            Authorize Device
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>

                        {/* Help Text */}
                        <p className="mt-8 text-center text-xs text-muted-foreground">
                            By authorizing, you agree to share your Claude Code telemetry with Burnboard.
                        </p>
                    </div>
                </main>

                {/* Footer */}
                <footer className="py-4 text-center">
                    <p className="font-mono text-xs text-muted-foreground">
                        <Link href="/" className="hover:text-burn hover:underline">
                            Back to Leaderboard
                        </Link>
                    </p>
                </footer>
            </div>
        </>
    );
}
