import { i18n } from '@/i18n';
import { fallbackLanguage, isSupportedLanguage, languages } from '@/i18n/resources';

// Todo número y fecha visible pasa por estas funciones: el formato sigue al idioma activo
// y no depende de la configuración regional del navegador.

export function intlLocale(): string {
  const language = i18n.resolvedLanguage ?? fallbackLanguage;

  return languages[isSupportedLanguage(language) ? language : fallbackLanguage].intlLocale;
}

function toDate(value: Date | string): Date {
  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    throw new RangeError(`Fecha inválida: ${String(value)}`);
  }

  return date;
}

export function formatNumber(value: number, options?: Intl.NumberFormatOptions): string {
  return new Intl.NumberFormat(intlLocale(), options).format(value);
}

// La API entrega instantes en UTC (ISO 8601); se muestran en la zona horaria del usuario
// salvo que se indique otra.
export function formatDate(value: Date | string, options?: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'short', ...options }).format(toDate(value));
}

// Reloj de 24 horas por defecto: los registros operativos (movimientos, recepciones,
// despachos) no deben depender de a. m. y p. m. para leerse en orden.
export function formatDateTime(value: Date | string, options?: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat(intlLocale(), {
    dateStyle: 'short',
    timeStyle: 'short',
    hourCycle: 'h23',
    ...options,
  }).format(toDate(value));
}
