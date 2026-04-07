import { previewManager } from './preview-manager'
import { spacingControls } from './spacing-controls'
import { buttonListManager } from './button-list-manager'
import { richtextEditor } from './richtext-editor'

document.addEventListener('alpine:init', () => {
    Alpine.data('previewManager', previewManager)
    Alpine.data('spacingControls', spacingControls)
    Alpine.data('buttonListManager', buttonListManager)
    Alpine.data('richtextEditor', richtextEditor)
})
