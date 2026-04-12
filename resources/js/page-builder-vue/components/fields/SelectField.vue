<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import type { FieldDef, Widget } from '../../types'
import * as api from '../../api'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const resolvedOptions = ref<Record<string, string>>({})
const loading = ref(false)

const options = computed(() => {
  if (props.field.options && Object.keys(props.field.options).length > 0) {
    return props.field.options
  }
  return resolvedOptions.value
})

async function resolveOptions() {
  const source = props.field.options_from
  if (!source) return

  loading.value = true
  try {
    if (source.startsWith('collection_fields:')) {
      const dependsOn = props.field.depends_on ?? 'collection_handle'
      const handle = props.widget.config[dependsOn] ?? ''
      if (!handle) {
        resolvedOptions.value = {}
        return
      }
      const res = await api.getCollectionFields(handle)
      const filterType = source.replace('collection_fields:', '')
      const filtered = filterType
        ? res.fields.filter((f) => f.type === filterType)
        : res.fields
      const opts: Record<string, string> = {}
      for (const f of filtered) {
        opts[f.key] = f.label
      }
      resolvedOptions.value = opts
    } else {
      const res = await api.getDataSource(source)
      resolvedOptions.value = res.options
    }
  } catch (e) {
    console.error('Failed to resolve select options:', e)
    resolvedOptions.value = {}
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (props.field.options_from) {
    resolveOptions()
  }
})

// Re-resolve when dependency field changes (for collection_fields:* sources)
if (props.field.options_from?.startsWith('collection_fields:')) {
  const dependsOn = props.field.depends_on ?? 'collection_handle'
  watch(
    () => props.widget.config[dependsOn],
    () => resolveOptions()
  )
}
</script>

<template>
  <select
    :value="modelValue ?? ''"
    class="inspector-control"
    @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <option value="">&mdash; Select &mdash;</option>
    <option
      v-for="(label, value) in options"
      :key="value"
      :value="value"
    >
      {{ label }}
    </option>
  </select>
</template>

