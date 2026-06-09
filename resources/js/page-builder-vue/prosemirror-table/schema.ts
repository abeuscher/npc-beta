import { Schema } from 'prosemirror-model'
import { tableNodes } from 'prosemirror-tables'
import { marks as basicMarks } from 'prosemirror-schema-basic'

// A deliberately minimal ProseMirror schema, isolated to the Table widget's
// embedded editor (session 349). It carries ONLY what a content table needs:
// the prosemirror-tables node set, a paragraph + text node for cell content,
// and three inline marks (bold / italic / link). No headings, lists, colour,
// heroicons, or media — that richer prose feature set belongs to the deferred
// Quill→ProseMirror migration track, not here. Cells are constrained to
// `paragraph+` so a pasted/nested table can never live inside a cell.
const pmTableNodes = tableNodes({
  tableGroup: 'block',
  cellContent: 'paragraph+',
  cellAttributes: {},
})

export const tableSchema = new Schema({
  nodes: {
    doc: { content: 'block+' },
    paragraph: {
      group: 'block',
      content: 'inline*',
      parseDOM: [{ tag: 'p' }],
      toDOM() {
        return ['p', 0]
      },
    },
    text: { group: 'inline' },
    table: pmTableNodes.table,
    table_row: pmTableNodes.table_row,
    table_cell: pmTableNodes.table_cell,
    table_header: pmTableNodes.table_header,
  },
  marks: {
    strong: basicMarks.strong,
    em: basicMarks.em,
    link: basicMarks.link,
  },
})
