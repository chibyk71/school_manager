<!-- resources/js/composables/datatable/RenderCell.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed, h, resolveComponent, isVNode, type VNode, type VNodeChild } from 'vue'
import type { ComponentRenderer, TemplateNode } from '@/types/datatables'

const props = defineProps<{
    /** Result returned from column.render(row) */
    renderResult: any
    /** Current row data – injected automatically */
    row: T
}>()

/**
 * Resolve a component by name safely.
 * Returns a fallback component if not found (never throws).
 */
const resolveComponentSafe = (name: string): any => {
    try {
        const comp = resolveComponent(name)
        return typeof comp === 'string' ? null : comp
    } catch {
        return null
    }
}

/**
 * Normalize any value to a safe VNodeChild.
 * Prevents Vue from crashing on null/undefined/false in children.
 */
const safe = (value: any): VNodeChild => {
    if (value == null || value === false) {
        return h('span', { class: 'text-gray-400 select-none', 'aria-hidden': 'true' }, '—')
    }
    return value
}

/**
 * Recursively renders a declarative TemplateNode into Vue VNodes.
 *
 * Supports:
 * - Simple string shorthand → wrapped in <span>
 * - Array of nodes → flattened and rendered
 * - Full TemplateNode object:
 *   - Custom tag via `template` (default: div)
 *   - Static or dynamic `text` content
 *   - `src` for images
 *   - Nested `children`
 *   - Dynamic `class`, `style`, `props`
 *   - Event listeners with automatic `row` injection
 *   - Arbitrary HTML attributes via rest spread
 *
 * All dynamic values (functions) are evaluated with the current `row` data.
 *
 * @param node  The TemplateNode (or string/array) to render
 * @param row   The current row data – used for dynamic evaluation
 * @returns     VNode or array of VNodes
 */
const renderTemplate = (node: TemplateNode | string | TemplateNode[], row: T): VNode | VNode[] => {
    // ------------------------------------------------------------------
    // 1. Handle string shorthand: "Hello" → <span>Hello</span>
    // ------------------------------------------------------------------
    if (typeof node === 'string') {
        // Wrap bare strings in span for consistent styling and safety
        return h('span', node)
    }

    // ------------------------------------------------------------------
    // 2. Handle array of nodes (e.g., multiple children)
    // ------------------------------------------------------------------
    if (Array.isArray(node)) {
        // Recursively render each child and flatten the result
        // flat() ensures nested arrays don't create extra wrappers
        return node.map(child => renderTemplate(child, row)).flat()
    }

    // ------------------------------------------------------------------
    // 3. Full TemplateNode object
    // ------------------------------------------------------------------
    const {
        template = 'div',           // Default HTML tag
        text,                       // Text content (string | number | function)
        src,                        // Image source (for <img>)
        children = [],              // Nested children
        class: className,           // Class (string | array | object | function)
        style,                      // Inline style (string | object | function)
        props: extraProps = {},     // Additional props
        on = {},                    // Event listeners
        ...rest                     // Any other HTML attributes (e.g., id, title, aria-*)
    } = node

    // ------------------------------------------------------------------
    // 4. Helper: Evaluate dynamic values (functions → resolved value)
    // ------------------------------------------------------------------
    const evalProp = (val: any): any => {
        return typeof val === 'function' ? val(row) : val
    }

    // ------------------------------------------------------------------
    // 5. Build resolved props object
    // ------------------------------------------------------------------
    const resolvedProps: Record<string, any> = {
        // Spread arbitrary attributes first (e.g., id, data-*, aria-*)
        ...rest,

        // Merge user-provided extra props
        ...extraProps,

        // Evaluate class and style if dynamic
        class: evalProp(className),
        style: evalProp(style),
    }

    // Special handling for src attribute (common in images)
    if (src !== undefined) {
        resolvedProps.src = evalProp(src)
    }

    // ------------------------------------------------------------------
    // 6. Build event listeners with automatic `row` injection
    //    Useful for actions like onClick={(e, row) => ...}
    // ------------------------------------------------------------------
    const listeners = Object.entries(on).reduce((acc, [event, handler]) => {
        acc[event] = (...args: any[]) => {
            // Call handler with original args + row as last parameter
            ; (handler as Function)(...args, row)
        }
        return acc
    }, {} as Record<string, any>)

    // ------------------------------------------------------------------
    // 7. Build children VNodes safely
    // ------------------------------------------------------------------
    let childrenVNodes: VNodeChild[] = []

    if (children.length > 0) {
        // Recursively render nested children
        childrenVNodes = children
            .map(child => renderTemplate(child, row))
            .flat()
            // Apply safety wrapper (handles null/undefined → em dash)
            .map(safe)
    } else if (text !== undefined) {
        // Critical fix: Wrap text content in array
        // Vue ignores primitive strings/numbers passed directly as children
        const content = evalProp(text)
        childrenVNodes = [safe(content)]
    }
    // If no children and no text → childrenVNodes remains empty array

    // ------------------------------------------------------------------
    // 8. Create and return the final VNode
    // ------------------------------------------------------------------
    // Only pass children if we have any — passing empty array causes issues in some cases
    const finalChildren = childrenVNodes.length > 0 ? childrenVNodes : undefined

    return h(
        template as string,           // Tag name
        { ...resolvedProps, ...listeners },  // Props + events
        finalChildren                  // Children (or undefined)
    )
}

