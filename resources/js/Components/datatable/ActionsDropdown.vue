<!--
ActionField Component

This component provides a reusable action menu for table rows in PrimeVue DataTables. It renders a subtle ellipsis button that, when clicked, opens a dropdown menu with customizable actions for the specific row data.

Key Features:
- Dynamic action visibility: Actions can be conditionally shown based on row data or user permissions using the 'show' callback.
- Dynamic labels: Action labels can be static strings or functions that receive the row data for customization (e.g., "Activate" vs "Deactivate" based on status).
- Confirmation support: For sensitive actions (e.g., delete), integrates with PrimeVue's ConfirmDialog to prompt user confirmation before executing the handler.
- Handler functions: Each action has a callback that receives the row data, allowing for operations like routing, API calls, or state changes.
- Severity styling: Supports PrimeVue severity levels (e.g., 'danger' for delete) to visually indicate action types.
- Popup menu: Uses PrimeVue Menu for the dropdown, ensuring accessibility and mobile-friendliness.
- Prevents premature menu closure: When a confirmation dialog is triggered, the menu remains open until the dialog is resolved.

Problems Solved:
- Reduces table clutter by consolidating multiple actions into a single button/menu.
- Ensures consistent action UI across all tables in the application.
- Handles permissions and row-specific logic without duplicating code in parent components.
- Integrates seamlessly with Inertia.js for routing and PrimeVue for UI consistency.
- Improves UX by providing confirmation for destructive actions, reducing accidental operations.

Props:
- actions: Array<TableAction<T>> - Required array of action definitions.
- row: T - Required row data object.

Dependencies:
- PrimeVue: Button, Menu, useConfirm (ConfirmDialog must be globally registered in the app).
- TypeScript types: TableAction from '@/types/table-actions'.
- Assumes hasPermission from usePermissions composable is available if used in 'show' callbacks.

Usage Example:
<ActionField :actions="userActions" :row="user" />

Where userActions might look like:
[
  { label: 'View', icon: 'pi pi-eye', handler: (user) => router.visit(`/users/${user.id}`) },
  { label: 'Delete', icon: 'pi pi-trash', severity: 'danger', confirm: { message: 'Confirm delete?' }, handler: (user) => deleteUser(user.id) }
]
-->
<template>
    <Button icon="pi pi-ellipsis-v" class="p-button-text p-button-rounded p-button-plain" @click="toggle"
        aria-label="Row Actions" />
    <Menu ref="menu" :model="menuItems" :popup="true" />
</template>

<script setup lang="ts" generic="T">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import { useConfirm } from 'primevue/useconfirm';
import type { TableAction } from '@/types/datatables';
import type { MenuItem } from 'primevue/menuitem';

const props = defineProps<{
    actions: TableAction<T>[];
    row: T;
}>();

const confirm = useConfirm();
const menu = ref();

const menuItems = computed<MenuItem[]>(() =>
    props.actions
        .filter((action) => {
            if (action.show === undefined) return true;
            return typeof action.show === 'boolean' ? action.show : action.show(props.row);
        })
        .map((action) => ({
            label: typeof action.label === 'function' ? action.label(props.row) : action.label,
            icon: typeof action.icon === 'function' ? action.icon(props.row) : action.icon,
            severity: action.severity,
            command: () => handleAction(action),
        }))
);

const toggle = (event: Event) => {
    menu.value.toggle(event);
};

const handleAction = async (action: TableAction<any>) => {
    // Close menu immediately for non-confirm actions
    if (!action.confirm) {
        menu.value?.hide();
        await action.handler(props.row);
        return;
    }

    // For actions requiring confirmation
    confirm.require({
        message: typeof action.confirm.message == 'function'? action.confirm.message(props.row) :action.confirm.message,
        header: typeof action.confirm.header == 'function'? action.confirm.header(props.row) :action.confirm.header ?? 'Confirm Action',
        icon: typeof action.confirm.icon == 'function'? action.confirm.icon(props.row) :action.confirm.icon ?? 'pi pi-exclamation-triangle',
        acceptLabel: 'Yes',
        rejectLabel: 'Cancel',
        acceptClass: action.confirm.acceptClass ?? 'p-button-danger',
        rejectClass: 'p-button-secondary p-button-outlined',
        accept: async () => {
            menu.value?.hide(); // Close menu after confirmation
            await action.handler(props.row);
        },
        reject: () => {
            // Menu stays open if user cancels (good UX)
        },
    });
};
</script>

<style scoped>
/* Custom CSS for subtle button styling */
:deep(.p-button) {
    width: 2rem;
    height: 2rem;
}
</style>
