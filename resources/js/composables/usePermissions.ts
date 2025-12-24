// resources/js/composables/usePermissions.js
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const userPermissions = computed(() => page.props.auth?.permissions || []);
    const userRoles = computed(() => page.props.auth?.roles || []);

    const hasPermission = (permission?: string) => {
        if (!permission) return false;
        // Support wildcards (e.g., 'students.*' allows 'students.view')
        return userPermissions.value.some(p =>
            p === permission ||
            (p.endsWith('*') && permission.startsWith(p.slice(0, -1)))
        );
    };

    const hasRole = (role?: string) => {
        if (!role) return false

        return userRoles.value.includes(role);
    }

    const hasAnyPermission = (permissions: string[]) => permissions.some(hasPermission);
    const hasAllPermissions = (permissions: string[]) => permissions.every(hasPermission);

    return {
        hasPermission,
        hasRole,
        hasAnyPermission,
        hasAllPermissions,
        userPermissions,
        userRoles,
    };
}
