# Editor Migration Plan: Livewire → Vue 3

**Created:** 2026-04-07 (Session 146)
**Scope:** Sessions 147–151

This document is the architectural plan for migrating the page builder editor from Livewire/Alpine.js to Vue 3 with Pinia state management. It covers the current architecture inventory, API endpoint design, Vue component and store design, and a phased migration sequence.

---

## Part 1 — Architecture Audit

### 1.1 Current file inventory

#### Livewire components

| File | Role |
|------|------|
| `app/Livewire/PageBuilder.php` | Root orchestrator. Holds page ID, block list, widget types, mode, selected block, preview HTML. |
| `app/Livewire/PageBuilderBlock.php` | Per-block card (handles mode). Renders in the block list; manages column child slots. |
| `app/Livewire/PageBuilderInspector.php` | Inspector panel. Loads selected block, renders config/style/query fields, persists changes. |

#### Blade views

| File | Role |
|------|------|
| `resources/views/livewire/page-builder.blade.php` | Main layout: toolbar, edit mode (preview + inspector), handles mode (block list + inspector), add-block modal, save-as-template modal. |
| `resources/views/livewire/page-builder-block.blade.php` | Single block card: drag handle, inline label edit, ellipsis menu, column widget child slots. |
| `resources/views/livewire/page-builder-inspector.blade.php` | Inspector panel: label editor, Content/Appearance tabs, query settings, spacing controls, Apply Changes button. |
| `resources/views/livewire/partials/inspector-field.blade.php` | Field dispatcher — includes the correct field-type partial based on `$field['type']`. |
| `resources/views/livewire/partials/inspector-field-group.blade.php` | Groups primary/advanced fields with layout grouping and collapsible "Carousel Settings" accordion. |
| `resources/views/livewire/partials/inspector-fields/*.blade.php` | 13 field-type partials: text, textarea, url, number, select, toggle, color, image, video, richtext, buttons, checkboxes, notice. |
| `resources/views/components/widget-picker-modal.blade.php` | Shared widget picker modal (used for both root and child add). Category filter, search, thumbnail grid. |

#### Alpine.js modules (`resources/js/page-builder/`)

| File | Role |
|------|------|
| `index.js` | Entry point — registers all four Alpine data components. |
| `preview-manager.js` | Manages the preview canvas: viewport zoom, library loading, Alpine re-init on preview content changes, height pinning during content swap. |
| `spacing-controls.js` | Computed "All" fields for padding/margin (getter/setter pattern over individual `style_config` values). |
| `richtext-editor.js` | Quill editor initialisation, inline image upload, two-way sync with Livewire `updateConfig`. |
| `button-list-manager.js` | Array manager for button lists (add/remove/reorder), persists via `$wire.updateConfig`. |

#### Services

| File | Role | Used by Vue? |
|------|------|-------------|
| `app/Services/WidgetRenderer.php` | Renders a PageWidget to `{html, styles, scripts}`. Server-rendered Blade templates. | Yes — called by preview API endpoint |
| `app/Services/WidgetDataResolver.php` | Resolves collection data for widget templates (custom, blog posts, events, products). | Yes — called via WidgetRenderer |
| `app/Services/DemoDataService.php` | Generates fake collection data for admin preview when no real data exists. | Yes — called via WidgetRenderer |
| `app/Services/PageContext.php` | Page-scoped data accessor (posts, pages, events, collections, products, forms). | Yes — passed to Blade templates during render |
| `app/Services/PageBuilderDataSources.php` | Resolves named data sources to `[value => label]` arrays for inspector select dropdowns. | Yes — called by lookup API endpoint |

#### Filament page shell

| File | Role |
|------|------|
| `app/Filament/Resources/PageResource/Pages/EditPage.php` | Filament EditRecord page. Renders page settings form. Header actions: public URL link, delete, save-as-template, snippets editor. Mounts `<livewire:page-builder>` in the form layout. |

---

### 1.2 State held by each component

#### PageBuilder.php (root)

| Property | Type | Description |
|----------|------|-------------|
| `$pageId` | string | UUID of the page being edited |
| `$blocks` | array | Root-level widget data (id, type info, config, style_config, query_config, sort_order, is_required) |
| `$widgetTypes` | array | Available widget types for the picker (filtered by page type) |
| `$requiredHandles` | array | Widget handles that cannot be deleted from this page |
| `$pageType` | string | Page type (default, system, member, post, event) |
| `$selectedBlockId` | string | Currently selected widget UUID |
| `$mode` | string | Editor mode: 'edit' or 'handles' |
| `$previewBlocks` | array | Rendered preview HTML for each root widget |
| `$requiredLibs` | array | JS library identifiers needed by widgets on this page |
| `$showAddModal` | bool | Widget picker modal visibility |
| `$insertPosition` | ?int | Insert position for new widget |
| `$addModalLabel` | string | Label input for new widget |
| `$showSaveTemplateModal` | bool | Save-as-template modal visibility |
| `$saveTemplateName` | string | Template name input |
| `$saveTemplateDescription` | string | Template description input |

