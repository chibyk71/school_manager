// types/datatable.ts
import { FilterMatchMode } from '@primevue/core/api';

// Re-export FilterMatchMode for easier access
export const FilterModes = FilterMatchMode;

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
    matchMode?: typeof FilterModes | string; // PrimeVue match mode
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
