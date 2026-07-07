<script setup lang="ts">
// Session 347 — presentational link popover (§G), split out of
// InlineFormatToolbar.vue. All state + logic lives in useInlineLinkPopover; this
// is the view bound to that controller (destructured for natural template
// ergonomics — the destructured refs stay reactive). The orchestrator
// instantiates the controller once and passes it in; the shared popover frame
// (position, data-attr, keydown) falls through from the orchestrator.
import type { useInlineLinkPopover } from '../../composables/useInlineLinkPopover'

const props = defineProps<{ ctl: ReturnType<typeof useInlineLinkPopover> }>()
const {
  linkState, linkUrlInput, pagePickerOpen, pageQuery, pageHighlight, filteredPages,
  saveLink, removeLink, cancelLinkPopover, pickPage, onUrlInput,
  urlLooksLikeEmail, applyMailto,
} = props.ctl
</script>

<template>
  <div class="ift-popover ift-link-popover">
    <label class="ift-link-label">URL</label>
    <input
      ref="linkUrlInput"
      v-model="linkState.url"
      type="text"
      class="ift-link-input"
      placeholder="https://example.com"
      @input="onUrlInput"
      @keydown.enter.prevent="saveLink"
    />

    <button
      v-if="urlLooksLikeEmail"
      type="button"
      class="ift-link-mailto"
      @mousedown.prevent
      @click="applyMailto"
    >This looks like an email address — make it a mailto: link</button>

    <label class="ift-link-label">Or pick a page</label>
    <div class="ift-link-picker">
      <input
        v-model="pageQuery"
        type="text"
        class="ift-link-input"
        placeholder="Search site pages…"
        @focus="pagePickerOpen = true"
        @input="pagePickerOpen = true"
        @keydown.down.prevent="pageHighlight = Math.min(filteredPages.length - 1, pageHighlight + 1)"
        @keydown.up.prevent="pageHighlight = Math.max(0, pageHighlight - 1)"
        @keydown.enter.prevent="(() => { const p = filteredPages[pageHighlight]; if (p) pickPage(p.slug, p.url || '') })()"
      />
      <ul v-if="pagePickerOpen && filteredPages.length" class="ift-link-picker__list">
        <li
          v-for="(p, i) in filteredPages.slice(0, 12)"
          :key="p.slug"
          class="ift-link-picker__row"
          :class="{ 'ift-link-picker__row--active': i === pageHighlight }"
          @mousedown.prevent
          @click="pickPage(p.slug, p.url || '')"
        >
          <span class="ift-link-picker__title">{{ p.title }}</span>
          <span class="ift-link-picker__url">{{ p.url }}</span>
        </li>
      </ul>
    </div>

    <label class="ift-link-label">Link text</label>
    <input
      v-model="linkState.linkText"
      type="text"
      class="ift-link-input"
      @keydown.enter.prevent="saveLink"
    />

    <label class="ift-link-check">
      <input v-model="linkState.openInNewTab" type="checkbox" />
      <span>Open in new tab</span>
    </label>

    <div class="ift-link-actions">
      <button
        v-if="linkState.mode === 'edit'"
        type="button"
        class="ift-link-btn ift-link-btn--remove"
        @mousedown.prevent
        @click="removeLink"
      >Remove</button>
      <div class="ift-link-actions__right">
        <button
          type="button"
          class="ift-link-btn"
          @mousedown.prevent
          @click="cancelLinkPopover"
        >Cancel</button>
        <button
          type="button"
          class="ift-link-btn ift-link-btn--primary"
          :disabled="!linkState.url.trim()"
          @mousedown.prevent
          @click="saveLink"
        >Save</button>
      </div>
    </div>
  </div>
</template>

<style>
.ift-link-popover { padding: 12px; }
.ift-link-label {
  display: block;
  margin: 4px 0 4px;
  font-size: 11px;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.ift-link-input {
  width: 100%;
  height: 32px;
  padding: 0 8px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  color: #e5e7eb;
  font: 13px/1 'Inter', system-ui, sans-serif;
  box-sizing: border-box;
}
.ift-link-input:focus { border-color: #818cf8; outline: none; }

.ift-link-mailto {
  display: block;
  width: 100%;
  margin-top: 4px;
  padding: 4px 8px;
  background: transparent;
  border: 1px dashed #4b5563;
  border-radius: 6px;
  color: #a5b4fc;
  font: 12px/1.3 'Inter', system-ui, sans-serif;
  text-align: left;
  cursor: pointer;
}
.ift-link-mailto:hover { background: #1f2937; border-color: #818cf8; }

.ift-link-picker { position: relative; }
.ift-link-picker__list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 180px;
  margin: 4px 0 0;
  padding: 4px 0;
  list-style: none;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  overflow: auto;
  z-index: 2;
}
.ift-link-picker__row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 10px;
  cursor: pointer;
}
.ift-link-picker__row:hover,
.ift-link-picker__row--active { background: #374151; }
.ift-link-picker__title { color: #e5e7eb; font-size: 13px; }
.ift-link-picker__url { color: #9ca3af; font-size: 11px; }

.ift-link-check {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 10px 0 0;
  color: #e5e7eb;
  font-size: 13px;
}
.ift-link-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  gap: 8px;
}
.ift-link-actions__right { display: flex; gap: 8px; margin-left: auto; }
.ift-link-btn {
  height: 30px;
  padding: 0 12px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  color: #e5e7eb;
  font: 13px/1 'Inter', system-ui, sans-serif;
  cursor: pointer;
}
.ift-link-btn:hover { background: #374151; }
.ift-link-btn:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }
.ift-link-btn:disabled { opacity: 0.4; cursor: default; }
.ift-link-btn--primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.ift-link-btn--primary:hover { background: #4338ca; }
.ift-link-btn--remove { background: transparent; border-color: transparent; color: #f87171; }
.ift-link-btn--remove:hover { background: rgba(220, 38, 38, 0.18); }
</style>