#### PageBuilderBlock.php (per block)

| Property | Type | Description |
|----------|------|-------------|
| `$blockId` | string | Widget UUID |
| `$block` | array | Widget data (id, type info, config, sort_order) |
| `$pageType` | string | Page type |
| `$parentBlockId` | string | Parent column widget ID (empty for root blocks) |
| `$parentColumnIndex` | int | Column slot index within parent |
| `$parentNumColumns` | int | Total columns in parent |
| `$columnTargets` | array | Available column widgets for move-to actions |
| `$isFirst`, `$isLast` | bool | Position flags (reactive) |
| `$isRequired` | bool | Whether this widget handle is required |
| `$childSlots` | array | Column children: `[columnIndex => [child, ...]]` |
| `$widgetTypes` | array | Lazy-loaded widget types for child add picker |
| `$showChildAddModal` | bool | Child add modal visibility |
| `$childAddColumn` | ?int | Target column for child add |
| `$childAddLabel` | string | Label input for child add |

#### PageBuilderInspector.php

| Property | Type | Description |
|----------|------|-------------|
| `$blockId` | string | Currently inspected widget UUID |
| `$block` | array | Full widget data (id, page_id, type info, config, query_config, style_config) |
| `$cmsTags` | array | Collection tags for query settings |
| `$selectOptions` | array | Resolved options for select fields, keyed by field key |
| `$imageUploads` | array | Temporary file upload objects |
| `$imageUrls` | array | Current image URLs for preview |

---

### 1.3 Server round-trips

#### PageBuilder.php

| Method | Trigger | DB operations | Events dispatched |
|--------|---------|---------------|-------------------|
| `mount()` | Initial page load | Load page, widget types, all root widgets, render all previews | — |
| `createBlock()` | Widget picker selection | Create PageWidget, shift sort orders, reload all blocks, re-render all previews | `block-selected` |
| `deleteBlock()` | Delete confirm | Delete PageWidget, reload blocks, re-render previews | `block-selected` (deselect) |
| `copyBlock()` | Menu action | Create PageWidget copy + recursive children, reload, re-render | — |
| `updateOrder()` | Drag-and-drop payload | Validate all IDs belong to page, update parent/column/sort for each item | `blocks-reordered` |
| `moveUp()` / `moveDown()` | Menu actions | Update sort_order for two blocks | — |
| `onMoveToMain()` | Menu action | Update parent_widget_id/column_index/sort_order | `blocks-reordered` |
| `onMoveToColumn()` | Menu action | Update parent_widget_id/column_index/sort_order | `blocks-reordered` |
| `selectBlock()` | Block click | Verify block exists on page | `block-selected` (browser) |
| `switchToEdit()` / `switchToHandles()` | Mode toggle buttons | None | — |
| `openAddModal()` | "+ Add Block" button | None | — |
| `openSaveTemplateModal()` | "Save as Template" button | None | — |
| `saveAsTemplate()` | Template modal submit | Serialize widget stack, create Template | — |
| `refreshAllPreviews()` | Called internally after most mutations | Load all active root widgets with children, render each via WidgetRenderer | `preview-content-changed` (browser) |

#### PageBuilderBlock.php

| Method | Trigger | DB operations | Events dispatched |
|--------|---------|---------------|-------------------|
| `mount()` | Livewire render | Load block + widgetType, load child slots if column | — |
| `openChildAddModal()` | "+ Add Block" in column | Lazy-load widget types | — |
| `createChildBlock()` | Child widget picker select | Create PageWidget as child, reload child slots | — |
| `onChildDelete()` | Child delete confirm | Delete PageWidget, reload child slots | — |
| `onChildMoveUp()` / `onChildMoveDown()` | Child menu actions | Swap sort_order on two siblings | — |
| `onChildMoveToColumn()` | Child menu action | Update column_index/sort_order | `preview-refresh-requested` |
| `requestDelete()` | Menu action | None (dispatches to parent) | `block-delete-requested` or `child-delete-requested` |
| `requestCopy()` | Menu action | None | `block-copy-requested` |
| `requestMoveUp()` / `requestMoveDown()` | Menu actions | None | `block-move-up/down-requested` or `child-move-up/down-requested` |
| `requestMoveToMainList()` | Menu action | None | `block-move-to-main-requested` |
| `requestMoveToColumnWidget()` | Menu action | None | `block-move-to-column-requested` |
| `selectSelf()` | Label click | None | `block-select-requested` |
| `updateInlineConfig()` | Contenteditable blur | Update PageWidget config | `inline-config-updated` |
| `onWidgetConfigUpdated()` | Inspector save | Reload block, relocate excess children if column count changed | — |
| `onBlocksReordered()` | Parent reorder | Reload child slots if column widget | — |

