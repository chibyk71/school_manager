<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import { Avatar } from 'primevue';
import type { MenuItem } from '@/types';
import { menuItems } from '@/store';
import {
    isLayoutMobileViewport,
    mobileSidebarOpen,
    SIDEBAR_WIDTH_COLLAPSED,
    SIDEBAR_WIDTH_EXPANDED,
    sidebarCollapsed,
} from '@/store/layout';

const page = usePage();
const isMobile = ref(false);

const updateIsMobile = () => {
    isMobile.value = isLayoutMobileViewport();
};

onMounted(() => {
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateIsMobile);
});

const openSubmenus = ref<Set<string>>(new Set());

const isCollapsed = computed(() => !isMobile.value && sidebarCollapsed.value);

const sidebarWidth = computed(() =>
    isCollapsed.value ? SIDEBAR_WIDTH_COLLAPSED : SIDEBAR_WIDTH_EXPANDED,
);

const toggleSubmenu = (item: MenuItem) => {
    const key = item.title;
    if (openSubmenus.value.has(key)) {
        openSubmenus.value.delete(key);
    } else {
        openSubmenus.value.add(key);
    }
};

const isSubmenuOpen = (item: MenuItem): boolean => openSubmenus.value.has(item.title);

const isActiveLink = (link?: string): boolean => {
    if (!link || link.endsWith('.html')) return false;
    const current = page.url.split('?')[0];
    if (link === '/' || link === '/dashboard') {
        return current === '/' || current === '/dashboard';
    }
    return current === link || current.startsWith(link + '/');
};

const hasActiveChild = (item: MenuItem): boolean =>
    !!item.submenu?.some((sub) => isActiveLink(sub.link));

watch(
    () => page.url,
    () => {
        for (const section of menuItems.value) {
            for (const item of section.items) {
                if (item.submenu && hasActiveChild(item)) {
                    openSubmenus.value.add(item.title);
                }
            }
        }
    },
    { immediate: true },
);

const user = computed(
    () => (page.props as { auth?: { user?: { name?: string; email?: string } } }).auth?.user,
);
const schoolName = computed(() => {
    const props = page.props as {
        school?: { name?: string };
        auth?: { school?: { name?: string } };
    };
    return props.school?.name || props.auth?.school?.name || 'School Manager';
});

const profileHref = computed(() => {
    try {
        return route('profile');
    } catch {
        try {
            return route('profile.edit');
        } catch {
            return '/profile';
        }
    }
});
</script>

<template>
    <aside
        class="sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r border-gray-200 bg-white transition-all duration-300 dark:border-gray-700 dark:bg-dark-bg-secondary"
        :class="[
            mobileSidebarOpen ? 'translate-x-0' : 'max-[991px]:-translate-x-full',
            isCollapsed ? 'items-center' : '',
        ]"
        :style="{ width: `${sidebarWidth}px` }"
    >
        <div
            class="flex h-14 shrink-0 items-center gap-2 border-b border-gray-100 px-3 dark:border-gray-700"
            :class="isCollapsed ? 'justify-center' : 'justify-start'"
        >
            <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white"
                >
                    SM
                </span>
                <span
                    v-show="!isCollapsed"
                    class="truncate text-lg font-bold tracking-tight text-dark dark:text-dark-text-primary"
                >
                    School Manager
                </span>
            </Link>
        </div>

        <div v-show="!isCollapsed" class="shrink-0 px-3 pt-3">
            <div
                class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 dark:border-gray-600 dark:bg-dark-bg-primary"
            >
                <Avatar
                    image="assets/img/icons/global-img.svg"
                    shape="circle"
                    class="!h-8 !w-8 shrink-0"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-dark dark:text-dark-text-primary">
                        {{ schoolName }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-dark-text-muted">
                        School Management
                    </p>
                </div>
            </div>
        </div>

        <PerfectScrollbar class="min-h-0 flex-1">
            <nav class="space-y-5 px-2 py-4">
                <template v-for="section in menuItems" :key="section.header">
                    <div>
                        <h6
                            v-show="!isCollapsed"
                            class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                        >
                            {{ section.header }}
                        </h6>

                        <ul class="space-y-0.5">
                            <li v-for="item in section.items" :key="item.title">
                                <div v-if="item.submenu">
                                    <button
                                        type="button"
                                        class="group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors"
                                        :class="[
                                            isSubmenuOpen(item) || hasActiveChild(item)
                                                ? 'bg-primary/10 text-primary dark:bg-primary/20'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                            isCollapsed ? 'justify-center' : 'justify-between',
                                        ]"
                                        :title="isCollapsed ? item.title : undefined"
                                        @click="toggleSubmenu(item)"
                                    >
                                        <span class="flex min-w-0 items-center gap-2.5">
                                            <i
                                                v-if="item.icon"
                                                :class="item.icon"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-soft-secondary text-base text-gray-500 group-hover:text-primary"
                                            />
                                            <span v-show="!isCollapsed" class="truncate">{{ item.title }}</span>
                                        </span>
                                        <i
                                            v-show="!isCollapsed"
                                            class="ti text-base transition-transform"
                                            :class="isSubmenuOpen(item) ? 'ti-minus' : 'ti-plus'"
                                        />
                                    </button>

                                    <Transition name="slide">
                                        <ul
                                            v-if="isSubmenuOpen(item) && !isCollapsed"
                                            class="ml-5 mt-0.5 space-y-0.5 border-l border-gray-200 py-1 pl-4 dark:border-gray-700"
                                        >
                                            <li v-for="sub in item.submenu" :key="sub.title">
                                                <Link
                                                    :href="sub.link || '#'"
                                                    class="block rounded-md px-2.5 py-1.5 text-sm transition-colors"
                                                    :class="
                                                        isActiveLink(sub.link)
                                                            ? 'bg-primary/10 font-medium text-primary'
                                                            : 'text-gray-600 hover:bg-gray-100 hover:text-primary dark:text-gray-400 dark:hover:bg-gray-800'
                                                    "
                                                >
                                                    {{ sub.title }}
                                                </Link>
                                            </li>
                                        </ul>
                                    </Transition>
                                </div>

                                <Link
                                    v-else
                                    :href="item.link || '#'"
                                    class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors"
                                    :class="[
                                        isActiveLink(item.link)
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                        isCollapsed ? 'justify-center' : '',
                                    ]"
                                    :title="isCollapsed ? item.title : undefined"
                                >
                                    <i
                                        v-if="item.icon"
                                        :class="item.icon"
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-soft-secondary text-base text-gray-500 group-hover:text-primary"
                                    />
                                    <span v-show="!isCollapsed" class="truncate">{{ item.title }}</span>
                                    <span
                                        v-if="item.badge && !isCollapsed"
                                        class="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>
            </nav>
        </PerfectScrollbar>

        <div
            class="shrink-0 border-t border-gray-100 p-3 dark:border-gray-700"
            :class="isCollapsed ? 'flex justify-center' : ''"
        >
            <Link
                :href="profileHref"
                class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-2 py-2 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800"
                :class="isCollapsed ? 'justify-center border-0 p-1' : ''"
            >
                <Avatar
                    image="assets/img/profiles/avatar-27.jpg"
                    shape="circle"
                    class="!h-9 !w-9 shrink-0"
                />
                <div v-show="!isCollapsed" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-dark dark:text-dark-text-primary">
                        {{ user?.name || 'User' }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-dark-text-muted">
                        {{ user?.email || 'Account' }}
                    </p>
                </div>
            </Link>
        </div>
    </aside>
</template>

<style scoped lang="postcss">
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
