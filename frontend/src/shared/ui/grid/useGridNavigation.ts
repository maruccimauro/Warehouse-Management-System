import { useCallback, useState, type FocusEvent, type KeyboardEvent, type RefObject } from 'react';

export interface CellPosition {
  row: number;
  col: number;
}

// Fila 0 es la cabecera. Cada celda enfocable lleva data-cell="fila:columna": la celda misma,
// o el control que contiene (el botón de orden de la cabecera, la casilla de selección).
export function cellAttribute(row: number, col: number): string {
  return `${row}:${col}`;
}

function parseCell(element: Element | null): CellPosition | undefined {
  const value = element?.closest('[data-cell]')?.getAttribute('data-cell');
  const [row, col] = (value ?? '').split(':').map(Number);

  return row === undefined || col === undefined || Number.isNaN(row) || Number.isNaN(col) ? undefined : { row, col };
}

function clamp(value: number, max: number): number {
  return Math.min(Math.max(value, 0), Math.max(max, 0));
}

// Altura típica de una fila en la densidad de la grilla, para RePág y AvPág cuando el
// contenedor todavía no tiene medidas (jsdom, por ejemplo).
const fallbackPageRows = 10;

interface GridNavigationOptions {
  rowCount: number;
  colCount: number;
  containerRef: RefObject<HTMLElement | null>;
}

// Navegación por celdas del patrón Grid de WAI-ARIA, con tabindex itinerante: la grilla es una
// sola parada de Tab y las flechas mueven el foco. En un borde no se cancela el evento, así el
// navegador sigue desplazando la página (guía de navegación espacial).
export function useGridNavigation({ rowCount, colCount, containerRef }: GridNavigationOptions) {
  const [stored, setStored] = useState<CellPosition>({ row: 0, col: 0 });
  // Si se achica la grilla (un filtro, otra página), la celda activa queda dentro del rango.
  const active: CellPosition = { row: clamp(stored.row, rowCount - 1), col: clamp(stored.col, colCount - 1) };

  const focusCell = useCallback(
    (position: CellPosition) => {
      const element = containerRef.current?.querySelector<HTMLElement>(
        `[data-cell="${cellAttribute(position.row, position.col)}"]`,
      );

      element?.focus({ preventScroll: true });
      element?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    },
    [containerRef],
  );

  const onFocus = (event: FocusEvent<HTMLElement>) => {
    const position = parseCell(event.target);

    if (position !== undefined) {
      setStored(position);
    }
  };

  const pageRows = (cell: Element): number => {
    const container = containerRef.current;
    const height = cell.getBoundingClientRect().height;

    if (container === null || height === 0 || container.clientHeight === 0) {
      return fallbackPageRows;
    }

    return Math.max(1, Math.floor(container.clientHeight / height) - 1);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
    if (event.altKey || event.metaKey || event.defaultPrevented || !(event.target instanceof Element)) {
      return;
    }

    const current = parseCell(event.target);

    if (current === undefined) {
      return;
    }

    const lastRow = rowCount - 1;
    const lastCol = colCount - 1;
    let next: CellPosition | undefined;

    if (event.ctrlKey && !['Home', 'End'].includes(event.key)) {
      return;
    }

    switch (event.key) {
      case 'ArrowUp':
        next = { row: current.row - 1, col: current.col };
        break;
      case 'ArrowDown':
        next = { row: current.row + 1, col: current.col };
        break;
      case 'ArrowLeft':
        next = { row: current.row, col: current.col - 1 };
        break;
      case 'ArrowRight':
        next = { row: current.row, col: current.col + 1 };
        break;
      case 'Home':
        next = event.ctrlKey ? { row: 0, col: 0 } : { row: current.row, col: 0 };
        break;
      case 'End':
        next = event.ctrlKey ? { row: lastRow, col: lastCol } : { row: current.row, col: lastCol };
        break;
      case 'PageDown':
        next = { row: clamp(current.row + pageRows(event.target), lastRow), col: current.col };
        break;
      case 'PageUp':
        next = { row: clamp(current.row - pageRows(event.target), lastRow), col: current.col };
        break;
      default:
        return;
    }

    const outside = next.row < 0 || next.row > lastRow || next.col < 0 || next.col > lastCol;

    if (outside || (next.row === current.row && next.col === current.col)) {
      return;
    }

    event.preventDefault();
    focusCell(next);
  };

  return { active, onFocus, onKeyDown, focusCell };
}