#### PageBuilderInspector.php

| Method | Trigger | DB operations | Events dispatched |
|--------|---------|---------------|-------------------|
| `mount()` | Livewire render (re-created on selection change) | Load block, tags, select options, image URLs | — |
| `updated()` | Any `wire:model` change | Update config/query_config/style_config/label on PageWidget | `widget-config-updated` |
| `updateConfig()` | Richtext editor save | Update single config key | `widget-config-updated` |
| `toggleCheckbox()` | Checkbox toggle | Update config array | `widget-config-updated` |
| `updatedImageUploads()` | File input change | Upload media, update config | `widget-config-updated` |
| `removeImage()` | Remove button click | Clear media collection, update config | `widget-config-updated` |
| `applyChanges()` | "Apply Changes" button | None | `preview-refresh-requested` |

---

### 1.4 What stays vs. what moves to Vue

| Component | Disposition | Rationale |
|-----------|-------------|-----------|
| **Filament EditPage shell** | **Stays** (Filament/Livewire) | Page settings form, header actions, breadcrumbs, sidebar — all Filament infrastructure |
| **PageBuilder.php** | **Becomes thin mount-point** | Passes bootstrap JSON to the Vue app div; retains `$pageId` and page-level data for initial render |
| **PageBuilderBlock.php** | **Moves to Vue** → `BlockCard.vue`, `BlockList.vue` | Block card UI, column child slots, drag handles, ellipsis menu — all become Vue components |
| **PageBuilderInspector.php** | **Moves to Vue** → `InspectorPanel.vue` + field components | Config/style/query editing — becomes Vue form components with Pinia state + API saves |
| **page-builder.blade.php** | **Becomes thin shell** | Renders `<div id="page-builder-app">` with bootstrap data as JSON attribute |
| **page-builder-block.blade.php** | **Removed** (session 151) | Replaced by Vue block components |
| **page-builder-inspector.blade.php** | **Removed** (session 151) | Replaced by Vue inspector components |
| **inspector-field*.blade.php** (all partials) | **Removed** (session 151) | Replaced by Vue field components |
| **Widget picker modal** | **Stays** (Blade component) | Opens via browser event from Vue, returns selection via browser event |
| **Save-as-template modal** | **Stays** (Livewire on PageBuilder.php) | Opens via browser event from Vue |
| **preview-manager.js** | **Moves to Vue** → `PreviewCanvas.vue` | Viewport zoom, library loading, Alpine re-init become Vue component methods |
| **spacing-controls.js** | **Moves to Vue** → `SpacingControl.vue` | Computed "All" fields become Vue computed properties |
| **richtext-editor.js** | **Moves to Vue** → `RichTextField.vue` | Quill initialisation moves to a Vue component with onMounted |
| **button-list-manager.js** | **Moves to Vue** → `ButtonListField.vue` | Array manager becomes Vue reactive state |
| **index.js** (Alpine registration) | **Removed** (session 151) | No longer needed once Alpine modules are gone |
| **WidgetRenderer.php** | **Stays** (server) | Called by the preview API endpoint — no change |
| **WidgetDataResolver.php** | **Stays** (server) | Called via WidgetRenderer — no change |
| **DemoDataService.php** | **Stays** (server) | Called via WidgetRenderer — no change |
| **PageContext.php** | **Stays** (server) | Injected into Blade templates during render — no change |
| **PageBuilderDataSources.php** | **Stays** (server) | Called by the lookup API endpoint — no change |

---

## Part 2 — API Endpoint Design

All endpoints live under Filament's admin panel middleware (session auth + CSRF). The route prefix is `/admin/api/page-builder`.

### 2.1 Widget CRUD

#### `GET /admin/api/page-builder/{page}/widgets`

Load the full widget tree for a page.

**Response:**

```json
{
  "widgets": [
    {
      "id": "uuid",
      "widget_type_id": "uuid",
      "widget_type_handle": "hero",
      "widget_type_label": "Hero",
      "widget_type_collections": [],
      "widget_type_config_schema": [...],
      "widget_type_assets": {},
      "widget_type_default_open": false,
      "parent_widget_id": null,
      "column_index": null,
      "label": "Hero 1",
      "config": {},
      "query_config": {},
      "style_config": {},
      "sort_order": 0,
      "is_active": true,
      "is_required": false,
      "preview_html": "<div class='widget widget--hero'>...</div>",
      "children": [...]
    }
  ],
  "required_libs": ["swiper"]
}
```

