---
title: Table Widget
description: Authoring a real WYSIWYG table — insert rows and columns, merge cells, toggle a header row, paste from Word or Google Docs, and style the result.
tags: [widget, page-builder, table, cms]
category: cms
standalone: true
parent: widgets
---

# Table Widget

The Table widget puts a real, editable table on a page — the kind of table you'd build in a word processor, with header rows, merged cells, and content you can paste straight from Word, Google Docs, or a web page. It's the right tool for tabular content: comparison tables, fee schedules, cookie/privacy policies, hours, anything that reads as a grid.

The table is authored in a dedicated editor that opens from the inspector — not by typing directly on the page. The page itself shows the finished table; the editor is where you build it.

## When to use this widget

Use a Table when your content is genuinely a grid of rows and columns. For a side-by-side **pricing comparison** with calls-to-action, the Pricing Chart widget is purpose-built and reads better. For prose with the occasional emphasized line, a Text widget is the right home — the Table widget is a block between paragraphs, not a table inside a sentence.

## Building the table

1. Drop a **Table** widget onto the page and open its inspector.
2. Under **Table**, click **Insert a table**. A wide editor opens.
3. Pick the starting size from the grid (like the "Insert table" picker in Google Docs) — hover to choose the number of columns and rows, then click. The new table comes with a header row.
4. Type into the cells. Use **Tab** to move to the next cell.

### The editor toolbar

Once a table exists, the toolbar gives you:

- **+ Row / − Row** — add a row below the current one, or delete the current row.
- **+ Col / − Col** — add a column to the right, or delete the current column.
- **Merge / Split** — select two or more cells (click and drag across them) and **Merge** them into one; **Split** undoes a merge.
- **Header** — toggle the header row on or off. Header cells render in the header style (see Appearance).
- **Bold / Italic / Link** — basic formatting for the text inside a cell. Select text, then apply. For a link, click the link button, type a URL, and apply.
- **Delete table** — removes the whole table so you can start over.

The editor shows the table with the colours, alignment, and gridlines you've configured (see Appearance), so what you build is what publishes.

Click **Done** (or click outside the editor) to close it. Your changes are saved with the widget.

### Column widths

Above the table is a row of small percentage boxes — one per column. Type a number (1–100) to fix that column's width as a percentage of the table; leave it blank for **auto** (the column sizes to its content and the remaining space). Mix fixed and auto columns freely.

### Pasting a table

You can paste a table copied from Word, Google Docs, a spreadsheet, or a web page directly into the editor. The structure — rows, columns, header cells, and merged (spanned) cells — comes across; fonts, colours, and other source styling are dropped so the table picks up your site's styling instead. Always give a pasted table a quick check, especially if the original had complex merged regions.

## Appearance tab

The table's structure and text live in the editor; its **look** is controlled here, and applies to the whole table at render time.

- **Border & gridlines** — the border control, with the four outer edges plus two interior toggles: horizontal lines between rows and vertical lines between columns. They share one width and colour.
- **Header alignment** / **Body alignment** — alignment for the header-row cells and the body cells. Horizontal position sets text alignment; vertical position sets how content sits within taller cells.
- **Header background / Header text** — colours for the header row.
- **Cell background / Cell text** — colours for the body cells.
- **Zebra striping** — turn this on to shade alternate body rows; a **stripe background** and **stripe text** colour appear. The header row is never striped.

## Notes

- The table is stored as clean HTML and rendered safely — any scripts, inline styles, or event handlers in pasted content are removed.
- Per-cell colours and per-cell borders aren't part of this widget; styling is by region (header / body / stripe). Cell-level styling and tables inside flowing paragraphs are planned for a later release.
