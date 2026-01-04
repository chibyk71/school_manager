<!-- resources/js/Pages/Profile/Edit.vue
<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

// Props from controller
const props = defineProps({
    profile: Object,
    is_admin_override: Boolean,
    can: Object,
})

// Form state
const form = useForm({
    first_name: props.profile.first_name,
    last_name: props.profile.last_name,
    title: props.profile.title || null,
    gender: props.profile.gender || null,
    phone: props.profile.phone || '',
    email: props.profile.email,
    photo: null,
})

// Avatar preview
const avatarUrl = ref(props.profile.photo_url)

// Watch for file input changes
const handleAvatarChange = (e) => {
    const file = e.target.files[0]
    if (!file) return

    // Validate size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image must be smaller than 5MB')
        return
    }

    form.photo = file

    // Show preview instantly
    const reader = new FileReader()
    reader.onload = (e) => {
        avatarUrl.value = e.target.result
    }
    reader.readAsDataURL(file)

    // Upload immediately
    uploadAvatar(file)
}

// Drag & drop
const isDragging = ref(false)
const dragOver = (e) => {
    e.preventDefault()
    isDragging.value = true
}
const dragLeave = () => {
    isDragging.value = false
}
const drop = (e) => {
    e.preventDefault()
    isDragging.value = false
    const file = e.dataTransfer.files[0]
    if (file && file.type.startsWith('image/')) {
        document.getElementById('photo-input').files = e.dataTransfer.files
        handleAvatarChange({ target: { files: [file] } })
    }
}

// Upload avatar via API
const uploadAvatar = (file) => {
    const uploadForm = new FormData()
    uploadForm.append('photo', file)
    if (props.is_admin_override) {
        uploadForm.append('profile_id', props.profile.id)
    }

    router.post(route('profile.avatar.upload'), uploadForm, {
        forceFormData: true,
        onSuccess: (page) => {
            // Update avatar URL from response
            avatarUrl.value = page.props.flash?.photo_url || page.props.profile?.photo_url || avatarUrl.value
            showToast('Avatar updated!')
        },
        onError: () => {
            showToast('Failed to upload avatar', 'error')
        }
    })
}

// Submit profile update
const submit = () => {
    const url = props.is_admin_override
        ? route('profile.update', { profile: props.profile.id })
        : route('profile.update')

    form.put(url, {
        onSuccess: () => {
            showToast('Profile updated successfully!')
        },
        onError: (errors) => {
            showToast('Please fix the errors below', 'error')
        }
    })
}

// 2FA Toggle
const toggle2FA = () => {
    if (props.profile.two_factor_enabled) {
        // Disable 2FA
        if (confirm('Are you sure you want to disable two-factor authentication?')) {
            router.post(route('2fa.disable'), {}, {
                onSuccess: () => {
                    props.profile.two_factor_enabled = false
                    showToast('2FA disabled')
                }
            })
        }
    } else {
        // Enable 2FA
        router.visit(route('2fa.enable'))
    }
}

// Toast helper (you can replace with your preferred toast library)
const showToast = (message, type = 'success') => {
    // Simple browser alert fallback – replace with Toast/Sonner/Nuxt UI etc.
    alert(`[${type.toUpperCase()}] ${message}`)
}
</script>

<template>
    <AuthenticatedLayout title="Edit Profile">
        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">
                {{ is_admin_override ? `Editing: ${profile.first_name} ${profile.last_name}` : 'My Profile' }}
            </h1>

            <div class="bg-white shadow-xl rounded-lg overflow-hidden">
                <div class="p-8">

                    <!-- Avatar Section --
                    <div class="mb-10 text-center">
                        <div class="relative inline-block" @dragover.prevent="dragOver" @dragleave="dragLeave"
                            @drop="drop" :class="{ 'ring-4 ring-blue-400 ring-opacity-50': isDragging }">
                            <img :src="avatarUrl" alt="Profile photo"
                                class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg" />
                            <div
                                class="absolute inset-0 rounded-full bg-black bg-opacity-40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block">
                                <span class="sr-only">Choose profile photo</span>
                                <input id="photo-input" type="file" accept="image/jpeg,image/png,image/webp"
                                    @change="handleAvatarChange"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer" />
                            </label>
                            <p class="text-xs text-gray-500 mt-2">JPG, PNG, WebP up to 5MB</p>
                        </div>
                    </div>

                    <!-- Form --
                    <form @submit.prevent="submit" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="title" value="Title" />
                                <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full"
                                    placeholder="Mr, Mrs, Dr..." />
                                <InputError :message="form.errors.title" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="first_name" value="First Name" required />
                                <TextInput id="first_name" v-model="form.first_name" type="text"
                                    class="mt-1 block w-full" required autofocus />
                                <InputError :message="form.errors.first_name" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="last_name" value="Last Name" required />
                                <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full"
                                    required />
                                <InputError :message="form.errors.last_name" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Email" required />
                                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full"
                                    required />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="phone" value="Phone" />
                                <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full"
                                    placeholder="+234 801 234 5678" />
                                <InputError :message="form.errors.phone" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="gender" value="Gender" />
                                <select v-model="form.gender"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Prefer not to say</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2FA Status --
                        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Two-Factor Authentication</h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ profile.two_factor_enabled ? 'Enabled – Your account is protected' : 'Not enabled' }}
                                    </p>
                                </div>
                                <PrimaryButton @click="toggle2FA"
                                    :class="{ 'bg-green-600 hover:bg-green-700': profile.two_factor_enabled }"
                                    type="button">
                                    {{ profile.two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA' }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- Submit --
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </PrimaryButton>
                            <SecondaryButton @click="$inertia.visit(route('dashboard'))" type="button">
                                Cancel
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> -->
