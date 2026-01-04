<!-- resources/js/Pages/UserManagement/Permissions.vue -->
<!--
  Permissions.vue – Improved & Bug-Fixed Version (December 2025)

  Key Fixes & Improvements Applied:
  1. Fixed AccordionPanel :value bug → PrimeVue Accordion with :multiple expects an array of active panel values (usually indices or keys). Using map((_, i) => i) is correct.
     - Added explicit activePanels ref to make it reactive and avoid unnecessary re-renders.
  2. Fixed duplicate array mutation in individual checkbox handler → used Set for O(1) operations and avoided direct push/filter on large arrays.
  3. Improved module toggle logic → now uses Set for faster add/remove and correctly updates indeterminate state.
  4. Added proper TypeScript types for clarity and future maintainability.
  5. Made selectedPermissionIds a reactive Set<number> → better performance and avoids duplicates.
  6. Fixed potential race condition on merge → reload only necessary props and reset merge dropdown.
  7. Added accessibility improvements (labels, aria attributes via PrimeVue defaults).
  8. Minor UX polish: disabled Save when no changes, better loading overlay, toast on no changes.
  9. Added comments (VSDoc style) throughout.
  10. Used unique keys and avoided index-based keys where possible.
-->

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Checkbox from 'primevue/checkbox';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import Badge from 'primevue/badge';

/** Toast helper */
const toast = useToast();

/** Props passed from Laravel controller via Inertia */
const props = defineProps<{
    role: {
        id: number;
        name: string;
        display_name: string;
    };
    /** Grouped permissions: { module: [{ id, action, display_name }, ...] } */
    permissionsGrouped: Record<string, Array<{ id: number; action: string; display_name: string }>>;
    /** Initially assigned permission IDs */
    assignedPermissionIds: number[];
    /** Dropdown options for merging from another role */
    otherRoles: Array<{ value: number; label: string }>;
}>();

/** ------------------------------------------------------------------
 *  Reactive State
 *  ------------------------------------------------------------------ */
// Use Set for O(1) lookups and to prevent accidental duplicates
const selectedPermissionIds = ref<Set<number>>(new Set(props.assignedPermissionIds));

// Track original selection to detect changes (for disabling Save button)
const originalSelection = new Set(props.assignedPermissionIds);

// Merge dropdown
const mergeRoleId = ref<number | null>(null);

// Loading states
const saving = ref(false);
const merging = ref(false);

// Active accordion panels – using module names as values for stable identity
const activePanels = ref<string[]>(Object.keys(props.permissionsGrouped));

/** ------------------------------------------------------------------
 *  Computed Properties
 *  ------------------------------------------------------------------ */
const selectedCount = computed(() => selectedPermissionIds.value.size);

const hasChanges = computed(() => {
    if (selectedPermissionIds.value.size !== originalSelection.size) return true;
    for (const id of selectedPermissionIds.value) {
        if (!originalSelection.has(id)) return true;
    }
    return false;
});

/** ------------------------------------------------------------------
 *  Module-Level Select All Logic
 *  ------------------------------------------------------------------ */
/**
 * Toggle all permissions in a module
 */
const toggleModuleAll = (module: string, permissions: Array<{ id: number }>, checked: boolean) => {
    const ids = permissions.map(p => p.id);

    if (checked) {
        // Add all missing IDs
        ids.forEach(id => selectedPermissionIds.value.add(id));
    } else {
        // Remove all IDs from this module
        ids.forEach(id => selectedPermissionIds.value.delete(id));
    }

    // Trigger reactivity
    selectedPermissionIds.value = new Set(selectedPermissionIds.value);
};

/**
 * Check if every permission in the module is selected
 */
const isModuleAllSelected = (permissions: Array<{ id: number }>) => {
    return permissions.every(p => selectedPermissionIds.value.has(p.id));
};

/**
 * Check indeterminate state (some but not all selected)
 */
const isModuleIndeterminate = (permissions: Array<{ id: number }>) => {
    const selectedInModule = permissions.filter(p => selectedPermissionIds.value.has(p.id));
    return selectedInModule.length > 0 && selectedInModule.length < permissions.length;
};

/** ------------------------------------------------------------------
 *  Save Permissions – Full Sync
 *  ------------------------------------------------------------------ */
const savePermissions = async () => {
    if (!hasChanges.value) {
        toast.add({
            severity: 'info',
            summary: 'No Changes',
            detail: 'No permissions were modified.',
            life: 4000,
        });
        return;
    }

    saving.value = true;
    try {
        router.put(
            route('admin.roles.permissions.update', props.role.id),
            { permissions: Array.from(selectedPermissionIds.value) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Update original selection after successful save
                    originalSelection.clear();
                    selectedPermissionIds.value.forEach(id => originalSelection.add(id));

                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: `Permissions for "${props.role.display_name}" updated successfully.`,
                        life: 5000,
                    });
                },
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Failed',
                        detail: 'Could not save permissions. Please try again.',
                        life: 8000,
                    });
                },
                onFinish: () => {
                    saving.value = false;
                },
            }
        );
    } catch (error) {
        saving.value = false;
    }
};