**Notes:**
- Children are nested within their parent's `children` array, grouped by `column_index`.
- `preview_html` is the rendered HTML for each root widget (same output as `renderWidgetForPreview`).
- `is_required` is computed from `WidgetType::requiredForPage()`.

---

#### `POST /admin/api/page-builder/{page}/widgets`

Create a new widget on the page.

**Request:**

```json
{
  "widget_type_id": "uuid",
  "label": "My Hero",
  "parent_widget_id": null,
  "column_index": null,
  "insert_position": 2
}
```

**Response:**

```json
{
  "widget": { ...full widget object with preview_html... },
  "tree": [ ...updated full tree... ],
  "required_libs": [...]
}
```

**Notes:**
- `label` is optional — server auto-generates if blank (same logic as `createBlock`).
- `insert_position` is optional — defaults to end of list.
- If `parent_widget_id` is set, the widget is created as a column child.
- Response includes the full updated tree so the client can replace its store in one operation.

---

#### `PUT /admin/api/page-builder/widgets/{widget}`

Update a widget's config, style_config, query_config, or label.

**Request:**

```json
{
  "label": "Updated Label",
  "config": { "heading": "New Heading" },
  "style_config": { "padding_top": 20 },
  "query_config": { "events": { "limit": 5 } }
}
```

**Response:**

```json
{
  "widget": { ...updated widget object (no preview_html)... }
}
```

**Notes:**
- All fields are optional — only provided fields are updated.
- Does NOT return preview HTML — the client marks the widget as dirty and requests preview separately.
- The `config` value is a full replacement (not a merge), matching current Livewire behaviour.

---

#### `DELETE /admin/api/page-builder/widgets/{widget}`

Delete a widget and its children.

**Response:**

```json
{
  "deleted": true,
  "tree": [ ...updated full tree... ],
  "required_libs": [...]
}
```

**Notes:**
- Returns 403 if the widget's handle is in the required list.
- Cascade-deletes children (column widget contents).
- Returns updated tree so client can replace store.

---

#### `POST /admin/api/page-builder/widgets/{widget}/copy`

Duplicate a widget and its children.

**Response:**

```json
{
  "widget": { ...new widget with preview_html... },
  "tree": [ ...updated full tree... ],
  "required_libs": [...]
}
```

---

#### `PUT /admin/api/page-builder/{page}/widgets/reorder`

Batch reorder widgets.

**Request:**

```json
{
  "items": [
    { "id": "uuid", "parent_widget_id": null, "column_index": null, "sort_order": 0 },
    { "id": "uuid", "parent_widget_id": "uuid", "column_index": 1, "sort_order": 0 }
  ]
}
```

**Response:**

```json
{
  "tree": [ ...updated full tree... ],
  "required_libs": [...]
}
```

**Notes:**
- Same validation as current `updateOrder`: all IDs must belong to the page, column widgets cannot be nested.

---

### 2.2 Preview rendering

#### `GET /admin/api/page-builder/widgets/{widget}/preview`

Render a single widget's preview HTML.

**Response:**

```json
{
  "id": "uuid",
  "html": "<div class='widget widget--hero'>...</div>",
  "required_libs": ["swiper"]
}
```

**Notes:**
- Called when the user selects a widget, switches modes, or clicks "Apply Changes".
- For column widgets, renders the full column including children.
- Uses the same `renderWidgetForPreview` logic.

---

### 2.3 Lookup endpoints (read-only reference data)

These endpoints provide data for inspector dropdowns. They are called once on editor mount and cached in the Pinia store.

#### `GET /admin/api/page-builder/widget-types?page_type={type}`

Widget type registry for the picker.

**Response:**

```json
{
  "widget_types": [
    {
      "id": "uuid",
      "handle": "hero",
      "label": "Hero",
      "description": "Full-width hero banner",
      "category": ["content"],
      "config_schema": [...],
      "collections": [],
      "assets": {},
      "full_width": true,
      "default_open": false,
      "thumbnail": "/path/to/thumb.png",
      "thumbnail_hover": "/path/to/thumb-hover.png"
    }
  ]
}
```

---

#### `GET /admin/api/page-builder/collections`

**Response:**

```json
{
  "collections": [
    { "handle": "team", "name": "Team Members", "source_type": "custom" }
  ]
}
```

---

#### `GET /admin/api/page-builder/collections/{handle}/fields`

**Response:**

```json
{
  "fields": [
    { "key": "name", "label": "Name", "type": "text" },
    { "key": "photo", "label": "Photo", "type": "image" }
  ]
}
```

---

#### `GET /admin/api/page-builder/tags`

