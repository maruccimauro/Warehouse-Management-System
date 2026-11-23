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
} as const;

export type IconName = keyof typeof paths;

const filled: ReadonlySet<IconName> = new Set(['execute']);

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
