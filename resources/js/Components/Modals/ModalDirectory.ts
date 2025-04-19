import { reactive, type DefineComponent, ref } from "vue";
import CustomFields from "./Create/CustomFields.vue";
import AssignTeacherSubjectClass from "./Create/AssignTeacherSubjectClass.vue";
import AddStaff from "./Create/AddStaff.vue";

export const ModalComponentDirectory = ref<Record<any, any>>({
    'custom-field': () => import('./Create/CustomFields.vue'),
    'assign-teacher-subject': () => import('./Create/AssignTeacherSubjectClass.vue'),
    'add-staff': () => import('./Create/AddStaff.vue'),
    'department': () => import('./Create/Department.vue'),
});
