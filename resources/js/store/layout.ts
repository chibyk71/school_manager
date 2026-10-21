import { ref } from 'vue';

/** Desktop: icon-only mini sidebar */
export const sidebarCollapsed = ref(false);

/** Mobile/tablet: off-canvas drawer visibility */
export const mobileSidebarOpen = ref(false);

/** Shared layout widths (px) — keep AuthenticatedLayout / SidePanel in sync */
export const SIDEBAR_WIDTH_EXPANDED = 260;
export const SIDEBAR_WIDTH_COLLAPSED = 80;
