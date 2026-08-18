/**
 * The app's TypeScript vocabulary, split by the surface it describes.
 *
 * Everything still imports from `@/types`; this file only decides where each
 * shape lives so no single declaration file grows past reading size.
 */

export * from './domain';
export * from './search';
export * from './notifications';
export * from './dashboard';
export * from './lists';
export * from './calendar';
export * from './records';

import type { SharedCompany, User } from './domain';

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    companyBranding: SharedCompany | null;
    googlePlacesApiKey: string | null;
    locale: string;
    translations: Record<string, string>;
};
