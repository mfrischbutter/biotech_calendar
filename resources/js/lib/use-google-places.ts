import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { setOptions, importLibrary } from '@googlemaps/js-api-loader';
import type { PageProps, PlaceSuggestion } from '@/types';

let initialized = false;
let placesReady = false;
let sessionToken: google.maps.places.AutocompleteSessionToken | null = null;

async function ensureLoaded(apiKey: string): Promise<boolean> {
    if (placesReady) return true;

    try {
        if (!initialized) {
            setOptions({ key: apiKey });
            initialized = true;
        }

        await importLibrary('places');
        sessionToken = new google.maps.places.AutocompleteSessionToken();
        placesReady = true;
        return true;
    } catch {
        return false;
    }
}

interface SuggestionItem {
    placeId: string;
    description: string;
    placePrediction: google.maps.places.PlacePrediction;
}

export function useGooglePlaces() {
    const page = usePage<PageProps>();
    const apiKey = page.props.googlePlacesApiKey;
    const suggestions = ref<SuggestionItem[]>([]);
    const loading = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    async function search(input: string) {
        if (debounceTimer) clearTimeout(debounceTimer);
        if (!apiKey || input.length < 3) {
            suggestions.value = [];
            return;
        }

        loading.value = true;

        debounceTimer = setTimeout(async () => {
            const ready = await ensureLoaded(apiKey);
            if (!ready) {
                loading.value = false;
                return;
            }

            try {
                const response = await google.maps.places.AutocompleteSuggestion
                    .fetchAutocompleteSuggestions({
                        input,
                        sessionToken: sessionToken ?? undefined,
                        language: 'de',
                        region: 'de',
                        includedPrimaryTypes: ['street_address', 'route', 'premise'],
                    });

                suggestions.value = (response.suggestions ?? [])
                    .filter((s): s is google.maps.places.AutocompleteSuggestion & { placePrediction: google.maps.places.PlacePrediction } =>
                        s.placePrediction != null,
                    )
                    .map((s) => ({
                        placeId: s.placePrediction.placeId,
                        description: s.placePrediction.text.toString(),
                        placePrediction: s.placePrediction,
                    }));
            } catch {
                suggestions.value = [];
            } finally {
                loading.value = false;
            }
        }, 300);
    }

    async function selectPlace(item: SuggestionItem): Promise<PlaceSuggestion | null> {
        try {
            const place = item.placePrediction.toPlace();
            await place.fetchFields({
                fields: ['displayName', 'formattedAddress', 'addressComponents', 'location'],
            });

            // Reset session token after details fetch (billing boundary)
            sessionToken = new google.maps.places.AutocompleteSessionToken();

            let route = '';
            let streetNumber = '';
            let zip = '';
            let city = '';

            for (const comp of place.addressComponents ?? []) {
                if (comp.types.includes('route')) route = comp.longText ?? '';
                if (comp.types.includes('street_number')) streetNumber = comp.longText ?? '';
                if (comp.types.includes('postal_code')) zip = comp.longText ?? '';
                if (comp.types.includes('locality')) city = comp.longText ?? '';
            }

            const street = streetNumber ? `${route} ${streetNumber}` : route;

            return {
                placeId: item.placeId,
                description: place.formattedAddress ?? '',
                street,
                zip,
                city,
                latitude: place.location?.lat() ?? 0,
                longitude: place.location?.lng() ?? 0,
            };
        } catch {
            return null;
        }
    }

    function clearSuggestions() {
        suggestions.value = [];
    }

    const isAvailable = !!apiKey;

    return { suggestions, loading, search, selectPlace, clearSuggestions, isAvailable };
}
