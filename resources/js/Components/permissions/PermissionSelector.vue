<script setup lang="ts">
/**
 * Reusable PermissionSelector (Permission Phase 5).
 *
 * Pure presentation / selection state. Does not know about roles or users,
 * does not persist, does not authorize, does not call APIs.
 *
 * Parent owns selectedPermissionIds via v-model and the immutable catalogue.
 */
import { computed, ref, watch } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

export type PermissionItem = {
    id: number | string;
    name: string;
    display_name?: string | null;
    description?: string | null;
};

export type PermissionGroup = {
    key: string;
    label: string;
    permissions: PermissionItem[];
};

const props = defineProps<{
    groups: PermissionGroup[];
    modelValue: Array<number | string>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [ids: Array<number | string>];
}>();

const search = ref('');
const activePanels = ref<string[]>([]);

const selectedSet = computed(() => new Set(props.modelValue.map((id) => String(id))));

const totalCount = computed(() =>
    props.groups.reduce((sum, g) => sum + g.permissions.length, 0),
);

const selectedCount = computed(() => selectedSet.value.size);

function matchesSearch(p: PermissionItem, q: string): boolean {
    if (!q) return true;
    const hay = `${p.display_name ?? ''} ${p.name} ${p.description ?? ''}`.toLowerCase();
    return hay.includes(q);
}

const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.groups;
    }
    return props.groups
        .map((g) => {
            const moduleMatch = g.label.toLowerCase().includes(q) || g.key.toLowerCase().includes(q);
            const perms = g.permissions.filter(
                (p) => moduleMatch || matchesSearch(p, q),
            );
            return { ...g, permissions: perms };
        })
        .filter((g) => g.permissions.length > 0);
});

watch(
    filteredGroups,
    (groups) => {
        if (search.value.trim()) {
            activePanels.value = groups.map((g) => g.key);
        }
    },
    { immediate: true },
);

watch(
    () => props.groups,
    (groups) => {
        if (!search.value.trim() && activePanels.value.length === 0) {
            activePanels.value = groups.map((g) => g.key);
        }
    },
    { immediate: true },
);

function idMap(): Map<string, number | string> {
    const map = new Map<string, number | string>();
    for (const g of props.groups) {
        for (const p of g.permissions) {
            map.set(String(p.id), p.id);
        }
    }
    return map;
}

function setSelection(ids: Array<number | string>) {
    emit('update:modelValue', ids);
}

function togglePermission(id: number | string, checked: boolean) {
    const key = String(id);
    const next = new Set(selectedSet.value);
    if (checked) {
        next.add(key);
    } else {
        next.delete(key);
    }
    const map = idMap();
    setSelection(Array.from(next).map((k) => map.get(k) ?? k));
}

function groupSelectedCount(group: PermissionGroup): number {
    return group.permissions.filter((p) => selectedSet.value.has(String(p.id))).length;
}

function groupAllSelected(group: PermissionGroup): boolean {
    return group.permissions.length > 0 && groupSelectedCount(group) === group.permissions.length;
}

function groupIndeterminate(group: PermissionGroup): boolean {
    const n = groupSelectedCount(group);
    return n > 0 && n < group.permissions.length;
}

function toggleGroup(group: PermissionGroup, checked: boolean) {
    const next = new Set(selectedSet.value);
    for (const p of group.permissions) {
        if (checked) {
            next.add(String(p.id));
        } else {
            next.delete(String(p.id));
        }
    }
    const map = idMap();
    setSelection(Array.from(next).map((k) => map.get(k) ?? k));
}

function selectAllVisible() {
    const next = new Set(selectedSet.value);
    for (const g of filteredGroups.value) {
        for (const p of g.permissions) {
            next.add(String(p.id));
        }
    }
    const map = idMap();
    setSelection(Array.from(next).map((k) => map.get(k) ?? k));
}

function clearAll() {
    setSelection([]);
}

function labelFor(p: PermissionItem): string {
    return (p.display_name && String(p.display_name).trim()) || p.name;
}
</script>

<template>
    <div class="permission-selector space-y-3" :class="{ 'opacity-60 pointer-events-none': disabled }">
        <div class="flex flex-wrap items-center gap-2 justify-between">
            <div class="relative flex-1 min-w-[12rem]">
                <span class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400" />
                <InputText
                    v-model="search"
                    placeholder="Search permissions, modules, identifiers…"
                    class="w-full pl-9"
                    :disabled="disabled"
                />
            </div>
            <div class="flex items-center gap-2">
                <Badge :value="`${selectedCount} / ${totalCount}`" severity="info" />
                <Button
                    label="Select All"
                    size="small"
                    text
                    :disabled="disabled || filteredGroups.length === 0"
                    @click="selectAllVisible"
                />
                <Button
                    label="Clear"
                    size="small"
                    text
                    severity="secondary"
                    :disabled="disabled || selectedCount === 0"
                    @click="clearAll"
                />
            </div>
        </div>

        <Accordion v-model:value="activePanels" multiple class="permission-selector-accordion">
            <AccordionPanel
                v-for="group in filteredGroups"
                :key="group.key"
                :value="group.key"
            >
                <AccordionHeader>
                    <div class="flex items-center gap-3 w-full pr-2">
                        <Checkbox
                            :modelValue="groupAllSelected(group)"
                            :indeterminate="groupIndeterminate(group)"
                            :binary="true"
                            :disabled="disabled || group.permissions.length === 0"
                            @update:modelValue="(v: boolean) => toggleGroup(group, v)"
                            @click.stop
                        />
                        <span class="font-medium">{{ group.label }}</span>
                        <Badge
                            :value="`${groupSelectedCount(group)} / ${group.permissions.length}`"
                            severity="secondary"
                            class="ml-auto"
                        />
                    </div>
                </AccordionHeader>
                <AccordionContent>
                    <ul class="space-y-2 pl-1">
                        <li
                            v-for="perm in group.permissions"
                            :key="String(perm.id)"
                            class="flex items-start gap-3 py-1"
                        >
                            <Checkbox
                                :modelValue="selectedSet.has(String(perm.id))"
                                :binary="true"
                                :disabled="disabled"
                                :inputId="`perm-${perm.id}`"
                                @update:modelValue="(v: boolean) => togglePermission(perm.id, v)"
                            />
                            <label :for="`perm-${perm.id}`" class="cursor-pointer leading-tight">
                                <span class="font-medium">{{ labelFor(perm) }}</span>
                                <span class="block text-xs text-surface-500 font-mono">{{ perm.name }}</span>
                                <span v-if="perm.description" class="block text-xs text-surface-400 mt-0.5">
                                    {{ perm.description }}
                                </span>
                            </label>
                        </li>
                    </ul>
                </AccordionContent>
            </AccordionPanel>
        </Accordion>

        <p v-if="filteredGroups.length === 0" class="text-sm text-surface-500 text-center py-6">
            No permissions match your search.
        </p>
    </div>
</template>
