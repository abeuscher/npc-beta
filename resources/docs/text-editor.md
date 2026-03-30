---
title: Text Editor
description: Reference for the rich text editor (Quill) used throughout the admin — toolbar buttons, formatting options, and inline image insertion.
standalone: true
version: "0.93"
updated: 2026-03-30
tags: [reference, admin, editor, quill]
---

# Text Editor

The admin uses [Quill](https://quilljs.com/) as its rich text editor. It appears in the page builder inspector, event descriptions, meeting details, and email template bodies.

---

## Toolbar

The toolbar is arranged in two rows.

**Row 1 — Formatting:**

- **Heading** dropdown — choose Paragraph (normal text), Heading 2, Heading 3, Heading 4, or Heading 5. Heading 1 is reserved for the page title and is not offered here.
- **Bold**, **Italic**, **Underline**, **Strikethrough** — standard inline formatting.
- **Text Color** and **Background Color** — pick a colour for the selected text or its highlight.

**Row 2 — Structure & media:**

- **Bullet List** and **Numbered List** — toggle list formatting on selected lines.
- **Blockquote** — indent the selected text as a quote.
- **Text Align** — left, centre, right, or justify.
- **Link** — insert or edit a hyperlink on the selected text.
- **Image** — upload an image file and insert it inline at the cursor. Accepted formats: PNG, JPEG, GIF, WebP, SVG. Maximum file size: 10 MB.
- **Clean** — remove all formatting from the selected text. Useful when pasting from Word or Google Docs and the text carries unwanted styles.

---

## Tips

- To paste plain text without source formatting, use **Ctrl+Shift+V** (Windows) or **Cmd+Shift+V** (Mac), or paste normally and then select the text and click **Clean**.
- The font is controlled by the site's design system and cannot be changed per-block.
