<script lang="ts" setup>
import { usePopup } from '@/helpers';
import { isDarkTheme, mobileSidebarOpen, sidebarCollapsed } from '@/store';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Avatar,
    Button,
    IconField,
    Image,
    InputIcon,
    InputText,
    Menu,
    OverlayBadge,
    Select,
} from 'primevue';
import { computed, ref } from 'vue';

const page = usePage();
const searchValue = ref('');
const selectedAcademicSession = ref(2025);
const selectedLanguage = ref({ name: 'English', value: 'us' });

const toggleDarkMode = () => {
    isDarkTheme.value = !!document.querySelector('html')?.classList.toggle('dark');
};

const toggleSidebar = () => {
    if (window.innerWidth < 992) {
        mobileSidebarOpen.value = !mobileSidebarOpen.value;
        return;
    }
    sidebarCollapsed.value = !sidebarCollapsed.value;
    document.body.classList.toggle('mini-sidebar', sidebarCollapsed.value);
};

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
    {
        id: 1,
        avatar: 'assets/img/profiles/avatar-27.jpg',
        message: '<span class="font-semibold text-dark">Shawn</span> performance in Math is below the threshold.',
        time: 'Just Now',
    },
    {
        id: 2,
        avatar: 'assets/img/profiles/avatar-23.jpg',
        message: '<span class="font-semibold text-dark">Sylvia</span> added appointment on 02:00 PM',
        time: '10 mins ago',
        actions: true,
    },
    {
        id: 3,
        avatar: 'assets/img/profiles/avatar-25.jpg',
        message:
            'New student record <span class="font-semibold text-dark">George</span> is created by <span class="font-semibold text-dark">Teressa</span>',
        time: '2 hrs ago',
    },
]);

const profileItems = ref([
    { name: 'My Profile', value: 'profile', icon: 'pi pi-user', route: 'profile' },
    { name: 'Settings', value: 'settings', icon: 'pi pi-cog', route: 'profile' },
    { name: 'Logout', value: 'logout', icon: 'pi pi-sign-out', route: 'logout' },
]);

const unreadNotifications = ref(2);
const unreadMessages = ref(3);

const user = computed(
    () => (page.props as { auth?: { user?: { name?: string; email?: string } } }).auth?.user,
);

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else if (document.exitFullscreen) {
        document.exitFullscreen();
    }
};

const { toggle: openAdd } = usePopup('add');
const { toggle: openLang } = usePopup('language');
const { toggle: openNotif } = usePopup('notification');
const { toggle: openProfile } = usePopup('profile');
</script>

