import { Head } from '@inertiajs/react';
import { Flame, Loader2 } from 'lucide-react';
import { useEffect } from 'react';

interface Props {
    redirectUrl: string;
}

export default function DeviceRedirect({ redirectUrl }: Props) {
    useEffect(() => {
        if (redirectUrl && typeof window !== 'undefined') {
            window.location.replace(redirectUrl);
        }
    }, [redirectUrl]);

    // Also trigger redirect immediately on render (backup for SSR)
    if (typeof window !== 'undefined' && redirectUrl) {
        setTimeout(() => {
            window.location.replace(redirectUrl);
        }, 100);
    }

    return (
        <>
            <Head title="Redirecting... - Burnboard">
                {redirectUrl && <meta httpEquiv="refresh" content={`0;url=${redirectUrl}`} />}
            </Head>
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-4">
                <div className="flex flex-col items-center">
                    <div className="relative mb-6">
                        <Flame className="h-12 w-12 text-burn" />
                        <Loader2 className="absolute -right-1 -top-1 h-5 w-5 animate-spin text-muted-foreground" />
                    </div>
                    <p className="mb-4 font-mono text-sm text-muted-foreground">
                        Redirecting to GitHub...
                    </p>
                    {redirectUrl && (
                        <a
                            href={redirectUrl}
                            className="font-mono text-xs text-burn hover:underline"
                        >
                            Click here if not redirected
                        </a>
                    )}
                </div>
            </div>
        </>
    );
}
