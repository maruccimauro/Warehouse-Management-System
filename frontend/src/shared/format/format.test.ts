import { formatDate, formatDateTime, formatNumber } from './format';

describe('formato con Intl según el idioma activo', () => {
  it('agrupa miles con punto y usa coma decimal', () => {
    expect(formatNumber(1234567.891)).toBe('1.234.567,891');
    expect(formatNumber(1234)).toBe('1.234');
  });

  it('acepta opciones de Intl.NumberFormat', () => {
    expect(formatNumber(0.5, { style: 'percent' })).toBe('50%');
  });

  it('formatea fechas ISO 8601 de la API en la zona horaria indicada', () => {
    expect(formatDate('2026-11-23T02:30:00Z', { timeZone: 'UTC' })).toBe('23/11/26');
    expect(formatDate('2026-11-23T02:30:00Z', { timeZone: 'America/Argentina/Buenos_Aires' })).toBe('22/11/26');
  });

  it('formatea fecha y hora con reloj de 24 horas', () => {
    expect(formatDateTime(new Date('2026-11-23T14:05:00Z'), { timeZone: 'UTC' })).toBe('23/11/26, 14:05');
  });

  it('rechaza una fecha inválida en lugar de mostrar "Invalid Date"', () => {
    expect(() => formatDate('no-es-fecha')).toThrow(RangeError);
  });
});
