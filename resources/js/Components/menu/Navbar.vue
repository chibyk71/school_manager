<script lang="ts" setup>
import { usePopup } from '@/helpers';
import { isDarkTheme, sidebarCollapsed } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, Button, IconField, Image, InputIcon, InputText, Menu, Menubar, OverlayBadge, Select } from 'primevue';
import { ref } from 'vue';

const searchValue = ref("");
const selectedAcademicSession = ref(2025); // TODO: Fetch dynamic options from server
const selectedLanguage = ref({ name: 'English', value: 'us' }); // TODO: Make selection persist and affect app

const toggleDarkMode = () => {
    isDarkTheme.value = !!document.querySelector('html')?.classList.toggle('dark');
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    document.body.classList.toggle('mini-sidebar', sidebarCollapsed.value);
    document.body.classList.toggle('expand-menu', !sidebarCollapsed.value);
};

// Mock data for menus – TODO: Replace with dynamic data from API or props
const addNewItems = ref([
    { name: 'Add Student', value: 'student', route: 'add-student' },
    { name: 'Add Teacher', value: 'teacher', route: 'add-teacher' },
    { name: 'Add Staff', value: 'staff', route: 'add-staff' },
    { name: 'Add Invoice', value: 'invoice', route: 'add-invoice' },
]);

const languageItems = ref([
    { name: 'English', value: 'us' },
    { name: 'French', value: 'fr' },
    { name: 'Spanish', value: 'es' },
    { name: 'German', value: 'de' },
]);

const notificationItems = ref([
    // Mock notifications – TODO: Fetch from API
    { id: 1, avatar: 'assets/img/profiles/avatar-27.jpg', message: '<span class="font-semibold text-dark">Shawn</span> performance in Math is below the threshold.', time: 'Just Now' },
    { id: 2, avatar: 'assets/img/profiles/avatar-23.jpg', message: '<span class="font-semibold text-dark">Sylvia</span> added appointment on 02:00 PM', time: '10 mins ago', actions: true },
    { id: 3, avatar: 'assets/img/profiles/avatar-25.jpg', message: 'New student record <span class="font-semibold text-dark">George</span> is created by <span class="font-semibold text-dark">Teressa</span>', time: '2 hrs ago' },
    { id: 4, avatar: 'assets/img/profiles/avatar-01.jpg', message: 'A new teacher record for <span class="font-semibold text-dark">Elisa</span>', time: '09:45 AM' },
]);

const profileItems = ref([
    { name: 'My Profile', value: 'profile', icon: 'pi pi-user', route: 'profile' },
    { name: 'Settings', value: 'settings', icon: 'pi pi-cog', route: 'profile-settings' },
    { name: 'Logout', value: 'logout', icon: 'pi pi-sign-out', route: 'logout' },
]);

// Unread notifications count – TODO: Dynamic
const unreadNotifications = ref(2);
const unreadMessages = ref(3);

// Handle fullscreen toggle
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else if (document.exitFullscreen) {
        document.exitFullscreen();
    }
};

// That's it! One line per dropdown, fully typed, zero unknown errors
const { toggle: openAdd }         = usePopup('add')
const { toggle: openLang }        = usePopup('language')
const { toggle: openNotif }       = usePopup('notification')
const { toggle: openProfile }     = usePopup('profile')
const { toggle: openMobileMenu }  = usePopup('mobileMenu')
</script>