<template>
    <header
        class="app-header sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-3 dark:border-gray-700 dark:bg-dark-bg-primary lg:px-5"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <Button
                type="button"
                icon="pi pi-bars"
                text
                rounded
                severity="secondary"
                class="!h-9 !w-9"
                aria-label="Toggle sidebar"
                @click="toggleSidebar"
            />

            <IconField class="hidden max-w-md flex-1 sm:flex">
                <InputIcon class="pi pi-search text-gray-400" />
                <InputText
                    v-model="searchValue"
                    placeholder="Search students, staff, invoices..."
                    class="w-full !border-gray-200 !bg-gray-50 dark:!border-gray-600 dark:!bg-dark-bg-secondary"
                />
            </IconField>
        </div>

        <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
            <Select
                v-model="selectedAcademicSession"
                :options="[2023, 2024, 2025, 2026]"
                class="hidden !h-9 max-w-[11rem] border border-gray-200 dark:border-gray-600 md:flex"
            >
                <template #value="slotProps">
                    <div v-if="slotProps.value" class="flex items-center text-sm">
                        <i class="pi pi-calendar mr-1.5 text-gray-400" />
                        <span class="truncate">AY {{ slotProps.value }}/{{ slotProps.value + 1 }}</span>
                    </div>
                    <span v-else>{{ slotProps.placeholder }}</span>
                </template>
                <template #option="slotProps">
                    {{ slotProps.option }}/{{ slotProps.option + 1 }}
                </template>
            </Select>

            <Button
                type="button"
                text
                rounded
                severity="secondary"
                class="!h-9 !w-9 hidden sm:inline-flex"
                @click="openLang"
            >
                <Image
                    class="h-5 w-5 rounded-full"
                    :src="`assets/img/flags/${selectedLanguage.value}.png`"
                    alt="Language"
                />
            </Button>
            <Menu ref="language" :popup="true" :model="languageItems">
                <template #item="{ item }">
                    <div class="flex items-center gap-2 p-2">
                        <Image
                            class="h-5 w-5 rounded-full"
                            :src="`assets/img/flags/${item.value}.png`"
                            alt="Flag"
                        />
                        <span class="text-sm font-medium">{{ item.name }}</span>
                    </div>
                </template>
            </Menu>

            <Button
                type="button"
                icon="pi pi-plus"
                rounded
                severity="secondary"
                outlined
                class="!h-9 !w-9"
                aria-label="Add new"
                @click="openAdd"
            />
            <Menu ref="add" :popup="true" :model="addNewItems">
                <template #start>
                    <div class="border-b p-3">
                        <h5 class="text-sm font-semibold">Add New</h5>
                    </div>
                </template>
                <template #item="{ item }">
                    <Link href="#" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                        <i class="pi pi-plus-circle text-primary" />
                        {{ item.name }}
                    </Link>
                </template>
            </Menu>

            <Button
                type="button"
                :icon="`pi pi-${isDarkTheme ? 'sun' : 'moon'}`"
                rounded
                severity="secondary"
                outlined
                class="!h-9 !w-9"
                aria-label="Toggle theme"
                @click="toggleDarkMode"
            />

            <OverlayBadge :value="unreadNotifications" severity="danger">
                <Button
                    type="button"
                    icon="pi pi-bell"
                    rounded
                    severity="secondary"
                    outlined
                    class="!h-9 !w-9"
                    aria-label="Notifications"
                    @click="openNotif"
                />
            </OverlayBadge>
            <Menu ref="notification" :popup="true" :model="notificationItems" class="w-80">
                <template #start>
                    <div class="flex items-center justify-between border-b px-3 py-2">
                        <h4 class="text-sm font-semibold">Notifications</h4>
                        <Button label="Mark all read" text size="small" />
                    </div>
                </template>
                <template #item="{ item }">
                    <div class="border-b px-3 py-2 last:border-0">
                        <div class="flex gap-2">
                            <Avatar :image="item.avatar" shape="circle" class="!h-9 !w-9" />
                            <div class="min-w-0 flex-1">
                                <p class="mb-0.5 text-sm" v-html="item.message" />
                                <span class="text-xs text-gray-500">{{ item.time }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </Menu>

            <OverlayBadge :value="unreadMessages" severity="success" class="hidden sm:inline-flex">
                <Button
                    :as="Link"
                    href="/chat"
                    icon="pi pi-envelope"
                    rounded
                    severity="secondary"
                    outlined
                    class="!h-9 !w-9"
                    aria-label="Messages"
                />
            </OverlayBadge>

            <Button
                type="button"
                icon="pi pi-expand"
                rounded
                severity="secondary"
                outlined
                class="!h-9 !w-9 hidden md:inline-flex"
                aria-label="Fullscreen"
                @click="toggleFullscreen"
            />

            <button
                type="button"
                class="ml-0.5 flex items-center gap-2 rounded-full p-0.5 transition hover:bg-gray-100 dark:hover:bg-gray-800"
                @click="openProfile"
            >
                <Avatar
                    image="assets/img/profiles/avatar-27.jpg"
                    shape="circle"
                    class="!h-9 !w-9"
                />
            </button>
            <Menu ref="profile" :popup="true" :model="profileItems">
                <template #start>
                    <div class="flex items-center gap-2 border-b p-3">
                        <Avatar
                            image="assets/img/profiles/avatar-27.jpg"
                            shape="circle"
                            class="!h-10 !w-10"
                        />
                        <div class="min-w-0">
                            <h6 class="truncate text-sm font-semibold">
                                {{ user?.name || 'User' }}
                            </h6>
                            <p class="truncate text-xs text-primary">
                                {{ user?.email || 'Account' }}
                            </p>
                        </div>
                    </div>
                </template>
                <template #item="{ item }">
                    <Button
                        :as="Link"
                        :href="route(item.route)"
                        :icon="item.icon"
                        :label="item.name"
                        text
                        severity="secondary"
                        class="w-full !justify-start"
                    />
                </template>
            </Menu>
        </div>
    </header>
</template>