**Response:**

```json
{
  "tags": [
    { "id": "uuid", "name": "Featured", "slug": "featured" }
  ]
}
```

---

#### `GET /admin/api/page-builder/pages`

**Response:**

```json
{
  "pages": [
    { "slug": "about", "title": "About Us" }
  ]
}
```

---

#### `GET /admin/api/page-builder/events`

**Response:**

```json
{
  "events": [
    { "slug": "annual-gala", "title": "Annual Gala" }
  ]
}
```

---

#### `GET /admin/api/page-builder/data-sources/{source}`

Resolves a named data source to options. Wraps `PageBuilderDataSources::resolve()`.

**Response:**

```json
{
  "options": { "annual-gala": "Annual Gala", "spring-fling": "Spring Fling" }
}
```

---

### 2.4 Image upload

#### `POST /admin/api/page-builder/widgets/{widget}/image`

Upload an image for a config field.

**Request:** `multipart/form-data` with fields `key` (config field key) and `file`.

**Response:**

```json
{
  "media_id": 42,
  "url": "/storage/123/image.webp"
}
```

#### `DELETE /admin/api/page-builder/widgets/{widget}/image/{key}`

Remove an image from a config field.

**Response:**

```json
{
  "removed": true
}
```

---

### 2.5 Auth & permissions

- All endpoints use Filament's admin panel middleware stack (web session + CSRF).
- Read endpoints (`GET`) require `view_page` permission.
- Write endpoints (`POST`, `PUT`, `DELETE`) require `update_page` permission (matching the existing `assertCanEdit()` pattern).
- Widget operations scope to the page via `page_id` FK — a user cannot manipulate widgets belonging to a page they don't have access to.

---

## Part 3 — Vue Application Structure

### 3.1 Pinia store: `useEditorStore`

```typescript
interface EditorState {
  // Core widget data
  pageId: string
  pageType: string
  widgets: Record<string, Widget>       // flat map keyed by widget ID
  rootOrder: string[]                    // ordered root widget IDs
  selectedBlockId: string | null
  editorMode: 'edit' | 'handles'

  // Preview state
  dirtyWidgets: Set<string>             // widget IDs needing preview refresh
  requiredLibs: string[]

  // Widget type registry
  widgetTypes: WidgetType[]
  requiredHandles: string[]

  // Lookup data (loaded once on mount)
  collections: Collection[]
  tags: Tag[]
  pages: PageRef[]
  events: EventRef[]

  // UI state
  saving: boolean
}

interface Widget {
  id: string
  widget_type_id: string
  widget_type_handle: string
  widget_type_label: string
  widget_type_collections: string[]
  widget_type_config_schema: FieldDef[]
  widget_type_assets: Record<string, any>
  widget_type_default_open: boolean
  parent_widget_id: string | null
  column_index: number | null
  label: string
  config: Record<string, any>
  query_config: Record<string, any>
  style_config: Record<string, any>
  sort_order: number
  is_active: boolean
  is_required: boolean
  preview_html: string
  children: Record<number, Widget[]>    // column_index → ordered children
}
```

**Key actions:**

| Action | API call | Updates |
|--------|----------|---------|
| `loadTree(pageId)` | `GET widgets` | Populates `widgets`, `rootOrder`, `requiredLibs` |
| `createWidget(payload)` | `POST widgets` | Replaces full tree from response |
| `updateWidget(id, changes)` | `PUT widgets/{id}` | Updates widget in map, adds to `dirtyWidgets` |
| `deleteWidget(id)` | `DELETE widgets/{id}` | Replaces full tree from response |
| `copyWidget(id)` | `POST widgets/{id}/copy` | Replaces full tree from response |
| `reorderWidgets(items)` | `PUT widgets/reorder` | Replaces full tree from response |
| `refreshPreview(id)` | `GET widgets/{id}/preview` | Updates `preview_html`, removes from `dirtyWidgets` |
| `selectBlock(id)` | — (client-only) | Sets `selectedBlockId`, triggers `refreshPreview` if dirty |
| `setMode(mode)` | — (client-only) | Sets `editorMode` |
| `uploadImage(widgetId, key, file)` | `POST widgets/{id}/image` | Updates config with media_id, sets image URL |
| `removeImage(widgetId, key)` | `DELETE widgets/{id}/image/{key}` | Clears config field and URL |

**Key getters:**

| Getter | Returns |
|--------|---------|
| `rootWidgets` | Ordered array of root widget objects |
| `selectedWidget` | The currently selected widget object, or null |
| `childrenOf(parentId)` | Children grouped by column_index |
| `isWidgetDirty(id)` | Whether the widget's preview needs refresh |
| `columnTargets` | Root column widgets available for move-to actions |

