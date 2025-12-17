// src/components/Modals/ModalDirectory.ts
import { Component } from 'vue';

type AsyncComponentLoader = () => Promise<Component>;

export const ModalComponentDirectory: Record<string, AsyncComponentLoader> = {
    'custom-field': () => import('./Create/CustomFields.vue'),
    'assign-teacher-subject': () => import('./Create/AssignTeacherSubjectClass.vue'),
    'add-staff': () => import('./Create/AddStaff.vue'),
    'assign-department-role': () => import('./Create/AssignRoleDepartmentModal.vue'),
    'admin-password-reset': () => import('./Create/AdminPasswordResetModal.vue'),
    'create-role': () => import('./Create/Roles/CreateModal.vue'),
    'department': () => import('@/Components/Modals/Create/hrm/DepartmentFormModal.vue'),
    'department-details': () => import('@/Components/Modals/Show/DepartmentDetailsModal.vue'),
};
