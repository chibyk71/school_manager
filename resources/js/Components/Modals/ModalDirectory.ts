// src/components/Modals/ModalDirectory.ts
import { Component } from 'vue';

type AsyncComponentLoader = () => Promise<Component>;

export const ModalComponentDirectory: Record<string, AsyncComponentLoader> = {
  'custom-field': () => import('./Create/CustomFields.vue'),
  'assign-teacher-subject': () => import('./Create/AssignTeacherSubjectClass.vue'),
  'add-staff': () => import('./Create/AddStaff.vue'),
  'department': () => import('./Create/Department.vue'),
  'department-role': () => import('./Create/AssignDepartmentRole.vue'),
};