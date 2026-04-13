<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

defineProps<{
  visible: boolean
  widgetLabel: string
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    emit('cancel')
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="visible"
        class="confirm-reset-overlay"
        @click.self="emit('cancel')"
      >
        <div class="confirm-reset-modal">
          <p class="confirm-reset-modal__message">
            Reset all settings on <strong>{{ widgetLabel }}</strong> to their defaults? This clears every override you have made on this widget.
          </p>
          <div class="confirm-reset-modal__actions">
            <button
              type="button"
              class="confirm-reset-modal__btn confirm-reset-modal__btn--cancel"
              @click="emit('cancel')"
            >Cancel</button>
            <button
              type="button"
              class="confirm-reset-modal__btn confirm-reset-modal__btn--confirm"
              @click="emit('confirm')"
            >Reset</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.confirm-reset-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
}

.confirm-reset-modal {
  width: 100%;
  max-width: 24rem;
  border-radius: 0.75rem;
  background: #fff;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.confirm-reset-modal__message {
  margin: 0 0 1.25rem;
  font-size: 0.875rem;
  color: #374151;
  line-height: 1.5;
}

.confirm-reset-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.confirm-reset-modal__btn {
  border: none;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.confirm-reset-modal__btn--cancel {
  background: none;
  color: #6b7280;
}

.confirm-reset-modal__btn--cancel:hover {
  background: #f3f4f6;
}

.confirm-reset-modal__btn--confirm {
  background: var(--c-primary-600, #4f46e5);
  color: #fff;
}

.confirm-reset-modal__btn--confirm:hover {
  background: var(--c-primary-500, #6366f1);
}

/* Transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
