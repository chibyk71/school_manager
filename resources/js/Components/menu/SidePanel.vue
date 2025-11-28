<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

import type { SidebarMenu, MenuItem } from '@/types' // adjust path as needed
import { menuItems, sidebarCollapsed } from '@/store'

// Track open submenu keys (using title as key – stable and unique enough)
const openSubmenus = ref<Set<string>>(new Set())

const toggleSubmenu = (item: MenuItem) => {
    const key = item.title
    if (openSubmenus.value.has(key)) {
        openSubmenus.value.delete(key)
    } else {
        openSubmenus.value.add(key)
    }
}

const isSubmenuOpen = (item: MenuItem): boolean => {
    return openSubmenus.value.has(item.title)
}

// Optional: close all on mobile route change
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { Avatar } from 'primevue'
const page = usePage()

watch(
    () => page.props.url,
    () => {
        if (window.innerWidth < 1024) {
            openSubmenus.value.clear()
        }
    }
)
</script>

<template>
    <aside
        class="sidebar fixed inset-y-0 left-0 z-50 w-72 border-r bg-white pt-16 transition-all duration-300 dark:bg-[var(--dark-bg-secondary)] dark:border-gray-700"
        :class="{ '!w-20': sidebarCollapsed }">
        <PerfectScrollbar class="h-full">
            <div class="flex h-full flex-col">
                <!-- Brand / Logo -->
                <div class="w-full flex items-center justify-center mt-5">
                    <Link href="/" class="flex items-center gap-3 border border-gray-200 hover:border-gray-300 dark:hover:border-gray-600 p-2 rounded">
                        <Avatar image="assets/img/icons/global-img.svg" size="large" class="h-10 w-10 flex-shrink-0" >
                        </Avatar>
                        <span class="text-lg font-semibold text-gray-900 transition-all dark:text-white inline-block"
                            :class="{ 'opacity-0 !hidden': sidebarCollapsed }">
                            Global International
                        </span>
                    </Link>
                </div>

                <!-- Scrollable Menu -->
                <nav class="flex-1 space-y-6 px-3 py-6">
                    <template v-for="section in menuItems" :key="section.header">
                        <!-- Section Header -->
                        <h6 class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 transition-all" :class="{ 'hidden opacity-0 pointer-events-none': sidebarCollapsed }">
                            {{ section.header }}
                        </h6>

                        <!-- Menu Items -->
                        <ul class="space-y-1">
                            <li v-for="item in section.items" :key="item.title">
                                <!-- Has Submenu -->
                                <div v-if="item.submenu">
                                    <button @click="toggleSubmenu(item)"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium transition-all" :class="[isSubmenuOpen(item)? 'bg-soft-primary !text-primary dark:bg-primary/20 [&_i.icon]:bg-surface-50 [&_i]:text-primary' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800']">
                                        <div class="flex items-center gap-3">
                                            <i v-if="item.icon" :class="item.icon"
                                                class="icon size-8 text-lg text-gray-500 transition-colors group-hover:text-primary flex items-center justify-center rounded bg-soft-secondary" />
                                            <span :class="{ 'opacity-0 hidden': sidebarCollapsed }" class="transition-all">
                                                {{ item.title }}
                                            </span>
                                        </div>
                                        <i class="ti ti-chevron-right text-base transition-transform font-extrabold" :class="{ 'rotate-90': isSubmenuOpen(item), 'opacity-0 hidden': sidebarCollapsed }" />
                                    </button>

                                    <!-- Submenu -->
                                    <Transition name="slide">
                                        <ul v-if="isSubmenuOpen(item)" class="mt-1 space-y-1 border-l-2 border-gray-200 pl-8 dark:border-gray-700">
                                            <li v-for="sub in item.submenu" :key="sub.title">
                                                <Link :href="sub.link!" class="block rounded-lg px-3 py-2 text-sm text-gray-600 transition-colors hover:bg-gray-100 hover:text-primary dark:text-gray-400 dark:hover:bg-gray-800">
                                                {{ sub.title }}
                                                </Link>
                                            </li>
                                        </ul>
                                    </Transition>
                                </div>

                                <!-- Leaf Item (direct link) -->
                                <Link v-else :href="item.link!"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all hover:bg-gray-100 hover:text-primary"
                                    :class="[
                                        $page.url === item.link
                                            ? 'bg-primary/10 text-primary dark:bg-primary/20'
                                            : 'text-gray-700 dark:text-gray-300'
                                    ]">
                                <i v-if="item.icon" :class="item.icon"
                                    class="size-8! text-lg text-gray-500 transition-colors group-hover:text-primary flex items-center justify-center rounded bg-soft-secondary" />
                                <span :class="{ 'opacity-0 hidden': sidebarCollapsed }" class="transition-all">
                                    {{ item.title }}
                                </span>
                                <span v-if="item.badge"
                                    class="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                    {{ item.badge }}
                                </span>
                                </Link>
                            </li>
                        </ul>
                    </template>
                </nav>
            </div>
        </PerfectScrollbar>
    </aside>
</template>

<style scoped lang='postcss'>
/* Smooth slide animation */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* Active route highlight */
.router-link-active {
    @apply bg-primary/10 text-primary dark:bg-primary/20;
}

/* Collapsed mode: hide text smoothly */
@media (min-width: 1024px) {
    .w-20 span:not([class*="badge"]) {
        @apply opacity-0 pointer-events-none;
    }

    .w-20 h6 {
        @apply opacity-0;
    }
}
</style>