import { useAppearance } from '@/hooks/use-appearance';
import { Moon, Sun, Monitor } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ThemeToggleProps {
    className?: string;
}

export function ThemeToggle({ className }: ThemeToggleProps) {
    const { appearance, updateAppearance } = useAppearance();

    const cycle = () => {
        const modes = ['light', 'dark', 'system'] as const;
        const currentIndex = modes.indexOf(appearance);
        const nextIndex = (currentIndex + 1) % modes.length;
        updateAppearance(modes[nextIndex]);
    };

    return (
        <button
            onClick={cycle}
            className={cn(
                'flex h-8 w-8 items-center justify-center rounded-md',
                'text-muted-foreground hover:text-foreground',
                'hover:bg-secondary transition-colors',
                className
            )}
            title={`Current: ${appearance}`}
        >
            {appearance === 'light' && <Sun className="h-4 w-4" />}
            {appearance === 'dark' && <Moon className="h-4 w-4" />}
            {appearance === 'system' && <Monitor className="h-4 w-4" />}
        </button>
    );
}
