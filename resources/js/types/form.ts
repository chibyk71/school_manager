/**
 * types/form.ts
 *
 * General form-related types used across dynamic forms, modals, and builder.
 *
 * Helps keep DynamicForm.vue, InputWrapper.vue, useModalForm.ts, etc. strongly typed.
 */

import type { CustomField, FieldCategory } from './custom-fields';

/**
 * Form values shape (what v-model binds to)
 */
export type FormValues = Record<string, any>;

/**
 * Inertia form errors shape (from useForm().errors)
 */
export type FormErrors = Record<string, string | string[]>;

/**
 * Props for InputWrapper.vue
 */
export interface InputWrapperProps {
    field: CustomField;
    error?: string | string[] | null;
    label?: string;
    disabled?: boolean;
    readonly?: boolean;
    prefixIcon?: string;
    suffixIcon?: string;
}

/**
 * Props for DynamicForm.vue
 */
export interface DynamicFormProps {
    fields: CustomField[] | FieldCategory[];
    modelValue: FormValues;
    errors?: FormErrors;
    disabled?: boolean;
    readonly?: boolean;
}
