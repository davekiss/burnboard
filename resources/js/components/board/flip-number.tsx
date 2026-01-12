import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

interface FlipNumberProps {
    value: number;
    format?: 'number' | 'currency' | 'compact';
    className?: string;
    prefix?: string;
    suffix?: string;
}

function formatValue(value: number, format: 'number' | 'currency' | 'compact'): string {
    if (format === 'currency') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    }

    if (format === 'compact') {
        if (value >= 1_000_000_000) {
            return `${(value / 1_000_000_000).toFixed(1)}B`;
        }
        if (value >= 1_000_000) {
            return `${(value / 1_000_000).toFixed(1)}M`;
        }
        if (value >= 1_000) {
            return `${(value / 1_000).toFixed(1)}K`;
        }
        return value.toString();
    }

    return new Intl.NumberFormat('en-US').format(value);
}

export function FlipNumber({
    value,
    format = 'number',
    className,
    prefix,
    suffix,
}: FlipNumberProps) {
    const [displayValue, setDisplayValue] = useState(formatValue(value, format));
    const [isUpdating, setIsUpdating] = useState(false);
    const prevValueRef = useRef(value);

    useEffect(() => {
        if (prevValueRef.current !== value) {
            setIsUpdating(true);

            // Small delay for animation effect
            const timer = setTimeout(() => {
                setDisplayValue(formatValue(value, format));
                prevValueRef.current = value;
            }, 150);

            const resetTimer = setTimeout(() => {
                setIsUpdating(false);
            }, 400);

            return () => {
                clearTimeout(timer);
                clearTimeout(resetTimer);
            };
        }
    }, [value, format]);

    const chars = displayValue.split('');

    return (
        <span className={cn('flip-display', className)}>
            {prefix && <span className="flip-digit opacity-60">{prefix}</span>}
            {chars.map((char, index) => (
                <span
                    key={`${index}-${char}`}
                    className={cn(
                        'flip-digit',
                        isUpdating && /\d/.test(char) && 'updating'
                    )}
                    style={{
                        animationDelay: isUpdating ? `${index * 30}ms` : undefined,
                    }}
                >
                    {char}
                </span>
            ))}
            {suffix && <span className="flip-digit opacity-60">{suffix}</span>}
        </span>
    );
}

// Simpler inline number display without flip animation
export function BoardNumber({
    value,
    format = 'number',
    className,
}: Omit<FlipNumberProps, 'prefix' | 'suffix'>) {
    const formatted = formatValue(value, format);

    return (
        <span className={cn('value-small tabular-nums', className)}>
            {formatted}
        </span>
    );
}
