import { cn } from '@/lib/utils';

interface LiveIndicatorProps {
    className?: string;
    label?: string;
}

export function LiveIndicator({ className, label = 'Live' }: LiveIndicatorProps) {
    return (
        <span className={cn('live-indicator', className)}>
            {label}
        </span>
    );
}
