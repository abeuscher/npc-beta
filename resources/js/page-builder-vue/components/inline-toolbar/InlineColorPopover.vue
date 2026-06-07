<script setup lang="ts">
// Session 347 — presentational color / highlight popover (§H), split out of
// InlineFormatToolbar.vue. Wraps the ColorPicker primitive in panel-only mode
// and emits the picked hex; the orchestrator owns which target (text colour vs
// highlight) is active and applies it to Quill. Frame attrs fall through.
import ColorPicker from '../primitives/ColorPicker.vue'

defineEmits<{ pick: [hex: string] }>()
</script>

<template>
  <div class="ift-popover ift-color-popover">
    <ColorPicker :model-value="''" panel-only @update:model-value="$emit('pick', $event)" />
  </div>
</template>

<style>
.ift-color-popover { padding: 8px; }
/* ColorPicker primitive paints itself; toolbar provides the dark frame. */
.ift-color-popover .color-picker__popover { background: transparent; border: 0; padding: 0; box-shadow: none; }
</style>
