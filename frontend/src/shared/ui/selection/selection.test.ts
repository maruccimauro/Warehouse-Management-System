import { listSearchParams } from '@/shared/api/listing';
import { checkSelection, normalizeEntries, selectionFilters, withMainEntry } from './selection';
import type { SelectionField, SelectOptionsField } from './selectionTypes';

const code: SelectOptionsField = {
  kind: 'selectOptions',
  id: 'code',
  label: 'Código',
  normalize: (text) => text.trim().toUpperCase(),
};
const rank: SelectOptionsField = { kind: 'selectOptions', id: 'rank', label: 'Nivel', type: 'integer' };
const fields: SelectionField[] = [
  { kind: 'parameter', id: 'warehouse', label: 'Depósito', required: true },
  code,
  rank,
];

describe('lógica de la pantalla de selección', () => {
  it('desde y hasta escriben la entrada principal: valor, rango y vacío la quita', () => {
    const single = withMainEntry([], 'A', '');
    const range = withMainEntry(single, 'A', 'M');

    expect(single).toEqual([{ sign: 'I', option: 'EQ', low: 'A' }]);
    expect(range).toEqual([{ sign: 'I', option: 'BT', low: 'A', high: 'M' }]);
    expect(withMainEntry([...range, { sign: 'I', option: 'EQ', low: 'Z' }], '', '')).toEqual([
      { sign: 'I', option: 'EQ', low: 'Z' },
    ]);
  });

  it('normaliza las entradas: mayúsculas, sin vacías y un rango sin hasta pasa a valor', () => {
    expect(
      normalizeEntries(code, [
        { sign: 'I', option: 'EQ', low: ' a01 ' },
        { sign: 'I', option: 'BT', low: '', high: '' },
        { sign: 'I', option: 'BT', low: 'b', high: '' },
        { sign: 'E', option: 'NB', low: 'c', high: 'd' },
      ]),
    ).toEqual([
      { sign: 'I', option: 'EQ', low: 'A01' },
      { sign: 'I', option: 'EQ', low: 'B' },
      { sign: 'E', option: 'NB', low: 'C', high: 'D' },
    ]);
  });

  it('F8 exige los obligatorios y valida enteros, límites y la tabla de verificación', () => {
    const base = { warehouse: 'CEN', code: [], rank: [] };

    expect(checkSelection(fields, { ...base, warehouse: ' ' }, new Set())).toEqual({
      ok: false,
      error: { fieldId: 'warehouse', target: 'low', code: 'required' },
    });
    expect(checkSelection(fields, base, new Set(['warehouse']))).toMatchObject({ error: { code: 'invalid' } });
    expect(
      checkSelection(fields, { ...base, rank: [{ sign: 'I', option: 'BT', low: '5', high: '2' }] }, new Set()),
    ).toMatchObject({ error: { fieldId: 'rank', target: 'high', code: 'lowGreater' } });
    expect(
      checkSelection(fields, { ...base, rank: [{ sign: 'I', option: 'EQ', low: 'dos' }] }, new Set()),
    ).toMatchObject({ error: { target: 'low', code: 'integer' } });
    // Un error en una entrada que no muestra la pantalla lleva al botón de selección múltiple.
    expect(
      checkSelection(
        fields,
        {
          ...base,
          code: [
            { sign: 'I', option: 'EQ', low: 'A' },
            { sign: 'I', option: 'BT', low: '', high: 'M' },
          ],
        },
        new Set(),
      ),
    ).toMatchObject({ error: { fieldId: 'code', target: 'multiple', code: 'lowMissing' } });
  });

  it('el texto no compara límites: el orden depende de la intercalación de la base', () => {
    const result = checkSelection(
      fields,
      { warehouse: 'CEN', code: [{ sign: 'I', option: 'BT', low: 'z', high: 'a' }], rank: [] },
      new Set(),
    );

    expect(result).toEqual({
      ok: true,
      values: { warehouse: 'CEN', code: [{ sign: 'I', option: 'BT', low: 'Z', high: 'A' }], rank: [] },
    });
  });

  it('traduce los criterios a filter[campo][i][...] del listado, sin los parámetros', () => {
    const filters = selectionFilters(fields, {
      warehouse: 'CEN',
      code: [
        { sign: 'I', option: 'EQ', low: 'A01' },
        { sign: 'I', option: 'BT', low: 'B01', high: 'B99' },
      ],
      rank: [],
    });

    expect(filters).toEqual({
      code: [
        { sign: 'I', option: 'EQ', low: 'A01' },
        { sign: 'I', option: 'BT', low: 'B01', high: 'B99' },
      ],
    });
    expect(decodeURIComponent(listSearchParams({ page: 1, perPage: 50, filters }).toString())).toBe(
      'filter[code][0][sign]=I&filter[code][0][option]=EQ&filter[code][0][low]=A01&' +
        'filter[code][1][sign]=I&filter[code][1][option]=BT&filter[code][1][low]=B01&filter[code][1][high]=B99&' +
        'page=1&per_page=50',
    );
  });
});
