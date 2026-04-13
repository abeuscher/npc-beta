<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { Widget, WidgetPreset, WidgetDraftPreset } from '../types'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const widgetType = computed(() =>
  store.widgetTypes.find((wt) => wt.handle === props.widget.widget_type_handle) ?? null
)

const authoredPresets = computed<WidgetPreset[]>(() =>
  widgetType.value?.presets ?? []
)

const draftPresets = computed<WidgetDraftPreset[]>(() =>
  widgetType.value?.draft_presets ?? []
)

const blankPreset = computed<WidgetPreset>(() => {
  const defaults = props.widget.resolved_defaults ?? {}
  const appearanceKeys = new Set(
    (props.widget.widget_type_config_schema ?? [])
      .filter((f) => (f.group ?? 'content') === 'appearance')
      .map((f) => f.key)
  )
  const appearanceDefaults: Record<string, any> = {}
  for (const key of Object.keys(defaults)) {
    if (appearanceKeys.has(key)) {
      appearanceDefaults[key] = defaults[key]
    }
  }
  return {
    handle: '__blank',
    label: 'Blank',
    description: 'Reset appearance to defaults.',
    config: appearanceDefaults,
    appearance_config: {},
  }
})

function apply(preset: WidgetPreset): void {
  store.applyPreset(props.widget.id, preset)
}

const saving = ref(false)
const error = ref<string | null>(null)

async function saveDraft(): Promise<void> {
  if (saving.value) return
  saving.value = true
  error.value = null
  try {
    await store.saveDraftPreset(props.widget.id)
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to save preset.'
  } finally {
    saving.value = false
  }
}

const editingId = ref<string | null>(null)
const editLabel = ref('')
const editDescription = ref('')
const renameError = ref<string | null>(null)

function startRename(preset: WidgetDraftPreset): void {
  editingId.value = preset.id
  editLabel.value = preset.label
  editDescription.value = preset.description ?? ''
  renameError.value = null
}

function cancelRename(): void {
  editingId.value = null
  renameError.value = null
}

async function commitRename(preset: WidgetDraftPreset): Promise<void> {
  const label = editLabel.value.trim()
  if (label === '') {
    renameError.value = 'Label is required.'
    return
  }
  try {
    await store.renameDraftPreset(preset.id, {
      label,
      description: editDescription.value.trim() || null,
    })
    editingId.value = null
  } catch (e: any) {
    renameError.value = e?.message ?? 'Rename failed.'
  }
}

async function removeDraft(preset: WidgetDraftPreset): Promise<void> {
  if (!window.confirm(`Delete draft preset "${preset.label}"?`)) return
  try {
    await store.deleteDraftPreset(preset.id)
  } catch (e: any) {
    error.value = e?.message ?? 'Delete failed.'
  }
}

const exportedId = ref<string | null>(null)

function exportToClipboard(preset: WidgetDraftPreset): void {
  const literal = buildPhpLiteral(preset)
  navigator.clipboard.writeText(literal).then(() => {
    exportedId.value = preset.id
    setTimeout(() => {
      if (exportedId.value === preset.id) exportedId.value = null
    }, 2000)
  }).catch((e) => {
    error.value = 'Clipboard copy failed: ' + (e?.message ?? 'unknown error')
  })
}

function buildPhpLiteral(preset: WidgetDraftPreset): string {
  const lines: string[] = []
  lines.push('[')
  lines.push(`    'handle'            => ${phpString(preset.handle)},`)
  lines.push(`    'label'             => ${phpString(preset.label)},`)
  lines.push(`    'description'       => ${preset.description ? phpString(preset.description) : 'null'},`)
  lines.push(`    'config'            => ${phpArray(preset.config ?? {}, 1)},`)
  lines.push(`    'appearance_config' => ${phpArray(preset.appearance_config ?? {}, 1)},`)
  lines.push('],')
  return lines.join('\n')
}

function phpString(s: string): string {
  return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'"
}

function phpScalar(v: any, indent: number): string {
  if (v === null || v === undefined) return 'null'
  if (typeof v === 'boolean') return v ? 'true' : 'false'
  if (typeof v === 'number') return String(v)
  if (typeof v === 'string') return phpString(v)
  if (Array.isArray(v) || typeof v === 'object') return phpArray(v, indent)
  return phpString(String(v))
}

function phpArray(v: any, indent: number): string {
  if (Array.isArray(v)) {
    if (v.length === 0) return '[]'
    const pad = '    '.repeat(indent + 1)
    const outer = '    '.repeat(indent)
    const parts = v.map((item) => `${pad}${phpScalar(item, indent + 1)},`)
    return '[\n' + parts.join('\n') + '\n' + outer + ']'
  }
  const entries = Object.entries(v)
  if (entries.length === 0) return '[]'
  const pad = '    '.repeat(indent + 1)
  const outer = '    '.repeat(indent)
  const keyWidth = Math.max(...entries.map(([k]) => phpString(k).length))
  const parts = entries.map(([k, val]) => {
    const keyLit = phpString(k).padEnd(keyWidth, ' ')
    return `${pad}${keyLit} => ${phpScalar(val, indent + 1)},`
  })
  return '[\n' + parts.join('\n') + '\n' + outer + ']'
}
</script>

