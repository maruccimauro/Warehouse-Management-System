// Íconos de trazo propios (16 × 16), decorativos: el nombre accesible lo da el control que
// los contiene. Usan currentColor para seguir el color del texto y el modo de alto contraste.
const paths = {
  check: 'M3 8.5l3 3 7-7',
  save: 'M2.5 2.5h9l2 2v9h-11z M5 2.5v3.5h5v-3.5 M5 13.5v-4h6v4',
  back: 'M13 8H3 M7 4L3 8l4 4',
  exit: 'M8 13V3 M4 7l4-4 4 4',
  execute: 'M5 3.5v9l7-4.5z',
  star: 'M8 1.8l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.6l-3.8 2 .7-4.3-3.1-3 4.3-.6z',
  folder: 'M1.5 3.5h4l1.5 1.5h7.5v8h-13z',
  transaction: 'M8 1.5l5.5 3v6.5L8 14l-5.5-3V4.5z M2.5 4.5L8 7.5l5.5-3 M8 7.5V14',
  chevron: 'M6 4l4 4-4 4',
  menu: 'M2 4h12 M2 8h12 M2 12h12',
  success: 'M8 1.5a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13z M5 8.2l2 2 4-4',
  warning: 'M8 1.8l6.5 11.7h-13z M8 6v3.5 M8 11.5v.5',
  error: 'M8 1.5a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13z M5.5 5.5l5 5 M10.5 5.5l-5 5',
  info: 'M8 1.5a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13z M8 7v4.5 M8 4.5v.5',
  help: 'M8 1.5a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13z M6 6.2a2 2 0 1 1 2.8 1.8c-.5.2-.8.6-.8 1.1v.6 M8 11.5v.5',
  sortAscending: 'M4.5 13V3 M2 5.5L4.5 3 7 5.5 M9 4h5 M9 8h3.5 M9 12h2',
  sortDescending: 'M4.5 3v10 M2 10.5L4.5 13 7 10.5 M9 4h5 M9 8h3.5 M9 12h2',
  filter: 'M2 3h12l-4.5 5.5v4.5l-3 1.5v-6z',
  filterOff: 'M2 3h12l-4.5 5.5v4.5l-3 1.5v-6z M11 11l3.5 3.5 M14.5 11L11 14.5',
  sum: 'M12 3H4l4.5 5L4 13h8',
  subtotal: 'M2 3h12 M2 6h12 M2 9h7 M11 11.5h3 M12.5 10v3',
  columns: 'M2 2.5h12v11H2z M6 2.5v11 M10 2.5v11',
  export: 'M8 2v8 M5 7l3 3 3-3 M2.5 11v2.5h11V11',
  first: 'M4 3.5v9 M12 4L8 8l4 4',
  last: 'M12 3.5v9 M4 4l4 4-4 4',
  previous: 'M10 4L6 8l4 4',
  arrowUp: 'M8 13V3 M4 7l4-4 4 4',
  arrowDown: 'M8 3v10 M4 9l4 4 4-4',
  grip: 'M5.5 3.5h1 M9.5 3.5h1 M5.5 8h1 M9.5 8h1 M5.5 12.5h1 M9.5 12.5h1',
  searchHelp: 'M7 2.5a4.5 4.5 0 1 0 0 9a4.5 4.5 0 1 0 0-9z M10.3 10.3l3.2 3.2',
  // Selección múltiple: la flecha de SAP, rellena cuando el criterio tiene más entradas.
  multipleSelection: 'M2.5 6h6V3l5 5-5 5v-3h-6z',
  multipleSelectionActive: 'M2.5 6h6V3l5 5-5 5v-3h-6z',
  deleteRow: 'M3 4.5h10 M6 4.5V3h4v1.5 M4.5 4.5l.7 9h5.6l.7-9',
  // Bandeja: el contador de no leídos del encabezado.
  inbox: 'M1.5 9l2-6h9l2 6v4.5h-13z M1.5 9h4l1 1.5h3l1-1.5h4',
} as const;

export type IconName = keyof typeof paths;

const filled: ReadonlySet<IconName> = new Set(['execute', 'multipleSelectionActive']);

export function Icon({ name, className = 'size-4' }: { name: IconName; className?: string }) {
  const isFilled = filled.has(name);

  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className={`shrink-0 ${className}`}>
      <path
        d={paths[name]}
        fill={isFilled ? 'currentColor' : 'none'}
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
