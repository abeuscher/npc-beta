<script setup lang="ts">
defineProps<{
  activeTab: 'content' | 'appearance'
}>()

const emit = defineEmits<{
  'update:activeTab': [value: 'content' | 'appearance']
}>()
</script>

<template>
  <div class="inspector-tabs">
    <button
      type="button"
      class="inspector-tabs__btn"
      :class="{ 'inspector-tabs__btn--active': activeTab === 'content' }"
      @click="emit('update:activeTab', 'content')"
    >
      Content
    </button>
    <button
      type="button"
      class="inspector-tabs__btn"
      :class="{ 'inspector-tabs__btn--active': activeTab === 'appearance' }"
      @click="emit('update:activeTab', 'appearance')"
    >
      Appearance
    </button>
  </div>
</template>

<style scoped>
.inspector-tabs {
  display: flex;
  border-left: 1px solid #e5e7eb;
  border-right: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0.25rem 0 0 0.25rem;
}

.inspector-tabs__btn {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem 0.375rem 0 0;
  margin-bottom: -1px;
  padding: 0.5rem 1rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #374151;
  background: transparent;
  opacity: 0.6;
  cursor: pointer;
  transition: opacity 0.15s, background-color 0.15s;
  margin-left: 0.25rem;
}

.inspector-tabs__btn:hover {
  opacity: 0.8;
}

.inspector-tabs__btn--active {
  background: #fff;
  opacity: 1;
  border-bottom-color: #fff;
}
</style>
