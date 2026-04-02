import { usePage } from '@inertiajs/vue3';

/**
 * Translation composable that reads from Laravel's shared translations.
 *
 * Usage:
 *   const { t } = useTrans();
 *   t('Save')           // → "Speichern"
 *   t('Delete :name?', { name: 'Max' }) // → "Max löschen?"
 */
export function useTrans() {
    const page = usePage();

    function t(key: string, replacements?: Record<string, string>): string {
        const translations = (page.props.translations ?? {}) as Record<string, string>;
        let value = translations[key] ?? key;

        if (replacements) {
            for (const [k, v] of Object.entries(replacements)) {
                value = value.replace(`:${k}`, v);
            }
        }

        return value;
    }

    return { t };
}