<template>
    <Menubar class="app-header !border-b fixed w-full top-0 right-0 left-0 overflow-hidden z-70 bg-white dark:bg-dark-bg-primary">
        <!-- Brand Logo & Sidebar Toggle -->
        <template #start>
            <div class="flex items-center">
                <!-- Mobile Sidebar Toggle -->
                <Button @click="toggleSidebar" icon="pi pi-bars" class="p-button-text p-button-plain mr-2" />

                <!-- Logo -->
                <Link :href="route('dashboard')" class="logo flex items-center">
                    <Image class="brand-img h-8 w-auto mr-2" src="assets/img/logo-small.svg" alt="Logo" />
                    <span class="brand-text text-xl font-bold text-dark dark:text-dark-text-primary">HUD ADMIN
                        <span class="text-xs font-bold opacity-50 absolute top-1.5 -ml-4">TM</span>
                    </span>
                </Link>
            </div>
        </template>

        <!-- Search Input (Responsive: Hidden on mobile, shown on lg+) -->
        <template #item>
            <IconField class="hidden lg:flex ml-4 w-64">
                <InputIcon class="pi pi-search text-gray-500" />
                <InputText class="!bg-transparent border-none focus:shadow-none" v-model="searchValue" placeholder="Search" />
            </IconField>
        </template>

        <!-- Right Side Items -->
        <template #end>
            <div class="flex items-center gap-x-2">
                <!-- Academic Session Select -->
                <Select v-model="selectedAcademicSession" :options="[2021, 2022, 2023, 2024, 2025]" placeholder="Select Academic Session" class="bg-transparent border border-gray-300 dark:border-gray-700 rounded-md">
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center">
                            <i class="pi pi-calendar-clock mr-2 text-gray-500"></i>
                            Academic Year: {{ slotProps.value }}/{{ slotProps.value + 1 }}
                        </div>
                        <span v-else>{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        {{ slotProps.option }}/{{ slotProps.option + 1 }}
                    </template>
                </Select>

                <!-- Language Dropdown -->
                <div class="flex items-center">
                    <Button @click="(e) => openLang" variant="outlined" class="p-button-rounded p-button-plain">
                        <Image class="w-6 h-6 rounded-full" :src="`assets/img/flags/${selectedLanguage.value}.png`" alt="Language" />
                    </Button>
                    <Menu ref="language" :popup="true" :model="languageItems">
                        <template #item="{ item }">
                            <div class="flex items-center p-2">
                                <Image class="w-6 h-6 mr-2 rounded-full" :src="`assets/img/flags/${item.value}.png`" alt="Flag" />
                                <span class="font-semibold">{{ item.name }}</span>
                            </div>
                        </template>
                    </Menu>
                </div>

                <!-- Add New Dropdown -->
                <div class="flex items-center">
                    <Button icon="pi pi-plus" @click="openAdd" variant="outlined" severity="secondary" class="p-button-rounded" />
                    <Menu ref="add" :popup="true" :model="addNewItems" :pt="{ list: { class: 'grid grid-cols-2 gap-4 p-3' } }">
                        <template #start>
                            <div class="p-3 border-b">
                                <h5 class="text-lg font-semibold">Add New</h5>
                            </div>
                        </template>
                        <template #item="{ item }">
                            <!-- TODO replace with this :href="route(item.route)" -->
                            <Link href="" class="block bg-primary-transparent rounded p-3 text-center hover:bg-primary/20 transition-colors">
                                <div class="avatar avatar-lg mb-2">
                                    <span class="inline-flex items-center justify-center w-12 h-12 bg-primary rounded-full"><i class="pi pi-plus"></i></span>
                                </div>
                                <p class="text-dark dark:text-dark-text-primary font-medium">{{ item.name }}</p>
                            </Link>
                        </template>
                    </Menu>
                </div>

                <!-- Dark Mode Toggle -->
                <Button :icon="`pi pi-${isDarkTheme ? 'sun' : 'moon'}`" @click="toggleDarkMode" variant="outlined" severity="secondary" class="p-button-rounded" />

                <!-- Notifications -->
                <div class="flex items-center">
                    <OverlayBadge :value="unreadNotifications" severity="danger">
                        <Button icon="pi pi-bell" @click="openNotif" variant="outlined" severity="secondary" class="p-button-rounded" />
                    </OverlayBadge>
                    <Menu ref="notification" :popup="true" :model="notificationItems" :pt="{ list: { class: 'h-72 overflow-y-auto p-4' } }">
                        <template #start>
                            <div class="flex items-center justify-between border-b pb-3 mb-3">
                                <h4 class="text-lg font-semibold">Notifications ({{ unreadNotifications }})</h4>
                                <Button variant="text" severity="primary" class="text-sm">Mark all as read</Button>
                            </div>
                        </template>
                        <template #item="{ item }">
                            <div class="border-b mb-3 pb-3">
                                <Link :href="route('activities')">
                                    <div class="flex items-start">
                                        <Avatar :image="item.avatar" size="large" shape="circle" class="mr-2" />
                                        <div class="flex-grow">
                                            <p class="mb-1 text-sm" v-html="item.message"></p>
                                            <span class="text-xs text-gray-500">{{ item.time }}</span>
                                            <div v-if="item.actions" class="mt-2 flex gap-2">
                                                <Button severity="secondary" label="Deny" size="small" />
                                                <Button severity="primary" label="Approve" size="small" />
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </template>
                        <template #end>
                            <div class="flex gap-2 pt-3 border-t">
                                <Button severity="danger" label="Cancel" class="w-1/2" />
                                <Button severity="primary" label="View All" class="w-1/2" />
                            </div>
                        </template>
                    </Menu>
                </div>

                <!-- Messages -->
                <OverlayBadge :value="unreadMessages" severity="success">
                    <Button :as="Link" href="/chat" icon="pi pi-envelope" variant="outlined" severity="secondary" class="p-button-rounded" />
                </OverlayBadge>

                <!-- Fullscreen -->
                <Button icon="pi pi-expand" @click="toggleFullscreen" variant="outlined" severity="secondary" class="p-button-rounded" />

                <!-- Profile Dropdown -->
                <div class="flex items-center">
                    <Avatar @click="openProfile" image="assets/img/profiles/avatar-27.jpg" shape="circle" size="large" />
                    <Menu ref="profile" :popup="true" :model="profileItems">
                        <template #start>
                            <div class="flex items-center p-3 border-b">
                                <Avatar image="assets/img/profiles/avatar-27.jpg" size="large" shape="circle" class="mr-2" />
                                <div>
                                    <h6 class="font-semibold">Kevin Larry</h6>
                                    <p class="text-primary text-sm mb-0">Administrator</p>
                                </div>
                            </div>
                        </template>
                        <template #item="{ item }">
                            <Button :as="Link" :href="route(item.route)" :icon="item.icon" :label="item.name" variant="text" severity="secondary" class="w-full justify-start" />
                        </template>
                    </Menu>
                </div>

                <!-- Mobile User Menu (Ellipsis for small screens) -->
                <div class="lg:hidden">
                    <Button icon="pi pi-ellipsis-v" @click="openMobileMenu" variant="text" class="p-button-plain" />
                    <Menu ref="mobileMenu" :popup="true" :model="profileItems" />
                </div>
            </div>
        </template>
    </Menubar>
</template>

<style scoped lang="postcss">
/* Custom styles if needed, but prefer Tailwind classes */
.app-header {
    @apply shadow-md;
}

/* Responsive adjustments */
@media (max-width: 767px) {
    .app-header {
        @apply flex-wrap justify-between;
    }
}
</style>