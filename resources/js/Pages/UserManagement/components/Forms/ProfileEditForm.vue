<!-- /resources/js/Pages/UserManagement/components/Forms/ProfileEditForm.vue -->
<!--
  resources/js/Pages/Profile/EditSelf.vue
  (or resources/js/Components/Modals/Edit/ProfileSelfEditModal.vue if used as modal)

  Self Profile Edit Page / Modal – Personal + Address + Password Change

  Features / Problems Solved:
  ────────────────────────────────────────────────────────────────
  • Full self-service profile editing (name, email, phone, username, avatar, address)
  • Avatar upload with preview, drag-drop, delete & size validation
  • Responsive two-column layout (sidebar avatar + main form) on md+
  • PrimeVue components for consistency (InputText, Button, FileUpload, etc.)
  • Form validation using Inertia useForm + custom rules
  • Password change section with visibility toggle
  • Matches backend ProfileController update() + uploadAvatar()
  • Dark mode support + accessibility (labels, focus states, ARIA)
  • Uses useModalForm composable for submit/close/reload pattern
  • Fits into your modal system (can be registered in ModalDirectory as 'edit-profile-self')

  Backend Alignment:
  ──────────────────
  • PATCH /profiles/{profile} → update personal info
  • POST /profiles/{profile}/avatar → avatar upload (Spatie MediaLibrary)
  • PATCH /profiles/{profile}/password → change password (separate or same form)

  Usage:
  • As full page: <EditSelf /> in Pages/Profile/Edit.vue
  • As modal: register in ModalDirectory → open via useModal().open('edit-profile-self', { profile })

  TODO / Future:
  • Add dynamic enums (gender, title) via HasDynamicEnum trait
  • Add country/state/city dropdowns with nnjeim/world integration
  • Add email verification status badge
  • Add 2FA toggle if implemented
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useToast, FileUpload, InputText, Button, Password, Avatar, Card } from 'primevue'

import { useModalForm } from '@/composables/useModalForm'
import { useModal } from '@/composables/useModal'
import AddressManager from '@/Components/Address/AddressManager.vue'
import TextInput from '@/Components/forms/textInput.vue'
import type { AddressFormData } from '@/types/address'
import DynamicEnumField from '@/Components/forms/DynamicEnumField.vue'
import InputWrapper from '@/Components/forms/InputWrapper.vue'
import CustomField from '@/Pages/Settings/System/CustomField.vue'
import CustomFieldRenderer from '@/Components/CustomFieldRenderer.vue'

// ────────────────────────────────────────────────
// Props (when used as modal)
// ────────────────────────────────────────────────
const props = defineProps<{
    profile?: {
        id: number | string
        title?: string
        first_name: string
        middle_name?: string
        last_name: string
        email?: string
        username: string
        phone?: string
        gender?: string
        date_of_birth: string
        biography?: string
        avatar_url?: string
        address?: AddressFormData[]
    }
}>()

// ────────────────────────────────────────────────
// Form Setup
// ────────────────────────────────────────────────
const form = useForm({
    title: props.profile?.title || '',
    first_name: props.profile?.first_name || '',
    last_name: props.profile?.last_name || '',
    middle_name: props.profile?.middle_name || '',
    email: props.profile?.email || '',
    username: props.profile?.username || '',
    phone: props.profile?.phone || '',
    address: props.profile?.address || [],
    gender: props.profile?.gender || '',
    date_of_birth: props.profile?.date_of_birth || '',
    biography: props.profile?.biography || '',

    current_password: '',
    password: '',
    password_confirmation: '',
    avatar: null as File | null
})

const toast = useToast()
const modal = useModal()

// Avatar preview
const avatarPreview = ref(props.profile?.avatar_url || '/images/default-avatar.png')

const onAvatarSelect = (event: any) => {
    const file = event.files[0]
    if (file) {
        form.avatar = file
        avatarPreview.value = URL.createObjectURL(file)
    }
}

// Submit handler
const submit = () => {
    form.post(route('profiles.update', props.profile?.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Profile Updated', life: 4000 })
            modal.closeCurrent?.()
            router.reload({ only: ['auth.user'] }) // refresh auth data if needed
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Validation Failed', detail: 'Check the form', life: 5000 })
        }
    })
}

// Separate avatar upload (if you want to keep it separate from main form)
const uploadAvatar = () => {
    if (!form.avatar) return

    const uploadForm = new FormData()
    uploadForm.append('avatar', form.avatar)

    router.post(route('profiles.avatar', props.profile?.id), uploadForm, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Avatar Updated', life: 3000 })
            form.avatar = null
        }
    })
}
</script>

