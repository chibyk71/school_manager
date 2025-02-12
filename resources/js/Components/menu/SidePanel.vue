<script lang="ts" setup>
import { menuItems, sidebarCollapsed } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, Button, Divider, PanelMenu } from 'primevue';
import { ref } from 'vue';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';

const toggleSubmenu = (e: MouseEvent & { currentTarget: HTMLAnchorElement }, hasSubmenu: boolean) => {
    const btn = e.currentTarget;
    const submenu = btn.nextElementSibling as HTMLElement;
    if (!hasSubmenu) {
        return;
    }

    if (!btn.classList.contains('subdrop')) {
        const parentUl = btn.closest('ul') as HTMLUListElement;
        parentUl.querySelectorAll('ul').forEach(ul => ul.style.display = 'none');
        parentUl.querySelectorAll('a').forEach(a => a.classList.remove('subdrop'));
        submenu.style.display = "block";
        submenu.style.height = "0"; // Reset height
        setTimeout(() => {
            submenu.style.height = submenu.scrollHeight + "px";
        }, 10);
        btn.classList.add('subdrop');
    } else {
        btn.classList.remove('subdrop');

        if (submenu.style.display === "block") {
            submenu.style.height = submenu.scrollHeight + "px"; // Set height before transition
            setTimeout(() => {
                submenu.style.height = "0";
            }, 10);

            submenu.addEventListener("transitionend", function hide() {
                submenu.style.display = "none";
                submenu.removeEventListener("transitionend", hide);
            });
        }
    }
}
</script>

<template>
    <div class="sidebar w-64 border-t" id="sidebar">
        <PerfectScrollbar>
            <div class="sidebar-inner overflow-hidden">
                <div id="sidebar-menu" class="sidebar-menu">
                    <ul>
                        <li class="mb-1">
                            <a href="javascript:void(0);" class="border dark:bg-slate-700 bg-slate-50 rounded p-2 mb-4">
                                <Avatar image="assets/img/icons/global-img.svg" class="avatar avatar-md rounded"
                                    alt="Profile" />
                                <span class="!text-color ml-2 font-normal">Global International</span>
                            </a>
                        </li>
                    </ul>

                    <ul>
                        <li v-for="item in menuItems" :key="item.header" class="mb-1">
                            <Divider align="left"><h6 :class="{'hidden':sidebarCollapsed}" class="mt-0">{{ item.header }}</h6></Divider>
                            <ul>
                                <li v-for="menu in item.items" class="submenu" :key="menu.title">
                                    <Button :as="menu.link ? Link : 'a'" class="relative"
                                        @click="(e) => toggleSubmenu(e, !!menu.submenu)" :unstyled="true"
                                        :href="menu.link ? menu.link : void (0)">
                                        <i :class="menu.icon" class="text-base/none size-6 flex items-center justify-center rounded-[5px] bg-surface-100 dark:bg-surface-500 text-color"></i>
                                        <span class="text-sm font-normal whitespace-nowrap text-color ml-2.5">{{ menu.title }}</span>
                                        <span v-if="menu.submenu" class="menu-arrow ti ti-chevron-right"></span>
                                    </Button>
                                    <ul class="hidden py-0 bottom-0 mt-2.5 m-0" v-if="menu.submenu">
                                        <li class="mb-0" v-for="{ link, title } in menu.submenu">
                                            <Link :href="link">{{ title }}</Link>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </PerfectScrollbar>
    </div>
</template>