/**
 * Main rendering pipeline – single source of truth
 */
const rendered = computed<VNodeChild>(() => {
    const result = props.renderResult
    const row = props.row

    // 1. Primitives & explicit empties
    if (result == null || result === false) {
        return safe(null) as VNode
    }

    if (typeof result === 'string' || typeof result === 'number' || typeof result === 'boolean') {
        return h('span', result)
    }

    // 2. Render function: (row) => vnode
    if (typeof result === 'function') {
        try {
            const output = result(row)
            return Array.isArray(output)
                ? h('span', output.map(safe))
                : safe(output) as VNode
        } catch (err) {
            console.error('[RenderCell] Render function error:', err)
            return h('span', { class: 'text-red-600 text-xs font-medium' }, 'Render Error')
        }
    }

    // 3. Already a VNode or array of VNodes
    if (isVNode(result) || Array.isArray(result)) {
        return Array.isArray(result)
            ? h('span', result.map(safe))
            : result
    }

    // 4. ComponentRenderer object
    if (result && typeof result === 'object' && 'component' in result) {
        const { component, props = {}, on = {}, slots = {} } = result as ComponentRenderer<T>

        const comp = typeof component === 'string'
            ? resolveComponentSafe(component) || 'span'
            : component

        const finalProps = typeof props === 'function' ? props(row) : { ...props, row }

        const listeners = Object.entries(on).reduce((acc, [event, handler]) => {
            acc[event] = (...args: any[]) => (handler as Function)(...args, row)
            return acc
        }, {} as Record<string, any>)

        return h(comp, { ...finalProps, ...listeners }, slots)
    }

    // 5. TemplateNode syntax
    if (result && typeof result === 'object' && 'template' in result) {
        const nodes = renderTemplate(result as TemplateNode, row)
        return Array.isArray(nodes)
            ? h('span', nodes)
            : nodes
    }

    // 6. Fallback (dev-only debug)
    if (import.meta.env.DEV) {
        console.warn('[RenderCell] Unsupported render result:', result)
        return h(
            'span',
            { class: 'text-xs text-orange-600 bg-orange-50 dark:bg-orange-900/30 px-2 py-1 rounded' },
            'Unsupported render'
        )
    }

    return safe(null) as VNode
})
</script>

<template>
    <!--
    We use <component :is> with a single VNode to:
    - Support fragments (arrays)
    - Prevent unnecessary wrapping
    - Keep reactivity optimal
  -->
    <component :is="rendered" class="inline-block align-middle" data-testid="render-cell" />
</template>

<style scoped lang="postcss">
/* Ensures consistent vertical alignment in table cells */
:deep(img) {
    @apply inline-block align-middle;
}

/* Prevent layout shift on empty cells */
:deep(span:empty)::before {
    content: '\2014';
    /* em dash */
    @apply text-gray-400;
}
</style>
