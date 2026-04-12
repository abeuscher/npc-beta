<script setup lang="ts">
import { computed } from 'vue'

type AlignmentValue =
  | 'top-left'
  | 'top-center'
  | 'top-right'
  | 'middle-left'
  | 'center'
  | 'middle-right'
  | 'bottom-left'
  | 'bottom-center'
  | 'bottom-right'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    disabled?: boolean
    label?: string
  }>(),
  {
    modelValue: 'center',
    disabled: false,
    label: '',
  }
)

const emit = defineEmits<{
  'update:modelValue': [value: AlignmentValue]
}>()

const ROW_NAMES = ['top', 'middle', 'bottom'] as const
const COL_NAMES = ['left', 'center', 'right'] as const

// Visual coordinates inside the 48-unit SVG viewBox
const ROW_Y = [10, 24, 38]
const COL_X = [10, 24, 38]

type RowIndex = 0 | 1 | 2
type ColIndex = 0 | 1 | 2

function valueToCoords(value: string): { row: RowIndex; col: ColIndex } {
  // The 'center' shorthand maps to middle/center.
  if (value === 'center') return { row: 1, col: 1 }

  const [rowName, colName] = value.split('-') as [string, string]
  const rowIdx = ROW_NAMES.indexOf(rowName as any)
  const colIdx = COL_NAMES.indexOf(colName as any)
  if (rowIdx === -1 || colIdx === -1) return { row: 1, col: 1 }
  return { row: rowIdx as RowIndex, col: colIdx as ColIndex }
}

function coordsToValue(row: RowIndex, col: ColIndex): AlignmentValue {
  if (row === 1 && col === 1) return 'center'
  return `${ROW_NAMES[row]}-${COL_NAMES[col]}` as AlignmentValue
}

const selected = computed(() => valueToCoords(props.modelValue))

const ariaLabel = computed(() => {
  const base = props.label ? `${props.label}: ` : 'Alignment: '
  return base + props.modelValue
})

function selectCell(row: RowIndex, col: ColIndex): void {
  if (props.disabled) return
  emit('update:modelValue', coordsToValue(row, col))
}

function onKeydown(e: KeyboardEvent): void {
  if (props.disabled) return

  const { row, col } = selected.value
  let nextRow = row
  let nextCol = col

  switch (e.key) {
    case 'ArrowUp':
      nextRow = Math.max(0, row - 1) as RowIndex
      break
    case 'ArrowDown':
      nextRow = Math.min(2, row + 1) as RowIndex
      break
    case 'ArrowLeft':
      nextCol = Math.max(0, col - 1) as ColIndex
      break
    case 'ArrowRight':
      nextCol = Math.min(2, col + 1) as ColIndex
      break
    case 'Enter':
    case ' ':
      // No-op confirmation: keeps focus consistent with other form controls.
      e.preventDefault()
      return
    default:
      return
  }

  if (nextRow !== row || nextCol !== col) {
    e.preventDefault()
    emit('update:modelValue', coordsToValue(nextRow, nextCol))
  }
}

const cells = computed(() => {
  const out: { row: RowIndex; col: ColIndex; cx: number; cy: number; value: AlignmentValue }[] = []
  for (let r = 0; r < 3; r++) {
    for (let c = 0; c < 3; c++) {
      out.push({
        row: r as RowIndex,
        col: c as ColIndex,
        cx: COL_X[c],
        cy: ROW_Y[r],
        value: coordsToValue(r as RowIndex, c as ColIndex),
      })
    }
  }
  return out
})
</script>

<template>
  <div class="nine-point" :class="{ 'nine-point--disabled': disabled }">
    <label v-if="label" class="inspector-label">{{ label }}</label>
    <div
      class="nine-point__control"
      role="radiogroup"
      :aria-label="ariaLabel"
      :aria-disabled="disabled"
      :tabindex="disabled ? -1 : 0"
      @keydown="onKeydown"
    >
      <svg
        class="nine-point__svg"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
      >
        <rect
          x="1"
          y="1"
          width="46"
          height="46"
          rx="4"
          ry="4"
          class="nine-point__bg"
        />
        <line x1="16" y1="4" x2="16" y2="44" class="nine-point__grid-line" />
        <line x1="32" y1="4" x2="32" y2="44" class="nine-point__grid-line" />
        <line x1="4" y1="16" x2="44" y2="16" class="nine-point__grid-line" />
        <line x1="4" y1="32" x2="44" y2="32" class="nine-point__grid-line" />
        <g
          v-for="cell in cells"
          :key="cell.value"
          class="nine-point__cell"
          :class="{
            'nine-point__cell--selected':
              cell.row === selected.row && cell.col === selected.col,
          }"
          @click="selectCell(cell.row, cell.col)"
        >
          <circle
            :cx="cell.cx"
            :cy="cell.cy"
            r="6"
            class="nine-point__hit"
          />
          <circle
            :cx="cell.cx"
            :cy="cell.cy"
            :r="cell.row === selected.row && cell.col === selected.col ? 4 : 3"
            class="nine-point__dot"
          />
          <title>{{ cell.value }}</title>
        </g>
      </svg>
    </div>
  </div>
</template>

<style scoped>
.nine-point {
  display: inline-flex;
  flex-direction: column;
  gap: 0.25rem;
}

.nine-point__control {
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 0.375rem;
  cursor: pointer;
  display: inline-block;
  outline: none;
}

.nine-point__control:focus-visible {
  box-shadow: 0 0 0 2px var(--c-primary-400, #818cf8);
}

.nine-point__svg {
  width: 100%;
  height: 100%;
  display: block;
}

.nine-point__bg {
  fill: #f9fafb;
  stroke: #d1d5db;
  stroke-width: 1;
}

.nine-point__grid-line {
  stroke: #e5e7eb;
  stroke-width: 0.75;
}

.nine-point__cell {
  cursor: pointer;
}

.nine-point__hit {
  fill: transparent;
}

.nine-point__dot {
  fill: #9ca3af;
  transition: fill 0.1s ease, r 0.1s ease;
}

.nine-point__cell:hover .nine-point__dot {
  fill: #6b7280;
}

.nine-point__cell--selected .nine-point__dot {
  fill: var(--c-primary-600, #4f46e5);
}

.nine-point--disabled {
  opacity: 0.5;
  pointer-events: none;
}
</style>