---

### 3.2 Vue component tree

```
PageBuilderApp.vue                    ← root: initialises store, contains layout
├── EditorToolbar.vue                 ← block count, mode toggle, Save as Template, + Add Block
├── [mode === 'edit']
│   ├── PreviewCanvas.vue             ← left pane: viewport controls, zoom, scrollable preview
│   │   └── PreviewRegion.vue (×N)    ← single widget: overlay div, click-to-select, injected HTML
│   └── InspectorPanel.vue            ← right pane: sticky, scrollable
│       ├── InspectorHeader.vue       ← widget type badge, inline label editor
│       ├── InspectorTabs.vue         ← Content / Appearance tab bar
│       ├── [Content tab]
│       │   └── InspectorFieldGroup.vue
│       │       └── InspectorField.vue (×N)  ← dispatches to typed field components
│       ├── [Appearance tab]
│       │   ├── InspectorFieldGroup.vue
│       │   ├── QuerySettings.vue     ← collection query config (limit, order, tags)
│       │   └── SpacingControl.vue    ← padding/margin with "All" computed fields
│       └── ApplyChangesButton.vue    ← triggers preview refresh
├── [mode === 'handles']
│   ├── BlockList.vue                 ← drag-sortable list of root blocks
│   │   └── BlockCard.vue (×N)        ← drag handle, label, menu, column child slots
│   │       └── ColumnSlot.vue (×N)   ← per-column child list with nested BlockCards
│   └── InspectorPanel.vue            ← same inspector, shared between modes
└── (event bridge to Livewire modals)
```

**Field components** (rendered by `InspectorField.vue` based on field type):

| Component | Field type(s) |
|-----------|---------------|
| `TextField.vue` | text, url |
| `TextareaField.vue` | textarea |
| `NumberField.vue` | number |
| `SelectField.vue` | select |
| `ToggleField.vue` | toggle |
| `ColorPickerField.vue` | color |
| `ImageUploadField.vue` | image, video |
| `RichTextField.vue` | richtext (Quill) |
| `ButtonListField.vue` | buttons |
| `CheckboxesField.vue` | checkboxes |
| `NoticeField.vue` | notice (read-only display) |

---

### 3.3 Event bridge (Vue ↔ Livewire)

Communication between the Vue app and Livewire modals uses browser `CustomEvent`s.

**Vue dispatches:**

| Event | Payload | Handled by |
|-------|---------|------------|
| `open-widget-picker` | `{ parentWidgetId?, columnIndex?, insertPosition? }` | PageBuilder.php / PageBuilderBlock.php |
| `open-save-template-modal` | `{}` | PageBuilder.php |

**Livewire dispatches (Vue listens):**

| Event | Payload | Handled by |
|-------|---------|------------|
| `widget-created` | `{ widget, tree, required_libs }` | `useEditorStore` — replaces tree |
| `template-saved` | `{}` | — (notification only) |

**Implementation:** `window.dispatchEvent(new CustomEvent(...))` / `window.addEventListener(...)`. The Vue app registers listeners in `onMounted` of `PageBuilderApp.vue` and cleans them up in `onUnmounted`.

---

### 3.4 Bootstrap data

The Blade shell passes initial data as a JSON blob on the mount element:

```html
<div
    id="page-builder-app"
    data-bootstrap='@json($bootstrapData)'
></div>
```

**Bootstrap data shape:**

```json
{
  "page_id": "uuid",
  "page_type": "default",
  "widgets": [...],
  "required_libs": [...],
  "widget_types": [...],
  "required_handles": [...],
  "collections": [...],
  "tags": [...],
  "pages": [...],
  "events": [...],
  "csrf_token": "...",
  "api_base_url": "/admin/api/page-builder",
  "inline_image_upload_url": "/admin/api/page-builder/inline-image"
}
```

This eliminates the need for separate lookup API calls on initial load — all reference data is included in the bootstrap. The lookup endpoints exist for cases where data needs to be refreshed (e.g. after creating a new collection in another tab).

---

## Part 4 — Migration Sequence Plan

### Session 147: Editor API & Vue Scaffold

**Files created:**

- `app/Http/Controllers/Admin/PageBuilderApiController.php` — all API endpoints
- `routes/admin-api.php` — route definitions (included from `routes/web.php`)
- `resources/js/page-builder-vue/App.vue` — root Vue component (minimal)
- `resources/js/page-builder-vue/stores/editor.ts` — Pinia store
- `resources/js/page-builder-vue/api.ts` — API client (fetch wrapper with CSRF)
- `resources/js/page-builder-vue/main.ts` — Vue app entry point (createApp + createPinia)
- `resources/js/page-builder-vue/types.ts` — TypeScript interfaces

