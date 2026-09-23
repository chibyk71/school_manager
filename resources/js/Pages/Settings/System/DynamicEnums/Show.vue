<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button, InputText, Textarea, Tag, Dialog } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

interface OptionRow {
  value: string
  label: string
  is_active: boolean
  is_required: boolean
  sort_order: number
  color: string | null
  icon: string | null
  source: string
  overridden: boolean
  enforced: boolean
  enforcement_reason: string | null
  tenant_label: string | null
  school_label: string | null
  tenant_option_id: string | null
  school_option_id: string | null
  capabilities: Record<string, boolean>
}

interface Detail {
  key: string
  label: string
  description: string | null
  scope: string
  options: OptionRow[]
  capabilities: Record<string, boolean>
}

const props = defineProps<{
  detail: Detail
  canManage: boolean
  canManageGlobals: boolean
  hasSchoolContext: boolean
}>()

const confirm = useConfirm()
const toast = useToast()
const showCreate = ref(false)
const showEdit = ref(false)
const editingOptionId = ref<string | null>(null)

const createForm = useForm({
  value: '',
  label: '',
  sort_order: 0,
  mode: 'option' as 'option' | 'override',
  tenant: false,
})

const definitionForm = useForm({
  label: props.detail.label,
  description: props.detail.description ?? '',
  tenant: !props.hasSchoolContext,
})

const editForm = useForm({
  label: '',
  sort_order: 0,
  color: '' as string | null,
  icon: '' as string | null,
})

const statusLabel = (opt: OptionRow) => {
  if (opt.overridden) return 'Customized'
  if (opt.source === 'school' && !opt.overridden) return 'School-only'
  return 'Inherited'
}

const statusSeverity = (opt: OptionRow) => {
  if (opt.overridden) return 'warn'
  if (opt.source === 'school') return 'info'
  return 'secondary'
}

function saveDefinition() {
  definitionForm.patch(
    route('settings.system.dynamic-enums.definition.update', props.detail.key),
    {
      preserveScroll: true,
      onSuccess: () => toast.add({ severity: 'success', summary: 'Saved', life: 2500 }),
    }
  )
}

function submitCreate() {
  createForm.post(route('settings.system.dynamic-enums.options.store', props.detail.key), {
    preserveScroll: true,
    onSuccess: () => {
      showCreate.value = false
      createForm.reset()
      toast.add({ severity: 'success', summary: 'Option created', life: 2500 })
    },
  })
}

function openEdit(opt: OptionRow) {
  editingOptionId.value = optionIdForAction(opt)
  editForm.label = opt.label
  editForm.sort_order = opt.sort_order
  editForm.color = opt.color
  editForm.icon = opt.icon
  showEdit.value = true
}

function submitEdit() {
  if (!editingOptionId.value) return
  editForm.patch(
    route('settings.system.dynamic-enums.options.update', [
      props.detail.key,
      editingOptionId.value,
    ]),
    {
      preserveScroll: true,
      onSuccess: () => {
        showEdit.value = false
        toast.add({ severity: 'success', summary: 'Option updated', life: 2500 })
      },
    }
  )
}

function postAction(name: string, optionId: string) {
  router.post(
    route(`settings.system.dynamic-enums.options.${name}`, [props.detail.key, optionId]),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast.add({ severity: 'success', summary: 'Updated', life: 2000 }),
      onError: (errors) => {
        const msg = Object.values(errors).flat().join(' ') || 'Action failed'
        toast.add({ severity: 'error', summary: msg, life: 4000 })
      },
    }
  )
}

function destroyOption(optionId: string) {
  confirm.require({
    message:
      'Permanently delete this option? This cannot be undone. Business records using this value will block deletion.',
    header: 'Confirm permanent deletion',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: () => {
      router.delete(
        route('settings.system.dynamic-enums.options.destroy', [
          props.detail.key,
          optionId,
        ]),
        {
          preserveScroll: true,
          onSuccess: () =>
            toast.add({ severity: 'success', summary: 'Deleted', life: 2500 }),
          onError: (errors) => {
            const msg = Object.values(errors).flat().join(' ') || 'Deletion blocked'
            toast.add({ severity: 'error', summary: msg, life: 5000 })
          },
        }
      )
    },
  })
}

function resetOverride(optionId: string) {
  confirm.require({
    message:
      'Remove this school customization? The tenant/default option will become effective again.',
    header: 'Reset to default',
    accept: () => postAction('reset', optionId),
  })
}

const optionIdForAction = (opt: OptionRow) =>
  opt.school_option_id || opt.tenant_option_id || ''

const canCreateTenant = computed(() => props.canManageGlobals)
const canCreateSchool = computed(() => props.canManage && props.hasSchoolContext)
</script>

