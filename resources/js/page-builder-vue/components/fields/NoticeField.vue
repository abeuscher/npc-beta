<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef } from '../../types'

const props = defineProps<{
  field: FieldDef
}>()

const isWarning = computed(() => props.field.variant === 'warning')
</script>

<template>
  <div
    class="inspector-notice"
    :class="isWarning ? 'inspector-notice--warning' : 'inspector-notice--info'"
  >
    <svg
      v-if="isWarning"
      xmlns="http://www.w3.org/2000/svg"
      class="inspector-notice__icon"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <svg
      v-else
      xmlns="http://www.w3.org/2000/svg"
      class="inspector-notice__icon"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div class="inspector-notice__content" v-html="field.content"></div>
  </div>
</template>

<style scoped>
.inspector-notice {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  line-height: 1.4;
}

.inspector-notice--info {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.inspector-notice--warning {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

.inspector-notice__icon {
  flex-shrink: 0;
  width: 1rem;
  height: 1rem;
  margin-top: 0.125rem;
}

.inspector-notice__content {
  flex: 1;
}

.inspector-notice__content :deep(a) {
  text-decoration: underline;
}
</style>