**Files modified:**

- `vite.config.js` — add Vue plugin, add page-builder-vue entry point
- `resources/views/livewire/page-builder.blade.php` — add Vue mount div alongside existing UI (both render, Vue hidden by default)
- `app/Livewire/PageBuilder.php` — add `$bootstrapData` property and `getBootstrapData()` method
- `package.json` — add `vue`, `pinia`, `@vitejs/plugin-vue` dependencies

**Files deleted:** None

**Acceptance criteria:**
- API endpoints return correct data for widget CRUD operations
- Vue app mounts inside the Filament page shell
- Pinia store loads widget tree from API and holds state
- Selecting a block in the Vue block list highlights it
- Saving a config change via the store persists to the database
- Existing Livewire editor continues to work (Vue app is opt-in via a flag or toggle)

**Still on Livewire:** Everything — the Vue app is a parallel proof-of-concept. Both systems are functional.

---

### Session 148: Editor Canvas in Vue

**Files created:**

- `resources/js/page-builder-vue/components/PreviewCanvas.vue`
- `resources/js/page-builder-vue/components/PreviewRegion.vue`
- `resources/js/page-builder-vue/components/EditorToolbar.vue`
- `resources/js/page-builder-vue/composables/useViewport.ts` — viewport zoom logic (extracted from preview-manager.js)
- `resources/js/page-builder-vue/composables/useLibraryLoader.ts` — widget JS/CSS library loading

**Files modified:**

- `resources/js/page-builder-vue/App.vue` — add toolbar, preview canvas, and edit/handles mode layout
- `resources/js/page-builder-vue/stores/editor.ts` — add preview-related actions and getters

**Files deleted:** None

**Acceptance criteria:**
- Preview canvas renders all widgets with correct HTML
- Clicking a widget region selects it (outline highlight)
- Viewport controls (desktop/tablet/mobile) work with correct zoom
- Widget JS libraries (Swiper, Chart.js) load and initialise in preview
- Preview refreshes when user clicks "Apply Changes" or selects a different block
- Links and forms in previews are inert (click/submit prevented)

**Still on Livewire:** Inspector panel, block list (handles mode), widget picker modal, save-as-template modal. The Livewire inspector still works via the existing `block-selected` event bridge.

---

### Session 149: Editor Inspector in Vue — Part 1

**Files created:**

- `resources/js/page-builder-vue/components/InspectorPanel.vue`
- `resources/js/page-builder-vue/components/InspectorHeader.vue`
- `resources/js/page-builder-vue/components/InspectorTabs.vue`
- `resources/js/page-builder-vue/components/InspectorField.vue`
- `resources/js/page-builder-vue/components/InspectorFieldGroup.vue`
- `resources/js/page-builder-vue/components/fields/TextField.vue`
- `resources/js/page-builder-vue/components/fields/TextareaField.vue`
- `resources/js/page-builder-vue/components/fields/NumberField.vue`
- `resources/js/page-builder-vue/components/fields/SelectField.vue`
- `resources/js/page-builder-vue/components/fields/ToggleField.vue`
- `resources/js/page-builder-vue/components/fields/CheckboxesField.vue`
- `resources/js/page-builder-vue/components/fields/NoticeField.vue`
- `resources/js/page-builder-vue/components/fields/RichTextField.vue`
- `resources/js/page-builder-vue/components/ApplyChangesButton.vue`

**Files modified:**

- `resources/js/page-builder-vue/App.vue` — replace Livewire inspector mount with Vue InspectorPanel
- `resources/js/page-builder-vue/stores/editor.ts` — add debounced save action, dirty widget tracking

**Files deleted:** None

**Acceptance criteria:**
- Inspector panel renders when a block is selected, shows "select a block" placeholder when none selected
- Content and Appearance tabs display correct fields from the widget's config_schema
- Text, textarea, number, select, toggle, checkbox, and notice fields work correctly
- Richtext field initialises Quill and saves content
- Config changes persist to the database via debounced API calls
- Widget is marked dirty after config change; "Apply Changes" triggers preview refresh
- Tabbed layout matches current inspector styling

**Still on Livewire:** Block list (handles mode), widget picker modal, save-as-template modal, image upload, button list, color picker, query settings, spacing controls.

---

### Session 150: Editor Inspector in Vue — Part 2

**Files created:**

- `resources/js/page-builder-vue/components/fields/ColorPickerField.vue`
- `resources/js/page-builder-vue/components/fields/ImageUploadField.vue`
- `resources/js/page-builder-vue/components/fields/ButtonListField.vue`
- `resources/js/page-builder-vue/components/QuerySettings.vue`
- `resources/js/page-builder-vue/components/SpacingControl.vue`
- `resources/js/page-builder-vue/components/BlockList.vue`
- `resources/js/page-builder-vue/components/BlockCard.vue`
- `resources/js/page-builder-vue/components/ColumnSlot.vue`

