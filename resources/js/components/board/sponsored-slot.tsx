import { cn } from '@/lib/utils';
import { Sparkles } from 'lucide-react';

interface SponsoredSlotProps {
    title: string;
    description?: string;
    href?: string;
    className?: string;
    variant?: 'banner' | 'row';
}

export function SponsoredSlot({
    title,
    description,
    href,
    className,
    variant = 'banner',
}: SponsoredSlotProps) {
    const content = (
        <>
            <div className="flex items-center gap-2">
                <Sparkles className="h-3.5 w-3.5 text-burn opacity-80" />
                <span className="font-mono text-xs font-semibold uppercase tracking-wider text-burn opacity-80">
                    Sponsored
                </span>
            </div>
            <div className="mt-1.5">
                <span className="font-medium">{title}</span>
                {description && (
                    <span className="ml-2 text-sm opacity-60">{description}</span>
                )}
            </div>
        </>
    );

    if (variant === 'row') {
        return (
            <div
                className={cn(
                    'board-row board-row-sponsored grid-cols-[1fr] !py-3',
                    className
                )}
            >
                {href ? (
                    <a
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer sponsored"
                        className="block hover:opacity-80 transition-opacity"
                    >
                        {content}
                    </a>
                ) : (
                    content
                )}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'rounded-md border border-burn/20 bg-burn-muted/30 p-4',
                className
            )}
        >
            {href ? (
                <a
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer sponsored"
                    className="block hover:opacity-80 transition-opacity"
                >
                    {content}
                </a>
            ) : (
                content
            )}
        </div>
    );
}

// Placeholder for when no sponsor is active
export function SponsoredSlotPlaceholder({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'rounded-md border border-dashed border-muted-foreground/20 p-4 text-center',
                className
            )}
        >
            <span className="font-mono text-xs text-muted-foreground">
                Your ad here &mdash;{' '}
                <a href="mailto:sponsor@burnboard.dev" className="text-burn hover:underline">
                    sponsor@burnboard.dev
                </a>
            </span>
        </div>
    );
}
