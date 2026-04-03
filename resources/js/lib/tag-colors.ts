/** Generate inline styles from a hex color for appointment cards. */
export function getTagStyle(color: string | null): {
    backgroundColor: string;
    borderColor: string;
    color: string;
} {
    if (!color) {
        return {
            backgroundColor: 'hsl(var(--muted))',
            borderColor: 'hsl(var(--border))',
            color: 'hsl(var(--muted-foreground))',
        };
    }

    return {
        backgroundColor: `${color}15`,
        borderColor: color,
        color: color,
    };
}

/** Generate dot style for tag indicators. */
export function getTagDotStyle(color: string | null): { backgroundColor: string } {
    return {
        backgroundColor: color || 'hsl(var(--muted-foreground))',
    };
}

/** Predefined palette matching Tag::COLORS on the backend. */
export const TAG_COLORS = [
    '#3b82f6', // blue
    '#f97316', // orange
    '#22c55e', // green
    '#a855f7', // purple
    '#ec4899', // pink
    '#6b7280', // gray
    '#ef4444', // red
    '#14b8a6', // teal
    '#f59e0b', // amber
    '#8b5cf6', // violet
    '#06b6d4', // cyan
    '#84cc16', // lime
    '#e11d48', // rose
    '#0ea5e9', // sky
    '#d946ef', // fuchsia
    '#78716c', // stone
] as string[];