**Files modified:**

- `resources/js/page-builder-vue/App.vue` — add handles mode with BlockList, integrate all inspector features
- `resources/js/page-builder-vue/stores/editor.ts` — add image upload actions, reorder actions
- `package.json` — add drag-and-drop library (e.g. `vuedraggable` or `@vueuse/integrations`)

**Files deleted:** None

**Acceptance criteria:**
- Color picker renders with current value and saves on change
- Image upload works (file select → upload → preview)
- Button list manager supports add/remove/reorder/edit
- Query settings panel renders for collection-backed widgets with limit, order, tag filters
- Spacing controls work with "All" shortcut and individual values
- Handles mode renders the block list with drag-and-drop reordering
- Column widget block cards show child slots with per-column add/reorder/delete
- Ellipsis menu with all actions (add above/below, copy, move up/down, move to column, delete with confirm)
- Event bridge to Livewire widget picker modal works (Vue dispatches open, Livewire returns selection)

**Still on Livewire:** Widget picker modal and save-as-template modal only (both stay permanently).

---

### Session 151: Editor Livewire Teardown

**Files created:** None

**Files modified:**

- `app/Livewire/PageBuilder.php` — strip to thin mount-point: keep `mount()` for bootstrap data, remove all block manipulation methods, keep `createBlock`/`saveAsTemplate` (for modal callbacks), remove preview rendering
- `resources/views/livewire/page-builder.blade.php` — replace with thin shell: toolbar gone, modals stay, main content is just `<div id="page-builder-app" data-bootstrap='...'>`
- `resources/js/page-builder-vue/App.vue` — remove any Livewire inspector fallback code

**Files deleted:**

- `app/Livewire/PageBuilderBlock.php`
- `app/Livewire/PageBuilderInspector.php`
- `resources/views/livewire/page-builder-block.blade.php`
- `resources/views/livewire/page-builder-inspector.blade.php`
- `resources/views/livewire/partials/inspector-field.blade.php`
- `resources/views/livewire/partials/inspector-field-group.blade.php`
- `resources/views/livewire/partials/inspector-fields/` (all 13 field partials)
- `resources/js/page-builder/preview-manager.js`
- `resources/js/page-builder/spacing-controls.js`
- `resources/js/page-builder/richtext-editor.js`
- `resources/js/page-builder/button-list-manager.js`
- `resources/js/page-builder/index.js`

**Acceptance criteria:**
- All editor flows work end-to-end on the Vue stack: create, edit, delete, copy, reorder, preview
- Column widgets work correctly: add children, reorder within columns, move between columns, move to/from main list
- Inline text editing works (if supported — may defer to later session)
- Widget picker modal opens from Vue and returns selections correctly
- Save-as-template modal works from Vue
- No orphaned Livewire event listeners or `$wire` calls remain
- No Alpine.js modules loaded that were specific to the old editor
- Fast test suite passes
- Editor loads and functions identically to the pre-migration version

**Still on Livewire:** Widget picker modal, save-as-template modal (permanent). PageBuilder.php remains as a thin Livewire shell that boots the Vue app.

---

## Appendix A — Files not changing

These files are part of the editor system but require no modification during the migration:

- `app/Services/WidgetRenderer.php` — called by the API controller, same logic
- `app/Services/WidgetDataResolver.php` — called via WidgetRenderer
- `app/Services/DemoDataService.php` — called via WidgetRenderer
- `app/Services/PageContext.php` — injected into Blade templates during render
- `app/Services/PageBuilderDataSources.php` — called by the lookup API controller
- `resources/views/widgets/*.blade.php` — widget Blade templates, unchanged
- `resources/views/components/widget-picker-modal.blade.php` — stays as Blade component
- `app/Filament/Resources/PageResource/Pages/EditPage.php` — Filament page shell, unchanged
- `app/Models/PageWidget.php` — model, unchanged
- `app/Models/WidgetType.php` — model, unchanged

## Appendix B — Security Checklist

- [ ] API endpoints require Filament session auth — no unauthenticated access
- [ ] Widget CRUD endpoints scope operations to the authenticated user's permissions (`update_page` for writes, `view_page` for reads)
- [ ] No sensitive data (API keys, user credentials) exposed in API responses or bootstrap JSON
- [ ] Collection/lookup endpoints return only data the user has permission to view
- [ ] Widget operations validate `page_id` ownership — cannot manipulate widgets on other pages
- [ ] Image uploads validate file type and size (reuse existing Spatie MediaLibrary config)
- [ ] CSRF token is included in all mutating API requests
