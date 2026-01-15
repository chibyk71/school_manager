/**
 * types/custom-fields.ts
 *
 * Central TypeScript definitions for Custom Fields module (frontend shape).
 *
 * These types represent:
 *   - The shape of custom field definitions coming from Laravel (via Inertia props or API)
 *   - The runtime data used in DynamicForm, InputWrapper, form builder, etc.
 *   - Partial alignment with backend CustomField model + CustomFieldType enum
 *
 * Key goals:
 *   - Strong typing for field_type → component mapping
 *   - Safe handling of optional/nullable fields from DB
 *   - Support for file/image constraints, conditional rules (future), options
 *   - Easy to extend when adding new field types or builder-specific props
 *
 * Usage:
 *   import type { CustomField, FieldCategory } from '@/types/custom-fields'
 *
 * Fits into the module:
 *   - Used by useCustomFields composable (fetched/merged fields)
 *   - Consumed by DynamicForm.vue & InputWrapper.vue for rendering
 *   - Extended in form builder for drag/drop metadata (sort, position, etc.)
 */

export type CustomFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'email'
    | 'tel'
    | 'url'
    | 'password'
    | 'date'
    | 'datetime'
    | 'select'
    | 'multiselect'
    | 'radio'
    | 'checkbox'
    | 'boolean'
    | 'file'
    | 'image'
    | 'color';

/**
 * Shape of the metadata object for EACH field type,
 * exactly as returned by CustomFieldType::toFrontendArray()
 */
export interface FieldTypeMetadata {
    name: string;           // Human name e.g. "Text", "Image Upload"
    icon: string;           // PrimeIcons class e.g. "pi pi-pencil"
    component: string;      // PrimeVue component name e.g. "InputText", "FileUpload"
    has_options: boolean;   // Whether this type supports options array
    is_file: boolean;       // Whether this is a file/image upload type
}

/**
 * Full map of field_type → metadata
 * This is the shape you get from Inertia shared data or API when using toFrontendArray()
 *
 * Example usage:
 * const types = page.props.customFieldTypes as FieldTypeMap;
 * const comp = types['image']?.component; // → "FileUpload" (typed!)
 */
export type FieldTypeMap = Record<CustomFieldType, FieldTypeMetadata>;

/**
 * Single custom field definition (frontend representation)
 */
export interface CustomField {
    /** Unique identifier (usually from DB) */
    id?: number | string;

    /** Machine name / key (used in form data & DB column) */
    name: string;

    /** Human-readable label (displayed in UI) */
    label?: string;

    /** Field type – must match one of CustomFieldType values */
    field_type: CustomFieldType;

    /** Whether this field is required */
    required?: boolean;

    /** Placeholder text for input */
    placeholder?: string;

    /** Small helper text below label (tooltip or inline) */
    hint?: string;

    /** Longer explanatory text (usually below input) */
    description?: string;

    /** Sort order (used for display & builder reordering) */
    sort?: number;

    /** Category / section name for grouping in forms */
    category?: string;

    /** Options for select, radio, checkbox, multiselect */
    options?: Array<{ value: string; label: string; color?: string; disabled?: boolean }>;

    /** Default value when creating new record */
    default_value?: string | number | boolean | null | any[];

    /** Laravel-style validation rules (array or object) */
    rules?: string[] | Record<string, any>;

    /** Tailwind classes or custom CSS classes to apply to wrapper/input */
    classes?: string | string[];

    /** Extra HTML attributes passed to the input element */
    extra_attributes?: Record<string, any>;

    /** File/image specific constraints */
    file_constraints?: {
        max_size_kb?: number;
        allowed_extensions?: string[];
        multiple?: boolean;
        accept?: string; // generated from extensions if needed
    };

    /** Future: conditional visibility / logic based on other fields */
    conditional_rules?: any; // TODO: define structure when implementing

    /** Icon class (pi-*) for prefix/suffix or field type badge */
    icon?: string;

    /** Whether field is disabled (admin override or conditional) */
    disabled?: boolean;

    /** Whether field is read-only */
    readonly?: boolean;

    // Builder-specific (added at runtime, not from backend)
    _builder?: {
        isSelected?: boolean;
        tempId?: string; // for unsaved new fields in builder
    };
}

/**
 * Grouped category for better form organization
 */
export interface FieldCategory {
    /** Internal key (e.g. 'personal_info', 'emergency') */
    name: string;

    /** Display title */
    label: string;

    /** Fields in this category, sorted by sort order */
    fields: CustomField[];

    /** Collapsed state (UI preference) */
    collapsed?: boolean;
}

/**
 * Shape of the response from useCustomFields / API
 */
export interface CustomFieldsResponse {
    categories: FieldCategory[];
    flatFields: CustomField[];
    initialValues: Record<string, any>;
}
