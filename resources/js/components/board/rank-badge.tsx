import { cn } from '@/lib/utils';

interface RankBadgeProps {
    rank: number;
    className?: string;
}

export function RankBadge({ rank, className }: RankBadgeProps) {
    const getBadgeClass = () => {
        switch (rank) {
            case 1:
                return 'rank-badge-1';
            case 2:
                return 'rank-badge-2';
            case 3:
                return 'rank-badge-3';
            default:
                return 'rank-badge-default';
        }
    };

    const getLabel = () => {
        switch (rank) {
            case 1:
                return '1ST';
            case 2:
                return '2ND';
            case 3:
                return '3RD';
            default:
                return `#${rank}`;
        }
    };

    return (
        <span className={cn('rank-badge', getBadgeClass(), className)}>
            {getLabel()}
        </span>
    );
}
