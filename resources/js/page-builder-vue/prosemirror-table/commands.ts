import type { Command } from 'prosemirror-state'
import { toggleMark } from 'prosemirror-commands'
import {
  addColumnAfter,
  addColumnBefore,
  deleteColumn,
  addRowAfter,
  addRowBefore,
  deleteRow,
  mergeCells,
  splitCell,
  toggleHeaderRow,
  deleteTable,
} from 'prosemirror-tables'
import { tableSchema } from './schema'

// Re-export the prosemirror-tables structural commands the v1 toolbar wires up.
export {
  addColumnAfter,
  addColumnBefore,
  deleteColumn,
  addRowAfter,
  addRowBefore,
  deleteRow,
  mergeCells,
  splitCell,
  toggleHeaderRow,
  deleteTable,
}

// Build a fresh `rows × cols` table node and replace the current selection
// with it. prosemirror-tables ships every structural command EXCEPT an insert,
// so we construct the node from the schema directly. The optional header row
// becomes table_header (`<th>`) cells; the rest are table_cell (`<td>`).
export function createTable(rows: number, cols: number, withHeaderRow: boolean): Command {
  return (state, dispatch) => {
    const { table, table_row, table_cell, table_header } = tableSchema.nodes
    const rowNodes = []
    for (let r = 0; r < rows; r++) {
      const cells = []
      for (let c = 0; c < cols; c++) {
        const cellType = withHeaderRow && r === 0 ? table_header : table_cell
        const cell = cellType.createAndFill()
        if (cell) {
          cells.push(cell)
        }
      }
      rowNodes.push(table_row.create(null, cells))
    }
    const tableNode = table.create(null, rowNodes)
    if (dispatch) {
      dispatch(state.tr.replaceSelectionWith(tableNode).scrollIntoView())
    }
    return true
  }
}

export const toggleBold: Command = toggleMark(tableSchema.marks.strong)
export const toggleItalic: Command = toggleMark(tableSchema.marks.em)

// Apply (or replace) a link mark across the current selection. An empty href
// clears the mark instead — the toolbar's link button toggles a small prompt.
export function setLink(href: string): Command {
  return (state, dispatch) => {
    const { link } = tableSchema.marks
    const { from, to, empty } = state.selection
    if (empty) {
      return false
    }
    if (dispatch) {
      const tr = state.tr.removeMark(from, to, link)
      const trimmed = href.trim()
      if (trimmed !== '') {
        tr.addMark(from, to, link.create({ href: trimmed }))
      }
      dispatch(tr.scrollIntoView())
    }
    return true
  }
}
