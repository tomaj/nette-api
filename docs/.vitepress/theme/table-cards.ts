// When a Markdown table does not fit the content column (measured on the column,
// not the window, so the sidebar and the outline count), its rows turn into cards
// that label each cell with its column header. Only classes and attributes change,
// the DOM rendered by Vue stays as it is.

let observer: ResizeObserver | undefined
const observed = new WeakSet<Element>()

function fit(table: HTMLTableElement): void {
  table.classList.remove('is-cards')
  if (table.scrollWidth > table.clientWidth + 1) table.classList.add('is-cards')
}

export function enhanceTables(): void {
  observer ??= new ResizeObserver((entries) =>
    entries.forEach((entry) => entry.target.querySelectorAll<HTMLTableElement>(':scope > table').forEach(fit)),
  )
  document.querySelectorAll<HTMLTableElement>('.vp-doc table').forEach((table) => {
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent?.trim() ?? '')
    table.querySelectorAll('tbody tr').forEach((row) => {
      Array.from(row.children).forEach((cell, i) => {
        if (headers[i]) cell.setAttribute('data-label', headers[i])
      })
    })
    const column = table.parentElement
    if (column && !observed.has(column)) {
      observed.add(column)
      observer!.observe(column)
    }
    fit(table)
  })
}
