import { cn } from '@/lib/utils';

interface LiveIndicatorProps {
    className?: string;
    label?: string;
    isPolling?: boolean;
}

export function LiveIndicator({ className, label = 'Live', isPolling = false }: LiveIndicatorProps) {
    return (
        <span className={cn('live-indicator', isPolling && 'polling', className)}>
            {isPolling ? 'Updating...' : label}
        </span>
    );
}
