import { EditorState } from 'prosemirror-state'
import { EditorView } from 'prosemirror-view'
import { DOMParser as PMDOMParser, DOMSerializer, type Node as PMNode } from 'prosemirror-model'
import { keymap } from 'prosemirror-keymap'
import { baseKeymap } from 'prosemirror-commands'
import { history, undo, redo } from 'prosemirror-history'
import { tableEditing, goToNextCell } from 'prosemirror-tables'
import { tableSchema } from './schema'

export interface TableEditorOptions {
  html?: string
  columnWidths?: number[]
  onChange?: (html: string) => void
}

export interface TableEditorHandle {
  view: EditorView
  getHTML(): string
  hasTable(): boolean
  getColumnCount(): number
  setColumnWidths(widths: number[]): void
  destroy(): void
}

function docFromHTML(html: string): PMNode | undefined {
  if (!html || html.trim() === '') {
    return undefined
  }
  const tmp = document.createElement('div')
  tmp.innerHTML = html.trim()
  return PMDOMParser.fromSchema(tableSchema).parse(tmp)
}

function htmlFromDoc(doc: PMNode): string {
  const fragment = DOMSerializer.fromSchema(tableSchema).serializeFragment(doc.content)
  const tmp = document.createElement('div')
  tmp.appendChild(fragment)
  return tmp.innerHTML
}

function documentHasTable(doc: PMNode): boolean {
  let found = false
  doc.descendants((node) => {
    if (node.type.name === 'table') {
      found = true
    }
    return !found
  })
  return found
}

// Number of structural columns in the first table, summing colspans of its
// first row (merged cells still occupy their columns).
function columnsOf(node: PMNode): number {
  const firstRow = node.firstChild
  if (!firstRow) return 0
  let n = 0
  firstRow.forEach((cell) => {
    n += (cell.attrs.colspan as number) ?? 1
  })
  return n
}

function firstTableColumns(doc: PMNode): number {
  let count = 0
  doc.descendants((node) => {
    if (count === 0 && node.type.name === 'table') {
      count = columnsOf(node)
      return false
    }
    return count === 0
  })
  return count
}

// A NodeView for the table node that renders a <colgroup> from the widget's
// per-column percentage widths. ProseMirror manages the <tbody> (contentDOM);
// the colgroup is ours, so we ignore mutations on it. This is the same DOM
// shape prosemirror-tables' own TableView uses, so cell editing/selection are
// unaffected.
class TableColgroupView {
  node: PMNode
  dom: HTMLElement
  contentDOM: HTMLElement
  private colgroup: HTMLElement
  private getWidths: () => number[]

  constructor(node: PMNode, getWidths: () => number[]) {
    this.node = node
    this.getWidths = getWidths

    const table = document.createElement('table')
    this.colgroup = document.createElement('colgroup')
    const tbody = document.createElement('tbody')
    table.appendChild(this.colgroup)
    table.appendChild(tbody)

    this.dom = table
    this.contentDOM = tbody
    this.renderColgroup()
  }

  renderColgroup(): void {
    const widths = this.getWidths()
    const cols = columnsOf(this.node)
    let hasWidth = false
    this.colgroup.replaceChildren()
    for (let i = 0; i < cols; i++) {
      const col = document.createElement('col')
      const w = widths[i]
      if (typeof w === 'number' && w >= 1 && w <= 100) {
        col.style.width = `${w}%`
        hasWidth = true
      }
      this.colgroup.appendChild(col)
    }
    this.dom.style.tableLayout = hasWidth ? 'fixed' : ''
  }

  update(node: PMNode): boolean {
    if (node.type.name !== 'table') return false
    this.node = node
    this.renderColgroup()
    return true
  }

  ignoreMutation(m: MutationRecord): boolean {
    return this.colgroup === m.target || this.colgroup.contains(m.target as Node)
  }
}

export function createTableEditor(mount: HTMLElement, opts: TableEditorOptions = {}): TableEditorHandle {
  let columnWidths: number[] = Array.isArray(opts.columnWidths) ? opts.columnWidths.slice() : []
  let tableView: TableColgroupView | null = null

  const state = EditorState.create({
    schema: tableSchema,
    doc: docFromHTML(opts.html ?? ''),
    plugins: [
      keymap({
        Tab: goToNextCell(1),
        'Shift-Tab': goToNextCell(-1),
        'Mod-z': undo,
        'Mod-y': redo,
        'Shift-Mod-z': redo,
      }),
      keymap(baseKeymap),
      history(),
      tableEditing(),
    ],
  })

  const view = new EditorView(mount, {
    state,
    nodeViews: {
      table: (node) => {
        tableView = new TableColgroupView(node, () => columnWidths)
        return tableView
      },
    },
    dispatchTransaction(tr) {
      const next = view.state.apply(tr)
      view.updateState(next)
      if (tr.docChanged && opts.onChange) {
        opts.onChange(documentHasTable(next.doc) ? htmlFromDoc(next.doc) : '')
      }
    },
  })

  return {
    view,
    getHTML: () => (documentHasTable(view.state.doc) ? htmlFromDoc(view.state.doc) : ''),
    hasTable: () => documentHasTable(view.state.doc),
    getColumnCount: () => firstTableColumns(view.state.doc),
    setColumnWidths: (widths: number[]) => {
      columnWidths = Array.isArray(widths) ? widths.slice() : []
      tableView?.renderColgroup()
    },
    destroy: () => view.destroy(),
  }
}