<template>
  <Head :title="`Dynamic Enum — ${detail.label}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link
            :href="route('settings.system.dynamic-enums.index')"
            class="text-sm text-primary-600 hover:underline mb-1 inline-block"
          >
            ← Dynamic Enums
          </Link>
          <h1 class="text-xl font-semibold">{{ detail.label }}</h1>
          <p class="font-mono text-xs text-surface-500 mt-1">{{ detail.key }}</p>
          <p v-if="detail.description" class="text-sm text-surface-600 mt-2">
            {{ detail.description }}
          </p>
          <p class="text-xs text-surface-500 mt-1">
            Scope:
            <span class="font-medium">{{
              hasSchoolContext ? "This school's customization / effective" : 'Tenant / default'
            }}</span>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            v-if="canCreateTenant || canCreateSchool"
            label="Add option"
            icon="pi pi-plus"
            size="small"
            @click="showCreate = true"
          />
        </div>
      </div>
    </template>

    <div class="p-4 space-y-6">
      <section
        v-if="detail.capabilities.can_edit_definition"
        class="rounded border border-surface-200 p-4 space-y-3"
      >
        <h2 class="font-medium">Definition presentation</h2>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="text-xs text-surface-500">Label</label>
            <InputText v-model="definitionForm.label" class="w-full" />
          </div>
          <div>
            <label class="text-xs text-surface-500">Description</label>
            <Textarea v-model="definitionForm.description" class="w-full" rows="2" />
          </div>
        </div>
        <Button
          label="Save presentation"
          size="small"
          :loading="definitionForm.processing"
          @click="saveDefinition"
        />
      </section>

      <section class="rounded border border-surface-200 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-surface-50 text-left">
            <tr>
              <th class="px-3 py-2">Value</th>
              <th class="px-3 py-2">Effective label</th>
              <th class="px-3 py-2">Default</th>
              <th class="px-3 py-2">School</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Flags</th>
              <th class="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="opt in detail.options"
              :key="opt.value"
              class="border-t border-surface-100"
              :class="{ 'opacity-60': !opt.is_active && !opt.enforced }"
            >
              <td class="px-3 py-2 font-mono text-xs">{{ opt.value }}</td>
              <td class="px-3 py-2 font-medium">{{ opt.label }}</td>
              <td class="px-3 py-2 text-surface-600">{{ opt.tenant_label || '—' }}</td>
              <td class="px-3 py-2 text-surface-600">{{ opt.school_label || '—' }}</td>
              <td class="px-3 py-2">
                <Tag :value="statusLabel(opt)" :severity="statusSeverity(opt)" />
              </td>
              <td class="px-3 py-2 space-x-1">
                <Tag v-if="opt.is_required" value="required" severity="danger" />
                <Tag v-if="opt.enforced" value="enforced" severity="warn" />
                <Tag v-if="!opt.is_active" value="inactive" severity="secondary" />
              </td>
              <td class="px-3 py-2">
                <div class="flex flex-wrap gap-1">
                  <Button
                    v-if="opt.capabilities.can_edit"
                    label="Edit"
                    size="small"
                    text
                    @click="openEdit(opt)"
                  />
                  <Button
                    v-if="opt.capabilities.can_activate && !opt.is_active"
                    label="Activate"
                    size="small"
                    text
                    @click="postAction('activate', optionIdForAction(opt))"
                  />
                  <Button
                    v-if="opt.capabilities.can_deactivate && opt.is_active"
                    label="Deactivate"
                    size="small"
                    text
                    @click="postAction('deactivate', optionIdForAction(opt))"
                  />
                  <Button
                    v-if="opt.capabilities.can_make_required && !opt.is_required"
                    label="Make required"
                    size="small"
                    text
                    @click="postAction('make-required', optionIdForAction(opt))"
                  />
                  <Button
                    v-if="opt.capabilities.can_remove_required"
                    label="Remove required"
                    size="small"
                    text
                    @click="postAction('remove-required', optionIdForAction(opt))"
                  />
                  <Button
                    v-if="opt.capabilities.can_reset"
                    label="Reset"
                    size="small"
                    text
                    severity="warn"
                    @click="resetOverride(optionIdForAction(opt))"
                  />
                  <Button
                    v-if="opt.capabilities.can_delete"
                    label="Delete"
                    size="small"
                    text
                    severity="danger"
                    @click="destroyOption(optionIdForAction(opt))"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showCreate" header="Add option" modal class="w-full max-w-md">
      <div class="space-y-3">
        <div>
          <label class="text-xs">Value (machine identity)</label>
          <InputText v-model="createForm.value" class="w-full" placeholder="e.g. male" />
        </div>
        <div>
          <label class="text-xs">Label</label>
          <InputText v-model="createForm.label" class="w-full" placeholder="e.g. Male" />
        </div>
        <div v-if="canCreateTenant && canCreateSchool" class="flex gap-2 text-sm">
          <label class="flex items-center gap-1">
            <input v-model="createForm.tenant" type="checkbox" />
            Tenant / default
          </label>
        </div>
        <div v-if="!createForm.tenant && canCreateSchool" class="text-xs text-surface-500">
          Use mode “override” when the value already exists on the tenant baseline.
          <select v-model="createForm.mode" class="mt-1 w-full border rounded px-2 py-1">
            <option value="option">School-only option</option>
            <option value="override">Override tenant value</option>
          </select>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showCreate = false" />
        <Button label="Create" :loading="createForm.processing" @click="submitCreate" />
      </template>
    </Dialog>

    <Dialog v-model:visible="showEdit" header="Edit option presentation" modal class="w-full max-w-md">
      <div class="space-y-3">
        <div>
          <label class="text-xs">Label</label>
          <InputText v-model="editForm.label" class="w-full" />
        </div>
        <div>
          <label class="text-xs">Sort order</label>
          <InputText v-model.number="editForm.sort_order" type="number" class="w-full" />
        </div>
        <div>
          <label class="text-xs">Color</label>
          <InputText v-model="editForm.color" class="w-full" placeholder="optional" />
        </div>
        <div>
          <label class="text-xs">Icon</label>
          <InputText v-model="editForm.icon" class="w-full" placeholder="optional" />
        </div>
        <p class="text-xs text-surface-500">Canonical value cannot be changed.</p>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showEdit = false" />
        <Button label="Save" :loading="editForm.processing" @click="submitEdit" />
      </template>
    </Dialog>
  </AuthenticatedLayout>
</template>
