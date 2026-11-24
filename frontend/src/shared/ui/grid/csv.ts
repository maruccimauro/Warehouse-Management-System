import { intlLocale } from '@/shared/format/format';
import type { GridColumn } from './gridTypes';
import { formatGridValue, isEmptyValue } from './gridValues';

// Exportación CSV de la grilla (E2.00, D5), como "Exportar a hoja de cálculo" de la ALV.
//
// - Separador según el idioma: con coma decimal (es-AR) Excel espera punto y coma.
// - BOM UTF-8: sin él, Excel en Windows abre el archivo como ANSI y rompe los acentos.
// - Números sin separador de miles, para que la hoja los lea como números.
// - Inyección de fórmulas (OWASP CSV Injection): un valor que empieza con =, +, -, @,
//   tabulación o retorno de carro se ejecutaría como fórmula al abrir el archivo. Se antepone
//   un apóstrofo. Un SKU, un nombre o un motivo de bloqueo los escribe un usuario.

const formulaStart = /^[=+\-@\t\r]/;
const needsQuotes = /["\r\n]/;
const byteOrderMark = String.fromCharCode(0xfeff);

export function csvSeparator(locale = intlLocale()): ',' | ';' {
  const decimal = new Intl.NumberFormat(locale).formatToParts(1.5).find((part) => part.type === 'decimal');

  return decimal?.value === ',' ? ';' : ',';
}

// raw: el texto sale de un número formateado por la grilla, no de lo que escribió un usuario;
// un negativo así no es una fórmula y la hoja debe leerlo como número.
export function csvCell(text: string, separator: string, raw = false): string {
  const guarded = !raw && formulaStart.test(text) ? `'${text}` : text;

  return needsQuotes.test(guarded) || guarded.includes(separator) ? `"${guarded.replaceAll('"', '""')}"` : guarded;
}

function exportCell<TData>(column: GridColumn<TData>, row: TData, locale: string, separator: string): string {
  const value = column.value(row);

  if (column.format === undefined && typeof value === 'number') {
    const text = new Intl.NumberFormat(locale, { useGrouping: false, maximumFractionDigits: 20 }).format(value);

    return csvCell(text, separator, true);
  }

  const text = isEmptyValue(value) && column.format === undefined ? '' : formatGridValue(column, value, row);

  return csvCell(text, separator);
}

export function toCsv<TData>(columns: readonly GridColumn<TData>[], rows: readonly TData[]): string {
  const locale = intlLocale();
  const separator = csvSeparator(locale);
  const lines = [
    columns.map((column) => csvCell(column.header, separator)).join(separator),
    ...rows.map((row) => columns.map((column) => exportCell(column, row, locale, separator)).join(separator)),
  ];

  return `${byteOrderMark}${lines.join('\r\n')}\r\n`;
}

// Nombre con fecha y hora local: dos exportaciones del mismo día no se pisan.
export function csvFileName(base: string, now = new Date()): string {
  const pad = (value: number) => String(value).padStart(2, '0');
  const stamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;

  return `${base}-${stamp}.csv`;
}

export function downloadCsv(fileName: string, content: string): void {
  const url = URL.createObjectURL(new Blob([content], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');

  link.href = url;
  link.download = fileName;
  link.hidden = true;
  document.body.append(link);
  link.click();
  link.remove();
  // El navegador ya tomó la descarga; liberar en la tarea siguiente evita cortarla en Safari.
  window.setTimeout(() => {
    URL.revokeObjectURL(url);
  }, 0);
}
