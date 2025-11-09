// resources/js/types/dashboard.d.ts
/** Generic widget props */
export interface WidgetProps {
    /** Title shown on the card */
    title: string;
    /** Optional icon name (PrimeVue) */
    icon?: string;
}

/** API response shape for all widgets */
export interface WidgetResponse<T = any> {
    data: T;
    meta?: {
        refreshed_at: string;
    };
}
