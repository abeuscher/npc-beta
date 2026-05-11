---
title: Bar Chart Widget
description: Configuring data sources, axes, colours, and chart-display options for the Bar Chart widget.
tags: [widget, page-builder, bar-chart, charts, cms]
category: cms
standalone: true
---

# Bar Chart Widget

The Bar Chart widget renders a bar chart on a public page using [Chart.js](https://www.chartjs.org/). The chart's data comes from a Collection — the same Collections that power Collection Manager listings — so updating chart data is a matter of editing rows in the collection, not the widget itself.

The widget renders a `<canvas>` element and a JavaScript bundle on the page; no SVG fallback. Browsers without canvas support will see nothing.

## When to use this widget

Use Bar Chart when you have a small set of labelled numeric values to visualise side-by-side — monthly signups, funds raised by campaign, attendance by event. For categorical comparison where the *shape* of the data matters more than precise values, a bar chart works well. For trend over time at high cardinality, consider a different visualisation.

## Inspector — Content tab

- **Chart title** — a heading rendered above the chart. Optional; leave blank for a chart with no title.
- **Collection** — the Collection that supplies the data. Pick from the dropdown. The collection must already exist (create one via **Collections** in the CMS group).
- **X axis field** — the field from the chosen collection whose value becomes each bar's label. Typically a text or category field (e.g. `month_name`, `campaign_handle`).
- **Y axis field** — the field whose numeric value becomes each bar's height. Must contain numbers — non-numeric values render as zero. If the field is missing from a row, that bar shows zero height.
- **X axis label** — the caption rendered below the X axis. Optional.
- **Y axis label** — the caption rendered to the left of the Y axis. Optional.

The **Collection**, **X axis field**, and **Y axis field** are all required. Until all three are set the widget shows a setup notice in the editor instead of rendering, and on the public page renders nothing.

## Inspector — Appearance tab

- **Bar fill color** — the colour of every bar. Single colour — the widget does not currently support per-bar colouring or gradient palettes. Default is the CRM brand blue (`#0172ad`).

Standard widget appearance fields (background, padding, full-width) apply as usual.

## Common patterns

- **Monthly trend over the year.** Create a collection with one row per month and fields `month` (text, e.g. `Jan`, `Feb`) and `value` (number). Map X to `month` and Y to `value`. Order the collection rows in display order, since the chart renders rows in the order returned by the data source.
- **Campaign comparison.** Create a collection of campaigns with fields `name` and `raised_amount`. Map X to `name`, Y to `raised_amount`. Update the values as totals change.
- **Survey result breakdown.** Create a collection with one row per option and a count field. Map X to option text, Y to count.

## Gotchas

- **The Y field must be numeric.** If you map Y to a text field, every bar reads as zero. Check that the field's type in the collection definition is `number`.
- **Empty collections render an empty canvas.** No "no data" message — just a blank chart frame. If a chart looks empty in preview, verify the collection has rows and that the chosen Y field has values.
- **Tags and sort order.** The chart honours the widget's Query Panel settings (order by, tag filter, limit). Use these to slice the data set down — for example, "top 10 by amount" or "tagged `featured`".
- **Chart.js bundle.** The widget pulls in the Chart.js library on any page where a Bar Chart appears. If page weight matters, prefer one chart per page over many.
