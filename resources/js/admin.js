// Quill v2 — self-hosted (session 370, Security S1). Vendored via npm (pinned in
// package.json) and exposed as the global window.Quill that both the admin Quill
// editor (./admin/quill-editor.js) and the page-builder inline richtext field
// consume. Replaces the former cdn.jsdelivr.net <script> render hook: a CDN
// compromise ran script inside authenticated PII sessions, and the external
// origin blocked any strict admin script-src.
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
window.Quill = Quill;

import helpSearch from './admin/help-search.js';
import buttonPreview from './admin/button-preview.js';
import fullscreenToggle from './admin/fullscreen-toggle.js';
import widgetPickerModal from './admin/widget-picker-modal.js';
import permissionTable from './admin/permission-table.js';
import quillEditor from './admin/quill-editor.js';
import customSelect from './admin/custom-select.js';
import './admin/sidebar-expand-scroll.js';
import { initTour } from './admin/tour/index.js';
import { registerWidgetComponents } from './shared/widget-components.js';

initTour();

// The page-builder preview renders the same widget templates the public site
// does, against Filament's Alpine. Since session 375 those templates reference
// their components by registered name, so the admin panel needs the same
// registrations the public bundle performs — the widget bundle itself already
// loads here (AdminPanelProvider render hook).
registerWidgetComponents();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('helpSearch', helpSearch);
    window.Alpine.data('buttonPreview', buttonPreview);
    window.Alpine.data('fullscreenToggle', fullscreenToggle);
    window.Alpine.data('widgetPickerModal', widgetPickerModal);
    window.Alpine.data('permissionTable', permissionTable);
    window.Alpine.data('quillEditor', quillEditor);
    window.Alpine.data('customSelect', customSelect);
});
