import { reactive, type DefineComponent, ref } from "vue";
import CustomFields from "./Create/CustomFields.vue";
import AssignTeacherSubjectClass from "./Create/AssignTeacherSubjectClass.vue";
import AddStaff from "./Create/AddStaff.vue";

export  const ModalComponentDirectory = ref<Record<any, any>>({
    'custom-field': CustomFields,
    'assign-teacher-subject': AssignTeacherSubjectClass,
    'add-staff': AddStaff,
})
