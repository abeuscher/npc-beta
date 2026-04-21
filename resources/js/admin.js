import helpSearch from './admin/help-search.js';
import buttonPreview from './admin/button-preview.js';
import fullscreenToggle from './admin/fullscreen-toggle.js';
import widgetPickerModal from './admin/widget-picker-modal.js';
import permissionTable from './admin/permission-table.js';
import quillEditor from './admin/quill-editor.js';
import customSelect from './admin/custom-select.js';
import './admin/sidebar-expand-scroll.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('helpSearch', helpSearch);
    window.Alpine.data('buttonPreview', buttonPreview);
    window.Alpine.data('fullscreenToggle', fullscreenToggle);
    window.Alpine.data('widgetPickerModal', widgetPickerModal);
    window.Alpine.data('permissionTable', permissionTable);
    window.Alpine.data('quillEditor', quillEditor);
    window.Alpine.data('customSelect', customSelect);
});
