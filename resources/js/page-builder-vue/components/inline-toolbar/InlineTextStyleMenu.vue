<script setup lang="ts">
// Session 347 — presentational text-style (heading) menu (§F1), split out of
// InlineFormatToolbar.vue. Pure view: the orchestrator owns format dispatch and
// passes the current header level + theme fonts; this emits the chosen level.
// The shared popover frame (position, data-attr, keydown) arrives as
// fall-through attributes from the orchestrator.
import { Check } from 'lucide-vue-next'

defineProps<{
  header: number | 'mixed' | null
  bodyFamily: string
  headingFamily: string
}>()
defineEmits<{ apply: [level: number | false] }>()
</script>

<template>
  <div class="ift-popover ift-textstyle-menu" role="menu">
    <button
      type="button"
      class="ift-textstyle-menu__row"
      role="menuitem"
      :style="{ fontFamily: bodyFamily }"
      @mousedown.prevent
      @click="$emit('apply', false)"
    >
      <span>Paragraph</span>
      <Check v-if="header === null" :size="14" class="ift-textstyle-menu__check" />
    </button>
    <button
      v-for="n in [1, 2, 3, 4, 5, 6]"
      :key="n"
      type="button"
      class="ift-textstyle-menu__row"
      role="menuitem"
      :style="{ fontFamily: headingFamily, fontWeight: 700, fontSize: Math.min(22, 14 + (7 - n) * 1.5) + 'px' }"
      @mousedown.prevent
      @click="$emit('apply', n)"
    >
      <span>Heading {{ n }}</span>
      <Check v-if="header === n" :size="14" class="ift-textstyle-menu__check" />
    </button>
  </div>
</template>

<style>
.ift-textstyle-menu { padding: 4px; min-width: 120px; max-width: 280px; }
.ift-textstyle-menu__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 28px;
  padding: 4px 12px;
  background: transparent;
  border: 0;
  border-radius: 4px;
  color: #e5e7eb;
  text-align: left;
  cursor: pointer;
}
.ift-textstyle-menu__row:hover { background: #374151; }
.ift-textstyle-menu__check { color: #818cf8; }
</style>
