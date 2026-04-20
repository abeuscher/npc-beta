<script setup lang="ts" generic="T extends string">
interface TabDef {
  id: T
  label: string
}

defineProps<{
  tabs: TabDef[]
  activeTab: T
}>()

const emit = defineEmits<{
  'update:activeTab': [value: T]
}>()
</script>

<template>
  <div class="inspector-tabs">
    <div class="inspector-tabs__list">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="inspector-tabs__btn"
        :class="{ 'inspector-tabs__btn--active': activeTab === tab.id }"
        :title="tab.label"
        @click="emit('update:activeTab', tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>
    <div v-if="$slots.toolbar" class="inspector-tabs__toolbar">
      <slot name="toolbar" />
    </div>
  </div>
</template>

<style scoped>
.inspector-tabs {
  display: flex;
  align-items: flex-end;
  border-left: 1px solid var(--np-control-border);
  border-right: 1px solid var(--np-control-border);
  background: var(--np-control-bar-bg);
  padding: 0.25rem 0.25rem 0 0.25rem;
}

.inspector-tabs__list {
  display: flex;
  flex: 1;
  min-width: 0;
}

.inspector-tabs__btn {
  position: relative;
  flex: 0 1 auto;
  min-width: 0;
  border: 1px solid var(--np-control-border);
  border-radius: 0.375rem 0.375rem 0 0;
  margin-bottom: -1px;
  padding: 0.375rem 0.625rem;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  color: var(--np-control-chip-text);
  background: transparent;
  opacity: 0.6;
  cursor: pointer;
  transition: var(--np-control-transition), opacity 0.15s;
  margin-left: 0.25rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.inspector-tabs__btn:hover {
  opacity: 0.8;
}

.inspector-tabs__btn--active {
  background: var(--np-control-chip-bg);
  color: var(--np-control-chip-text-active);
  opacity: 1;
  border-bottom-color: var(--np-control-chip-bg);
}

.inspector-tabs__toolbar {
  display: flex;
  align-items: center;
  padding: 0 0.25rem 0.25rem;
}
</style>
