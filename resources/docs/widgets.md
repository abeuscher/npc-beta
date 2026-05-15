---
title: Widgets
description: Introduction to page-builder widgets — what they are, where they appear, and where to find help for specific widget types.
tags: [widget, page-builder, cms]
category: cms
standalone: true
search_weight: 100
---

# Widgets

Widgets are the building blocks of pages on your public website. Every CMS page — the homepage, blog posts, event pages, contact pages — is assembled from widgets dropped onto the page in the page builder. A widget might be a simple text block or image, or a more involved piece like a donation form, an event calendar, or a chart pulling from a collection.

## Using widgets in the page builder

When you edit a CMS page, the **+ Widget** button below the preview opens the widget picker. Pick a widget to add it to the page, click it to select, and use the inspector panel on the right to configure its content and appearance. See the [Pages](/admin/help/cms-pages) article for the full page-builder workflow.

Each widget has two inspector tabs — **Content** (the widget's data and copy) and **Appearance** (background, padding, full-width). Every inspector field has a label, and most have a one-line helper underneath that explains what the field controls. For most widgets that's enough.

## Widgets with detailed help

A handful of widgets have enough configuration surface to warrant a dedicated help article. The list below is sortable — click a column header to re-sort, click again to reverse. Click a widget's help link to open its article.

<div
    x-data="{
        sort: 'label',
        dir: 'asc',
        rows: [
            { label: 'Bar Chart', category: 'Content', description: 'Data visualization bar chart powered by a collection data source.', slug: 'widget-bar-chart' },
            { label: 'Donation Form', category: 'Giving and Sales · Forms', description: 'Configurable donation form with preset amounts, monthly, and annual options.', slug: 'widget-donation-form' },
            { label: 'Event Calendar', category: 'Events', description: 'Interactive month/week calendar view of published events.', slug: 'widget-event-calendar' },
            { label: 'Event Registration Form', category: 'Events · Forms', description: 'Sign-up form for a selected event, with payment support for paid events.', slug: 'widget-event-registration' },
            { label: 'Pricing Chart', category: 'Content', description: 'Side-by-side pricing comparison table with N tier columns and an emphasis treatment for the recommended tier.', slug: 'widget-pricing-chart' },
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

Widgets not on the list use the inspector itself as the reference. Detailed articles get added as widget complexity warrants them — when a new widget gains a dedicated help doc, the author appends a row to the table above.
