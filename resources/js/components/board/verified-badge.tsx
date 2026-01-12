import { cn } from '@/lib/utils';
import { BadgeCheck, ShieldQuestion } from 'lucide-react';

interface VerifiedBadgeProps {
    isVerified: boolean;
    score?: number;
    showScore?: boolean;
    className?: string;
    size?: 'sm' | 'md' | 'lg';
}

export function VerifiedBadge({
    isVerified,
    score,
    showScore = false,
    className,
    size = 'sm',
}: VerifiedBadgeProps) {
    const sizeClasses = {
        sm: 'h-3.5 w-3.5',
        md: 'h-4 w-4',
        lg: 'h-5 w-5',
    };

    if (!isVerified) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1',
                className
            )}
            title={`Verified via public GitHub activity${score ? ` (${score}% match)` : ''}`}
        >
            <BadgeCheck
                className={cn(
                    sizeClasses[size],
                    'text-positive'
                )}
            />
            {showScore && score !== undefined && (
                <span className="font-mono text-xs text-positive">
                    {score}%
                </span>
            )}
        </span>
    );
}

// Tooltip component explaining verification
export function VerificationTooltip({ className }: { className?: string }) {
    return (
        <div className={cn('max-w-xs text-xs', className)}>
            <div className="mb-2 flex items-center gap-2 font-medium">
                <BadgeCheck className="h-4 w-4 text-positive" />
                Verified Stats
            </div>
            <p className="text-muted-foreground">
                This user's claimed Claude Code metrics have been cross-referenced
                with their public GitHub activity. Commits, PRs, and line changes
                roughly match what they've reported.
            </p>
            <p className="mt-2 text-muted-foreground">
                <span className="font-medium">Note:</span> Only public repository
                activity can be verified. Private repo work may not be reflected.
            </p>
        </div>
    );
}
