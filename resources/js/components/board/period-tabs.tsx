import { cn } from '@/lib/utils';

interface PeriodTabsProps {
    value: string;
    onChange: (value: string) => void;
    className?: string;
}

const periods = [
    { value: 'day', label: 'Today' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'all', label: 'All Time' },
];

export function PeriodTabs({ value, onChange, className }: PeriodTabsProps) {
    return (
        <div className={cn('period-tabs', className)}>
            {periods.map((period) => (
                <button
                    key={period.value}
                    onClick={() => onChange(period.value)}
                    className={cn(
                        'period-tab',
                        value === period.value && 'active'
                    )}
                >
                    {period.label}
                </button>
            ))}
        </div>
    );
}