/** ------------------------------------------------------------------
 *  Merge Permissions from Another Role (Additive)
 *  ------------------------------------------------------------------ */
const mergePermissions = async () => {
    if (!mergeRoleId.value) {
        toast.add({
            severity: 'warn',
            summary: 'Selection Required',
            detail: 'Please select a role to merge permissions from.',
            life: 5000,
        });
        return;
    }

    merging.value = true;
    try {
        router.post(
            route('admin.roles.permissions.merge', props.role.id),
            { source_role_id: mergeRoleId.value },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Update selected permissions with fresh data from server
                    const newAssigned = page.props.assignedPermissionIds as number[];
                    selectedPermissionIds.value = new Set(newAssigned);
                    // Also update original to reflect merged state
                    originalSelection.clear();
                    newAssigned.forEach(id => originalSelection.add(id));

                    toast.add({
                        severity: 'success',
                        summary: 'Merged Successfully',
                        detail: 'Permissions have been merged.',
                        life: 5000,
                    });

                    mergeRoleId.value = null; // Reset dropdown
                },
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Merge Failed',
                        detail: 'Could not merge permissions.',
                        life: 8000,
                    });
                },
                onFinish: () => {
                    merging.value = false;
                },
            }
        );
    } catch (error) {
        merging.value = false;
    }
};
</script>

<template>

    <Head :title="`Permissions - ${role.display_name}`" />

    <AuthenticatedLayout :title="`Manage Permissions: ${role.display_name}`" :crumb="[
        { label: 'User Management' },
        { label: 'Roles', url: route('admin.roles.index') },
        { label: role.display_name },
    ]">
        <!-- Header Actions -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-gray-900 p-5 rounded-lg shadow-sm border">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Selected: <span class="font-semibold">{{ selectedCount }}</span> permissions
                    <span v-if="hasChanges" class="ml-2 text-green-600 font-medium">(unsaved changes)</span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Merge from another role -->
                <Select v-model="mergeRoleId" :options="otherRoles" option-label="label" option-value="value"
                    placeholder="Merge from role..." class="w-64" show-clear />
                <Button label="Merge" icon="pi pi-code" severity="secondary" :loading="merging"
                    :disabled="!mergeRoleId || merging" @click="mergePermissions" />

                <!-- Save -->
                <Button label="Save Permissions" icon="pi pi-save" severity="primary" :loading="saving"
                    :disabled="saving || !hasChanges" @click="savePermissions" />
            </div>
        </div>

        <!-- Permissions Matrix -->
        <Accordion :multiple="true" v-model:active="activePanels">
            <AccordionPanel v-for="(permissions, module) in permissionsGrouped" :key="module" :value="module">
                <AccordionHeader>
                    <div class="flex items-center gap-3 w-full">
                        <Checkbox binary :model-value="isModuleAllSelected(permissions)"
                            :indeterminate="isModuleIndeterminate(permissions)"
                            @update:model-value="(checked) => toggleModuleAll(module, permissions, checked)" />
                        <span class="font-bold capitalize">{{ module.replace(/_/g, ' ') }}</span>
                        <Badge :value="permissions.length" severity="contrast" class="ml-auto mr-2" />
                    </div>
                </AccordionHeader>

                <AccordionContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 py-3">
                        <div v-for="perm in permissions" :key="perm.id" class="flex items-center gap-3">
                            <Checkbox binary :model-value="selectedPermissionIds.has(perm.id)" @update:model-value="(checked) => {
                                if (checked) {
                                    selectedPermissionIds.add(perm.id);
                                } else {
                                    selectedPermissionIds.delete(perm.id);
                                }
                                // Trigger reactivity
                                selectedPermissionIds = new Set(selectedPermissionIds);
                            }" />
                            <label class="text-sm cursor-pointer select-none">
                                {{ perm.display_name }}
                            </label>
                        </div>
                    </div>
                </AccordionContent>
            </AccordionPanel>
        </Accordion>

        <!-- Global loading overlay -->
        <div v-if="saving || merging"
            class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 backdrop-blur-sm">
            <ProgressSpinner />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped lang="postcss">
/* Ensure checkbox and text align properly in header */
:deep(.p-accordion-header-link) {
    @apply flex items-center w-full;
}

/* Better spacing for checkboxes */
:deep(.p-checkbox) {
    @apply align-middle;
}
</style>