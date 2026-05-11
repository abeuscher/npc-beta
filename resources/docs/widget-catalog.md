---
title: Widget Catalog
description: A sortable index of every widget that has detailed configuration help.
tags: [widget, page-builder, catalog, cms]
category: cms
standalone: true
---

# Widget Catalog

Most page-builder widgets are self-explanatory from their label and inspector fields. A handful have enough configuration depth to warrant a dedicated help article — those are listed below.

The table is sortable: click a column header to re-sort. Search from the help top-bar also works for finding a specific widget by name (e.g. "bar chart").

If the widget you're looking for isn't here, the inspector itself is usually the best reference — every field has a label and most have a one-line helper underneath.

<div
    x-data="{
        sort: 'label',
        dir: 'asc',
        rows: [
            { label: 'Bar Chart', category: 'Content', description: 'Data visualization bar chart powered by a collection data source.', slug: 'widget-bar-chart' },
            { label: 'Donation Form', category: 'Giving and Sales · Forms', description: 'Configurable donation form with preset amounts, monthly, and annual options.', slug: 'widget-donation-form' },
            { label: 'Event Calendar', category: 'Events', description: 'Interactive month/week calendar view of published events.', slug: 'widget-event-calendar' },
            { label: 'Event Registration Form', category: 'Events · Forms', description: 'Sign-up form for a selected event, with payment support for paid events.', slug: 'widget-event-registration' },
            { label: 'Web Form', category: 'Forms', description: 'Embeds a contact or general-purpose form built in the Form Manager.', slug: 'widget-web-form' }
        ],
        toggle(col) {
            if (this.sort === col) {
                this.dir = this.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort = col;
                this.dir = 'asc';
            }
        },
        arrow(col) {
            if (this.sort !== col) return '';
            return this.dir === 'asc' ? ' ▲' : ' ▼';
        },
        get sorted() {
            const col = this.sort;
            const factor = this.dir === 'asc' ? 1 : -1;
            return [...this.rows].sort((a, b) =>
                a[col].toString().toLowerCase().localeCompare(b[col].toString().toLowerCase()) * factor
            );
        }
    }"
    class="widget-catalog"
>
    <table class="widget-catalog-table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th @click="toggle('label')" style="cursor:pointer; text-align:left; border-bottom:2px solid currentColor; padding:0.5rem;">Label<span x-text="arrow('label')"></span></th>
                <th @click="toggle('category')" style="cursor:pointer; text-align:left; border-bottom:2px solid currentColor; padding:0.5rem;">Category<span x-text="arrow('category')"></span></th>
                <th @click="toggle('description')" style="cursor:pointer; text-align:left; border-bottom:2px solid currentColor; padding:0.5rem;">Description<span x-text="arrow('description')"></span></th>
                <th style="text-align:left; border-bottom:2px solid currentColor; padding:0.5rem;">Help Doc</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in sorted" :key="row.slug">
                <tr>
                    <td x-text="row.label" style="padding:0.5rem; border-bottom:1px solid rgba(127,127,127,0.2);"></td>
                    <td x-text="row.category" style="padding:0.5rem; border-bottom:1px solid rgba(127,127,127,0.2);"></td>
                    <td x-text="row.description" style="padding:0.5rem; border-bottom:1px solid rgba(127,127,127,0.2);"></td>
                    <td style="padding:0.5rem; border-bottom:1px solid rgba(127,127,127,0.2);"><a :href="'/admin/help/' + row.slug" x-text="row.slug"></a></td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

## Adding a widget to this catalog

When a new widget gains a detailed help doc, the author appends a row to the table above. The catalog is hand-curated on purpose — it lists only widgets where the configuration surface justifies a separate article, rather than mirroring every row in `widget_types`. Widgets without detailed docs surface through the picker's built-in label and description.
