import { previewManager } from './preview-manager'
import { spacingControls } from './spacing-controls'
import { buttonListManager } from './button-list-manager'
import { richtextEditor } from './richtext-editor'

function register(Alpine) {
    Alpine.data('previewManager', previewManager)
    Alpine.data('spacingControls', spacingControls)
    Alpine.data('buttonListManager', buttonListManager)
    Alpine.data('richtextEditor', richtextEditor)
}

// If Alpine is already available (loaded before this module), register immediately.
// Otherwise, wait for the alpine:init event.
if (window.Alpine) {
    register(window.Alpine)
} else {
    document.addEventListener('alpine:init', () => register(window.Alpine))
}
