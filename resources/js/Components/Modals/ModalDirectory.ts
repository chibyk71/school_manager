import { ref } from "vue";

export const ModalComponentDirectory = ref<Record<any, any>>({
    'custom-field': () => import('./Create/CustomFields.vue'),
    'assign-teacher-subject': () => import('./Create/AssignTeacherSubjectClass.vue'),
    'add-staff': () => import('./Create/AddStaff.vue'),
    'department': () => import('./Create/Department.vue'),
    'department-role': () => import('./Create/AssignDepartmentRole.vue'),
});
