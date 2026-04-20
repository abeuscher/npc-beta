<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

const props = defineProps<{
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
        class="confirm-delete-overlay"
        @click.self="emit('cancel')"
      >
        <div class="confirm-delete-modal">
          <p class="confirm-delete-modal__message">
            Are you sure you want to delete this widget: <strong>{{ widgetLabel }}</strong>?
          </p>
          <div class="confirm-delete-modal__actions">
            <button
              type="button"
              class="confirm-delete-modal__btn confirm-delete-modal__btn--cancel"
              @click="emit('cancel')"
            >Cancel</button>
            <button
              type="button"
              class="confirm-delete-modal__btn confirm-delete-modal__btn--delete"
              @click="emit('confirm')"
            >Delete</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.confirm-delete-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
}

.confirm-delete-modal {
  width: 100%;
  max-width: 24rem;
  border-radius: 0.75rem;
  background: #fff;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.confirm-delete-modal__message {
  margin: 0 0 1.25rem;
  font-size: 0.875rem;
  color: #374151;
  line-height: 1.5;
}

.confirm-delete-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.confirm-delete-modal__btn {
  border: none;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.confirm-delete-modal__btn--cancel {
  background: none;
  color: #6b7280;
}

.confirm-delete-modal__btn--cancel:hover {
  background: #f3f4f6;
}

.confirm-delete-modal__btn--delete {
  background: #dc2626;
  color: #fff;
}

.confirm-delete-modal__btn--delete:hover {
  background: #b91c1c;
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

html.dark .confirm-delete-overlay          { background: rgba(0, 0, 0, 0.7); }
html.dark .confirm-delete-modal            { background: rgb(31 41 55); color: rgb(229 231 235); border-color: rgb(75 85 99); }
html.dark .confirm-delete-modal__message   { color: rgb(209 213 219); }
</style>
