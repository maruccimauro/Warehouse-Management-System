import { redirectTargetFrom } from './redirectTarget';

describe('destino después del login', () => {
  it('vuelve a la ruta interna que se intentó abrir', () => {
    expect(redirectTargetFrom({ from: '/inventario?zona=A#fila-3' })).toBe('/inventario?zona=A#fila-3');
  });

  it.each([
    ['sin estado', null],
    ['estado sin destino', {}],
    ['destino que no es texto', { from: 42 }],
    ['URL absoluta', { from: 'https://otro-sitio.example/' }],
    ['URL relativa al protocolo', { from: '//otro-sitio.example/' }],
    ['ruta relativa', { from: 'inventario' }],
  ])('usa el inicio ante %s', (_caso, state) => {
    expect(redirectTargetFrom(state)).toBe('/');
  });
});
