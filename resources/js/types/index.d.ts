import { InputTypeHTMLAttribute } from "vue";

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: {
        error: string,
        success: string
    }
};

export type Statistic = {
    stat: number,
    percentage: number,
    title: string,
    active: number,
    inactive: number
}

export interface Student {
    [string]: unknown;
    id: number;
    first_name: string;
    last_name: string;
    middle_name: string;
    enrolment_number: string;
    email: string;
    email_verified_at?: string;
    phone: string;
    address: Address;
    created_at: string;
    updated_at: string;
};

export interface Address {
    id: number;
    addressable_id: number;
    addressable_type: string;
    line1: string;
    line2: string;
    city: string;
    state: string;
    zip: string;
    country: string;
    created_at: string;
    updated_at: string;
}

export interface Field {
    name: string; // The unique identifier for the custom field.
    label: string; // The human-readable label for the field.
    placeholder?: string; // Placeholder text for input fields.
    classes?: string; // Additional CSS classes for styling.
    field_type: InputTypeHTMLAttribute | 'select' | 'textarea'; // Specifies the type of input.
    options?: { label: string, value: string }[]; // Available options for select, radio, or checkbox fields.
    default_value?: any; // Default value for the field.
    description?: string; // Longer description for the field.
    hint?: string; // Tooltip or hint for the field.
    category?: string; // Grouping of fields into categories.
    sort?: number;
    extra_attributes?: Record<string, any> | null; // flexible extra data
    field_options?: Record<string, any> | null; // advanced settings
    has_options?: boolean;
}

export type CustomField = {
    id: number,
    rules?: string[]; // Laravel validation rules (e.g., 'required', 'email').
    created_at?: string; // ISO date string
    updated_at?: string; // ISO date string
    cast_as?: string | null; // e.g., "string", "integer", "boolean"
    entity_id?: number | string | null; // ID of the related entity
    model_type?: string | null; // Laravel morph type
} & Field;

export interface Category {
    name: string;
    fields: CustomField[];
}

// types/datatable.ts

import type { FilterMatchMode } from '@primevue/core/api';

export type ColumnFilterType =
    | 'text'        // standard InputText
    | 'dropdown'    // single select Dropdown
    | 'multiselect' // multi select
    | 'date'        // Calendar
    | 'number'      // numeric input
    | 'boolean'     // checkbox
    | 'custom';     // any other custom component

export interface ColumnDefinition<T = Record<string, unknown>> {
    field: keyof T | string;           // field in your data object
    header: string;                    // column header text
    sortable?: boolean;                // enable sorting
    matchMode?: FilterMatchMode | string; // PrimeVue match mode
    filterType?: ColumnFilterType;     // type of filter UI
    filterOptions?: any[];             // options for dropdown/multiselect
    filterPlaceholder?: string;        // placeholder text for filter
    bodyClass?: string;                 // CSS class for body cells
    headerClass?: string;               // CSS class for header
    style?: Record<string, string>;     // inline styles
    width?: string;                     // fixed width
    render?: (rowData: T) => any;       // custom cell rendering
}
