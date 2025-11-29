// ===================================================================
// resources/js/types/form.ts
// 100% ACCURATE reflection of your Laravel custom_fields table
// Matches migration: 2025_01_13_040341_create_custom_fields_table.php
// ===================================================================

import type { InputHTMLAttributes } from 'vue';

// ------------------------------------------------------------------
// Supported field types (must match what you use in backend)
// ------------------------------------------------------------------
export type FieldType =
    | 'text'
    | 'password'
    | 'email'
    | 'number'
    | 'date'
    | 'textarea'
    | 'select'
    | 'checkbox'
    | 'radio'
    | 'file'
    | 'switch'
    | 'multiselect'
    | 'color'
    | 'rating';

// ------------------------------------------------------------------
// Base interface shared by all fields
// ------------------------------------------------------------------
export interface BaseCustomField {
    id: number;
    name: string;                    // DB: name (unique per model + school)
    label: string | null;            // DB: label
    placeholder: string | null;      // DB: placeholder
    field_type: FieldType;           // DB: field_type
    default_value: string | null;    // DB: text (we'll parse later)
    description: string | null;      // DB: text
    hint: string | null;             // DB: hint
    category: string | null;         // DB: category
    sort: number;                    // DB: sort (default 0)
    has_options: boolean;           // DB: has_options

    // JSON columns (stored as JSON in DB)
    rules?: string[] | null;                    // DB: json('rules')
    classes?: string | null;                  // DB: json('classes') → we'll convert to string
    options?: Array<{ label: string; value: any }> | null; // DB: json('options')
    extra_attributes?: Record<string, any> | null;         // DB: json('extra_attributes')
    field_options?: Record<string, any> | null;           // DB: json('field_options')

    // Casting & relations
    cast_as?: string | null;         // DB: cast_as
    model_type: string;              // DB: model_type (e.g. App\Models\Student)
    school_id?: string | null;       // DB: foreignUuid('school_id')

    // Timestamps
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

// ------------------------------------------------------------------
// Response from API: grouped by category
// ------------------------------------------------------------------
export interface FieldCategory {
    name: string;
    label: string;
    fields: CustomField[];
}

// ------------------------------------------------------------------
// Final usable field in Vue (normalized)
// ------------------------------------------------------------------
export interface CustomField extends BaseCustomField {
    // Normalized values (converted from DB format)
    label: string;
    placeholder: string;
    default_value: any;
    description: string;
    hint: string;
    category: string;

    // Converted from JSON string[] → string
    classes: string;

    // Parsed options (from JSON string or null)
    options: Array<{ label: string; value: any }>;

    // Always array (never null after normalization)
    rules: string[];

    // Extra attributes safe to spread
    extra_attributes: Record<string, any>;

    // For async select
    search_url?: string;
    search_key?: string;
    search_delay?: number;
    multiple?: boolean;
    icon: string;
}

// ------------------------------------------------------------------
// API Response Shape (from your Laravel controller)
// ------------------------------------------------------------------
export interface CustomFieldsApiResponse {
    categories: FieldCategory[];
    values?: Record<string, any>; // pre-filled values for edit forms
}

// ------------------------------------------------------------------
// Helper: Type guard for fields with options
// ------------------------------------------------------------------
export function isOptionField(field: CustomField): boolean {
    return ['select', 'multiselect', 'checkbox', 'radio', 'async-select'].includes(field.field_type);
}

export function isAsyncSelectField(field: CustomField): field is CustomField & {
    search_url: string;
} {
    return !!field.search_url;
}
