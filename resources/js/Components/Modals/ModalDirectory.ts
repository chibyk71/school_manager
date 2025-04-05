import { reactive, type DefineComponent, ref } from "vue";
import CustomFields from "./CustomFields.vue";
import AssignTeacherSubjectClass from "./Create/AssignTeacherSubjectClass.vue";

export  const ModalComponentDirectory = ref<Record<any, any>>({
    'custom-field': CustomFields,
    'assign-teacher-subject': AssignTeacherSubjectClass
})
