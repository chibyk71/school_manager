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
    };
    darkMode: boolean;
};

// resources/js/types.ts
export interface StatisticData {
  /** Main number to display */
  value: number;
  /** Title (e.g. "Total Students") */
  title: string;
  /** Background image for avatar */
  image: string;
  /** Tailwind background class (e.g. "bg-red-200/50") */
  severity: string;
  /** Growth change */
  growth: number;
  /** Active count */
  active: number;
  /** Inactive count */
  inactive: number;
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

/**
 * Filter operator enum.
 * @enum {string}
 */
export type FilterOperator =
    /**
     * Equal
     */
    | '$eq'
    /**
     * Equal (case-sensitive)
     */
    | '$eqc'
    /**
     * Not equal
     */
    | '$ne'
    /**
     * Less than
     */
    | '$lt'
    /**
     * Less than or equal to
     */
    | '$lte'
    /**
     * Greater than
     */
    | '$gt'
    /**
     * Greater than or equal to
     */
    | '$gte'
    /**
     * Included in an array
     */
    | '$in'
    /**
     * Not included in an array
     */
    | '$notIn'
    /**
     * Contains
     */
    | '$contains'
    /**
     * Does not contain
     */
    | '$notContains'
    /**
     * Contains (case-sensitive)
     */
    | '$containsc'
    /**
     * Does not contain (case-sensitive)
     */
    | '$notContainsc'
    /**
     * Is null
     */
    | '$null'
    /**
     * Is not null
     */
    | '$notNull'
    /**
     * Is between
     */
    | '$between'
    /**
     * Is not between
     */
    | '$notBetween'
    /**
     * Starts with
     */
    | '$startsWith'
    /**
     * Starts with (case-sensitive)
     */
    | '$startsWithc'
    /**
     * Ends with
     */
    | '$endsWith'
    /**
     * Ends with (case-sensitive)
     */
    | '$endsWithc'
    /**
     * Joins the filters in an "or" expression
     */
    | '$or'
    /**
     * Joins the filters in an "and" expression
     */
    | '$and';

export interface FilterCondition {
    field: string; // The field to filter on
    operator: FilterOperator; // The operator to use
    value: any; // The value to compare against
}

export interface FilterGroup {
    conditions: FilterCondition[]; // Array of conditions in this group
    operator: '$and' | '$or'; // How to combine conditions
}

export type Filter = FilterCondition | FilterGroup; // A filter can be a single condition or a group of conditions
export type Filters = Record<string, Filter>; // Filters are keyed by field name
export type SortOrder = 'asc' | 'desc'; // Sort order for sorting operations

export interface Sort {
    field: string; // The field to sort by
    order: SortOrder; // The sort order
}

export interface Pagination {
    page: number; // Current page number
    pageSize: number; // Number of items per page
}

export interface DataTableRequest<T = Record<string, unknown>> {
    filters?: Filters; // Filters to apply
    sort?: Sort[]; // Sorting options
    pagination?: Pagination; // Pagination options
    columns?: ColumnDefinition<T>[]; // Columns to include in the response
}

export interface DataTableResponse<T = Record<string, unknown>> {
    data: T[]; // The data for the current page
    totalRecords: number; // Total number of records available
    page: number; // Current page number
    pageSize: number; // Number of items per page
}

export interface DataTableState<T = Record<string, unknown>> {
    request: DataTableRequest<T>; // Current request state
    response: DataTableResponse<T>; // Current response state
}

export interface DataTableColumnState<T = Record<string, unknown>> {
    field: keyof T | string; // Field in the data object
    visible: boolean; // Whether the column is visible
    width?: string; // Width of the column
    sortOrder?: SortOrder; // Current sort order
    filterValue?: any; // Current filter value
}

export interface DataTableStateWithColumns<T = Record<string, unknown>> extends DataTableState<T> {
    columns: DataTableColumnState<T>[]; // State for each column
}

export interface DataTableColumnVisibility {
    [key: string]: boolean; // Maps column field to visibility state
}

export interface DataTableColumnSort {
    [key: string]: SortOrder; // Maps column field to sort order
}


/**
 * Represents a single menu item (leaf node or parent with submenu)
 */
export interface MenuItem {
  /** Display title */
  title: string;

  /** Tabler icon class (e.g., "ti ti-layout-dashboard") */
  icon?: string;

  /** Direct route – if present, this is a clickable link */
  link?: string;

  /** Optional badge (e.g., version number) */
  badge?: string;

  /** Nested submenu – if present, this item opens a dropdown */
  submenu?: MenuItem[];
}

/**
 * A menu section/group that appears in the sidebar
 */
export interface MenuSection {
  /** Header text shown above the group */
  header: string;

  /** List of menu items under this header */
  items: MenuItem[];
}

/**
 * Complete sidebar menu structure
 */
export type SidebarMenu = MenuSection[];

export type MenuItemWithChildren = MenuItem & { submenu: MenuItem[] };
export type MenuItemLeaf = MenuItem & { link: string; submenu?: never };

export type MenuItemAll = MenuItemWithChildren | MenuItemLeaf;