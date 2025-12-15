<!-- resources/js/Pages/Settings/School/Roles/RoleFormModal.vue -->
<!--
  RoleFormModal.vue

  Purpose:
  - Unified modal for both creating and editing role details (name, display_name, description, department)
  - Simplifies maintenance by handling both modes in one component
  - Create mode: Shows "Copy permissions from" dropdown and "Proceed to manage permissions" checkbox
  - Edit mode: Hides create-specific fields, focuses on updating existing role
  - Department field: Required for all roles (linked to organizational structure for dashboards, reporting, etc.)
  - Uses useModalForm for consistent form handling, validation, toast, modal closing, and reloads
  - Fully integrated with Inertia, PrimeVue, AsyncSelect (for departments if needed), and your modal system

  Workflow:
  - Opened from Index.vue with modals.open('role-form', { role: null or existing, departments, existingRoles })
  - Create: POST to admin.roles.store → optional copy permissions + redirect to matrix if "proceed" checked
  - Edit: PUT to admin.roles.update → updates details only (permissions managed separately)
  - Department: Required select – assumes pre-loaded departments prop (or use AsyncSelect for search)
  - On success: Closes modal + refreshes roles table via reload: ['roles']

  Future notes:
  - If departments need async search, swap to AsyncSelect and provide search_url in props
  - To add fields like icon/color, extend form and validation here
  - Copy logic is server-side in RolesController@store
-->

<script setup lang="ts">
import AsyncSelect from '@/Components/forms/AsyncSelect.vue';
import { useModalForm } from '@/composables/useModalForm';
import { modals } from '@/helpers';
import { InputText, Textarea, Checkbox, Select, Button } from 'primevue';
import { computed } from 'vue';

// Props from modals.open('role-form', { role, departments, existingRoles })
const props = defineProps<{
    role?: {
        id: number;
        name: string;
        display_name: string;
        description: string | null;
        department_id: number | null; // Assuming role has department_id
    };
    departments: Array<{ value: number; label: string }>; // Pre-loaded department options
    existingRoles?: Array<{ value: number; label: string }>; // Only for create
}>();

// Detect mode
const isEdit = computed(() => !!props.role);

// Initial form data
const initialForm = {
    name: props.role?.name ?? '',
    display_name: props.role?.display_name ?? '',
    department_id: props.role?.department_id ?? null,
    description: props.role?.description ?? '',
    copy_from_role_id: null as number | null,
    proceed_to_permissions: true,
};

// Form config
const { form, submit, isLoading, errors } = useModalForm(initialForm, {
    resource: 'roles',
    resourceId: props.role?.id,
    method: isEdit.value ? 'put' : 'post',
    successMessage: isEdit.value ? 'Role updated successfully' : 'Role created successfully',
    reload: ['roles'], // Refresh table on success
    onSuccess: () => {
        modals.close(); // Always close modal
    },
});

// Hide create-only fields in edit mode
const showCopyFrom = computed(() => !isEdit.value);
const showProceed = computed(() => !isEdit.value);
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="border-b pb-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {{ isEdit ? 'Edit Role' : 'Create New Role' }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ isEdit ? 'Update basic details for this role.' : `Create a new role and optionally copy permissions
                from an existing one.` }}
            </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit()" class="space-y-5">
            <!-- Role Name (slug) -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Role Name <span class="text-red-500">*</span>
                </label>
                <InputText id="name" v-model="form.name" :placeholder="isEdit ? '' : 'e.g., senior_teacher'"
                    class="w-full" :invalid="!!errors.name" aria-describedby="name-error" />
                <small v-if="errors.name" id="name-error" class="text-red-600 text-xs mt-1">
                    {{ errors.name }}
                </small>
                <p v-if="!isEdit" class="text-xs text-gray-500 mt-1">Lowercase, underscores only. Used internally.</p>
            </div>

            <!-- Display Name -->
            <div>
                <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Display Name <span class="text-red-500">*</span>
                </label>
                <InputText id="display_name" v-model="form.display_name"
                    :placeholder="isEdit ? '' : 'e.g., Senior Teacher'" class="w-full"
                    :invalid="!!errors.display_name" />
                <small v-if="errors.display_name" class="text-red-600 text-xs mt-1">
                    {{ errors.display_name }}
                </small>
            </div>

            <!-- Department (Required) -->
            <div>
                <label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Department <span class="text-red-500">*</span>
                </label>
                <AsyncSelect id="department_id" :field="{
                    search_url: '/departments/search', // Your API endpoint
                    multiple: false,
                    field_options: {
                        option_label: 'label',
                        option_value: 'value',
                        search_key: 'q',
                        search_delay: 300,
                    }
                }" v-model="form.department_id" :invalid="!!errors.department_id" />
                <small v-if="errors.department_id" class="text-red-600 text-xs mt-1">
                    {{ errors.department_id }}
                </small>
                <p class="text-xs text-gray-500 mt-1">Required: Roles are linked to departments for organizational
                    structure and dashboard personalization.</p>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Description (Optional)
                </label>
                <Textarea id="description" v-model="form.description" rows="3"
                    placeholder="Brief description of this role's responsibilities..." class="w-full"
                    :invalid="!!errors.description" />
            </div>

            <!-- Copy Permissions From (Create Only) -->
            <div v-if="showCopyFrom">
                <label for="copy_from_role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Copy Permissions From (Optional)
                </label>
                <AsyncSelect id="copy_from_role_id" :field="{
                    search_url: '/roles/search', // Your API endpoint for searching roles
                    multiple: false,
                    field_options: {
                        option_label: 'label',
                        option_value: 'value',
                        search_key: 'q',
                        search_delay: 300,
                        search_params: { exclude_current: true } // Optional: exclude self in edit mode if needed
                    }
                }" v-model="form.copy_from_role_id" />
                <p class="text-xs text-gray-500 mt-1">
                    Permissions will be copied immediately on creation.
                </p>
            </div>

            <!-- Proceed to Permissions (Create Only) -->
            <div v-if="showProceed" class="flex items-center gap-3">
                <Checkbox id="proceed_to_permissions" v-model="form.proceed_to_permissions" :binary="true" />
                <label for="proceed_to_permissions"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                    Proceed to manage permissions after creation
                </label>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <Button type="button" label="Cancel" severity="secondary" outlined @click="modals.close()" />
                <Button type="submit" :label="isEdit ? 'Update Role' : 'Create Role'" severity="primary"
                    :loading="isLoading" :disabled="isLoading" />
            </div>
        </form>
    </div>
</template>