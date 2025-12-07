<!-- resources/js/Components/shared/RenderCell.vue -->
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
 * Render a declarative TemplateNode (HTML-like object syntax)
 */
const renderTemplate = (node: TemplateNode | string, row: T): VNode | VNode[] => {
    if (typeof node === 'string') {
        return h('span', node)
    }

    if (Array.isArray(node)) {
        return node.map(n => renderTemplate(n, row)).flat()
    }

    const {
        template = 'div',
        text,
        src,
        children = [],
        class: className,
        style,
        props: extraProps = {},
        on = {},
        ...rest
    } = node

    // Resolve dynamic values (functions → value)
    const evalProp = (val: any) => (typeof val === 'function' ? val(row) : val)

    const resolvedProps: Record<string, any> = {
        ...rest,
        ...extraProps,
        class: evalProp(className),
        style: evalProp(style),
    }

    if (src !== undefined) resolvedProps.src = evalProp(src)

    // Event handlers get row injected as last arg
    const listeners = Object.entries(on).reduce((acc, [event, handler]) => {
        acc[event] = (...args: any[]) => (handler as Function)(...args, row)
        return acc
    }, {} as Record<string, any>)

    const childNodes = children.length
        ? children.map(child => renderTemplate(child, row)).flat()
        : text !== undefined
            ? evalProp(text)
            : undefined

    return h(template, { ...resolvedProps, ...listeners }, childNodes || undefined)
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