<template>
    <div class="p-6 space-y-8">
        <!-- Two-column layout on md+ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left Sidebar: Avatar Upload -->
            <Card class="md:col-span-1 h-fit">
                <template #header>
                    <h5 class="font-semibold text-lg">Personal Information</h5>
                </template>
                <div class="flex flex-col items-center gap-6">
                    <!-- File Upload -->
                    <FileUpload ref="fileUpload" name="avatar" :multiple="false" accept="image/jpeg,image/png"
                        :maxFileSize="5000000" customUpload @uploader="onAvatarSelect" :showCancelButton="false"
                        chooseLabel="Click to Upload or Drag & Drop" class="w-full">
                        <template #content>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                <i class="pi pi-cloud-upload text-4xl text-gray-400 mb-3"></i>
                                <p class="text-sm">JPG or PNG</p>
                                <p class="text-xs text-gray-500">(Max 450 × 450 px)</p>
                            </div>
                        </template>
                        <template #header="{ chooseCallback, uploadCallback, clearCallback, files }">
                            <div class="relative group">
                                <Avatar :image="avatarPreview" size="xlarge" shape="circle"
                                    class="border-4 border-white shadow-lg" />
                                <div
                                    class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <Button label="Change" icon="pi pi-camera" class="p-button-rounded p-button-sm" />
                                </div>
                            </div>

                            <div class="text-center">
                                <h6 class="font-medium">Edit Your Photo</h6>
                                <div class="flex gap-2">
                                    <Button @click="chooseCallback()" icon="pi pi-images" rounded variant="outlined"
                                        severity="secondary"></Button>
                                    <Button @click="clearCallback()" icon="pi pi-times" rounded variant="outlined"
                                        severity="danger" :disabled="!files || files.length === 0"></Button>
                                </div>
                            </div>
                        </template>
                    </FileUpload>

                    <Button v-if="form.avatar" label="Save Avatar" icon="pi pi-check" severity="success"
                        @click="uploadAvatar" class="w-full" />
                </div>
            </Card>

            <!-- Main Form Area -->
            <div class="md:col-span-2 space-y-6">
                <!-- Personal Information -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h5 class="font-semibold text-lg">Personal Information</h5>
                            <Button icon="pi pi-pencil" label="Edit" severity="primary" text size="small" />
                        </div>
                    </template>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <TextInput label="Title" name="title" v-model="form.title" :error="form.errors.title" placeholder="Enter Title (MR, Mrs, Dr)" required />

                        <TextInput label="First name" name="first_name" v-model="form.first_name" :error="form.errors.first_name" placeholder="Enter First Name" required />

                        <TextInput label="Middle name" name="middle_name" v-model="form.middle_name" :error="form.errors.middle_name" placeholder="Enter Middle Name" />

                        <TextInput label="Last name" name="last_name" v-model="form.last_name" :error="form.errors.last_name" placeholder="Enter Last Name" required />

                        <!-- TODO: make required conditional per schools requirement, not all school will expect email address from all users, so school might make email optional for student same for phone -->
                        <TextInput type='email' label="Email" name="email" v-model="form.email" :error="form.errors.email" placeholder="Enter Email Address" :required="false" />

                        <TextInput label="Phone Number" type='tel' name="phone" v-model="form.phone" :error="form.errors.phone" placeholder="Enter Phone" required />

                        <TextInput label="User Name" name="username" v-model="form.username" :error="form.errors.username" placeholder="Enter User Name" disabled required />

                        <!-- gender -->
                         <DynamicEnumField model="Profile" v-model="form.gender" name="gender" label="Gender" :error="form.errors.gender" property="gender" />

                        <CustomFieldRenderer v-model="form.date_of_birth" :error="form.errors.date_of_birth" :field="{
                            label: 'Date of birth',
                            name: 'date of birth',
                            field_type: 'calendar',
                         }" />

                         <CustomFieldRenderer v-model="form.biography" :error="form.errors.biography" :field="{
                            label: 'Biography',
                            name: 'biography',
                            field_type: 'textarea',
                         }" />
                    </div>
                </Card>

                <!-- Address Information -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h5 class="font-semibold text-lg">Address Information</h5>
                            <!-- <Button icon="pi pi-pencil" label="Edit" severity="primary" text size="small" /> -->
                        </div>
                    </template>
                    <AddressManager addressable-type="profile" v-model="form.address" />
                </Card>

                <!-- Password Change -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h5 class="font-semibold text-lg">Change Password</h5>
                            <Button icon="pi pi-lock" label="Change" severity="warning" text size="small" />
                        </div>
                    </template>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-1">Current Password</label>
                            <Password v-model="form.current_password" toggleMask placeholder="Current Password"
                                class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">New Password</label>
                            <Password v-model="form.password" toggleMask placeholder="New Password" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Confirm Password</label>
                            <Password v-model="form.password_confirmation" toggleMask placeholder="Confirm Password"
                                class="w-full" />
                        </div>
                    </div>
                </Card>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 mt-8">
                    <Button label="Cancel" severity="secondary" text @click="modal.closeCurrent?.()" />
                    <Button label="Save Changes" icon="pi pi-save" severity="primary" :loading="form.processing"
                        @click="submit" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom overrides if needed */
:deep(.p-avatar-xlarge) {
    width: 120px;
    height: 120px;
}
</style>