<template>
  <div class="preset-gallery">
    <button
      type="button"
      class="preset-save"
      :disabled="saving"
      @click="saveDraft"
    >
      {{ saving ? 'Saving…' : 'Save current appearance as preset' }}
    </button>

    <p v-if="error" class="preset-error">{{ error }}</p>

    <button
      type="button"
      class="preset-card"
      @click="apply(blankPreset)"
    >
      <div class="preset-card__thumb" aria-hidden="true"></div>
      <div class="preset-card__body">
        <div class="preset-card__label-row">
          <div class="preset-card__label">{{ blankPreset.label }}</div>
        </div>
        <div v-if="blankPreset.description" class="preset-card__description">
          {{ blankPreset.description }}
        </div>
      </div>
    </button>

    <button
      v-for="preset in authoredPresets"
      :key="'code-' + preset.handle"
      type="button"
      class="preset-card"
      @click="apply(preset)"
    >
      <div class="preset-card__thumb" aria-hidden="true"></div>
      <div class="preset-card__body">
        <div class="preset-card__label-row">
          <div class="preset-card__label">{{ preset.label }}</div>
        </div>
        <div v-if="preset.description" class="preset-card__description">
          {{ preset.description }}
        </div>
      </div>
    </button>

    <div
      v-for="preset in draftPresets"
      :key="'draft-' + preset.id"
      class="preset-card preset-card--draft"
    >
      <button
        type="button"
        class="preset-card__apply"
        @click="apply(preset)"
      >
        <div class="preset-card__thumb" aria-hidden="true"></div>
      </button>

      <div class="preset-card__body">
        <div class="preset-card__label-row">
          <template v-if="editingId === preset.id">
            <input
              v-model="editLabel"
              class="preset-card__label-input"
              type="text"
              @keydown.enter.prevent="commitRename(preset)"
              @keydown.escape.prevent="cancelRename"
            />
          </template>
          <template v-else>
            <div class="preset-card__label">{{ preset.label }}</div>
          </template>
          <span class="preset-card__badge">Draft</span>
        </div>

        <template v-if="editingId === preset.id">
          <textarea
            v-model="editDescription"
            class="preset-card__description-input"
            rows="2"
            placeholder="Description (optional)"
            @keydown.escape.prevent="cancelRename"
          ></textarea>
          <p v-if="renameError" class="preset-error">{{ renameError }}</p>
          <div class="preset-card__edit-actions">
            <button type="button" @click="commitRename(preset)">Save</button>
            <button type="button" @click="cancelRename">Cancel</button>
          </div>
        </template>
        <template v-else>
          <div v-if="preset.description" class="preset-card__description">
            {{ preset.description }}
          </div>
          <div class="preset-card__actions">
            <button type="button" @click="startRename(preset)">Rename</button>
            <button type="button" @click="exportToClipboard(preset)">
              {{ exportedId === preset.id ? 'Copied!' : 'Export' }}
            </button>
            <button type="button" class="preset-card__delete" @click="removeDraft(preset)">Delete</button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.preset-gallery {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.preset-save {
  padding: 0.5rem 0.75rem;
  border: 1px dashed #9ca3af;
  border-radius: 0.375rem;
  background: #f9fafb;
  font-size: 0.8125rem;
  color: #374151;
  cursor: pointer;
}

.preset-save:hover:not(:disabled) {
  border-color: #6b7280;
  background: #f3f4f6;
}

.preset-save:disabled {
  cursor: progress;
  opacity: 0.6;
}

.preset-error {
  margin: 0;
  padding: 0.375rem 0.5rem;
  font-size: 0.75rem;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 0.25rem;
}

.preset-card {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #fff;
  cursor: pointer;
  overflow: hidden;
  text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.preset-card:hover {
  border-color: #9ca3af;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.preset-card--draft {
  cursor: default;
}

.preset-card--draft:hover {
  border-color: #e5e7eb;
  box-shadow: none;
}

.preset-card__apply {
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
}

.preset-card__thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #f3f4f6;
  border-bottom: 1px solid #e5e7eb;
}

.preset-card__body {
  padding: 0.625rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.preset-card__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.preset-card__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
}

.preset-card__label-input {
  flex: 1;
  padding: 0.25rem 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.preset-card__description {
  font-size: 0.75rem;
  color: #6b7280;
  line-height: 1.4;
}

.preset-card__description-input {
  width: 100%;
  padding: 0.25rem 0.375rem;
  font-size: 0.75rem;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  resize: vertical;
}

.preset-card__badge {
  display: inline-block;
  padding: 0.0625rem 0.375rem;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 0.25rem;
}

.preset-card__actions,
.preset-card__edit-actions {
  display: flex;
  gap: 0.375rem;
  font-size: 0.75rem;
}

.preset-card__actions button,
.preset-card__edit-actions button {
  padding: 0.1875rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  color: #374151;
  cursor: pointer;
}

.preset-card__actions button:hover,
.preset-card__edit-actions button:hover {
  border-color: #9ca3af;
  background: #f9fafb;
}

.preset-card__delete {
  color: #b91c1c !important;
}
</style>
