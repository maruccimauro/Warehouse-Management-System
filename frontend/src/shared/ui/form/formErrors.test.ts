import { visibleFieldError, type FieldErrorState } from './formErrors';

const meta = (overrides: Partial<FieldErrorState>): FieldErrorState => ({
  errors: [{ message: 'Campo obligatorio.' }],
  isBlurred: true,
  errorMap: {},
  ...overrides,
});

describe('visibleFieldError', () => {
  it('un campo vacío no se marca al salir de él, solo al grabar o ejecutar', () => {
    expect(visibleFieldError(meta({}), false, true)).toBeUndefined();
    expect(visibleFieldError(meta({}), true, true)).toBe('Campo obligatorio.');
  });

  it('un valor con formato inválido se marca al salir del campo', () => {
    expect(visibleFieldError(meta({ errors: [{ message: 'Formato inválido.' }] }), false, false)).toBe('Formato inválido.');
    expect(visibleFieldError(meta({ isBlurred: false }), false, false)).toBeUndefined();
  });

  it('el error del servidor se ve siempre', () => {
    expect(visibleFieldError(meta({ errors: [], isBlurred: false, errorMap: { onServer: 'Ya existe.' } }), false, true)).toBe('Ya existe.');
  });
});
