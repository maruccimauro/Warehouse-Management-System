import { resolveHelpKey } from './help';
import { helpLinks, helpUrl, isHelpKey } from './helpLinks';

const su3 = { code: 'SU3', helpKey: 'transaction.SU3' } as const;

function element(html: string, selector: string): Element {
  document.body.innerHTML = html;
  const found = document.querySelector(selector);

  if (found === null) {
    throw new Error(`No existe ${selector}`);
  }

  return found;
}

describe('claves de ayuda', () => {
  it('arma las anclas de errores, estados y permisos según DM-11', () => {
    expect(helpUrl('message.WAREHOUSE_INACTIVE')).toBe('/docs/referencia/mensajes#warehouse-inactive');
    expect(helpUrl('state.location.blocked')).toBe('/docs/referencia/estados#location-blocked');
    expect(helpUrl('permission.warehouses.view_all')).toBe('/docs/referencia/permisos#warehouses-view-all');
    expect(helpUrl('home')).toBe('/docs/');
  });

  it('reconoce solo las claves declaradas', () => {
    expect(isHelpKey('field.SU3.lgn')).toBe(true);
    expect(isHelpKey('message.UNKNOWN')).toBe(false);
    expect(isHelpKey('toString')).toBe(false);
    expect(Object.keys(helpLinks)).not.toContain('message.REVERSAL_EXCEEDS_REMAINING');
  });
});

describe('resolveHelpKey', () => {
  it('prioriza la clave explícita más cercana', () => {
    const focused = element(
      '<div data-help-field="lgn"><a data-help-key="state.location.blocked" href="#">x</a></div>',
      'a',
    );

    expect(resolveHelpKey(focused, su3)).toBe('state.location.blocked');
  });

  it('usa el campo de la transacción abierta', () => {
    const focused = element('<div data-help-field="lgn"><input /></div>', 'input');

    expect(resolveHelpKey(focused, su3)).toBe('field.SU3.lgn');
  });

  it('salta una clave explícita inexistente y un campo sin ayuda hasta la transacción', () => {
    const focused = element('<div data-help-field="other"><input data-help-key="error.NOPE" /></div>', 'input');

    expect(resolveHelpKey(focused, su3)).toBe('transaction.SU3');
  });

  it('fuera de una transacción abre el inicio de la wiki', () => {
    const focused = element('<div data-help-field="lgn"><input /></div>', 'input');

    expect(resolveHelpKey(focused, undefined)).toBe('home');
    expect(resolveHelpKey(null, undefined)).toBe('home');
  });
});
