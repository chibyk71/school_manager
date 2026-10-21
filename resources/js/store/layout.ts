import { ref } from 'vue';

/**
 * Layout shell breakpoints and shared state.
 *
 * Desktop shell begins at LAYOUT_DESKTOP_MIN (inclusive).
 * Viewports strictly below that width use the mobile drawer.
 * Chosen to match @theme --breakpoint-lg: 992px in app.css.
 * CSS utilities use explicit max-[991px] / min-[992px] variants so
 * behavior does not depend on whether Tailwind resolves lg as 992 or 1024.
 */
export const LAYOUT_DESKTOP_MIN = 992;

/** Desktop: icon-only mini sidebar */
export const sidebarCollapsed = ref(false);

/** Mobile/tablet: off-canvas drawer visibility */
export const mobileSidebarOpen = ref(false);

/** Shared sidebar widths (px) — keep AuthenticatedLayout / SidePanel in sync */
export const SIDEBAR_WIDTH_EXPANDED = 260;
export const SIDEBAR_WIDTH_COLLAPSED = 80;

/** True when viewport is below the desktop shell threshold. */
export function isLayoutMobileViewport(width: number = typeof window !== 'undefined' ? window.innerWidth : LAYOUT_DESKTOP_MIN): boolean {
    return width < LAYOUT_DESKTOP_MIN;
}
