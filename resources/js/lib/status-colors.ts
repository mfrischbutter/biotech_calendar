/** Mix a hex color with white to produce a solid light tint. */
function lightTint(hex: string, amount = 0.88): string {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const mix = (c: number) => Math.round(c + (255 - c) * amount);
    return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
}

/** Generate inline styles from a hex color for appointment cards. */
export function getStatusStyle(color: string | null): {
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
        backgroundColor: lightTint(color),
        borderColor: color,
        color: color,
    };
}

/** Generate dot style for status indicators. */
export function getStatusDotStyle(color: string | null): { backgroundColor: string } {
    return {
        backgroundColor: color || 'hsl(var(--muted-foreground))',
    };
}

/** Predefined color palette for quick selection. */
export const STATUS_COLORS = [
    '#3b82f6', // blue
    '#22c55e', // green
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#6b7280', // gray
] as string[];
