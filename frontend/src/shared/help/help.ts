import { helpUrl, isHelpKey, type HelpKey } from './helpLinks';

// Nombre de la pestaña de ayuda: como la ventana del Performance Assistant de SAP, se
// reutiliza en cada F1 en lugar de abrir una pestaña nueva por consulta. La wiki es del
// mismo origen y propia, así que la referencia a esta ventana (window.opener) no expone
// nada que la wiki no tenga ya.
export const helpWindowName = 'wms-help';

// Atributos que declaran la ayuda en el DOM, leídos desde el elemento con foco:
//   data-help-key="state.location.blocked"  clave explícita
//   data-help-field="lgn"                   campo de la transacción abierta
export const helpKeyAttribute = 'data-help-key';
export const helpFieldAttribute = 'data-help-field';

// Transacción abierta: su código arma la clave de sus campos y helpKey es su página.
export interface HelpContext {
  code: string;
  helpKey: HelpKey;
}

// Ayuda del elemento con foco (D6 de E2.16), de lo más específico a lo más general: la
// clave explícita más cercana, el campo de la transacción, la transacción y el inicio de
// la wiki. Una clave declarada que no existe se salta en lugar de abrir una página rota.
export function resolveHelpKey(focused: Element | null, transaction: HelpContext | undefined): HelpKey {
  const explicit = focused?.closest(`[${helpKeyAttribute}]`)?.getAttribute(helpKeyAttribute);

  if (explicit != null && isHelpKey(explicit)) {
    return explicit;
  }

  if (transaction === undefined) {
    return 'home';
  }

  const field = focused?.closest(`[${helpFieldAttribute}]`)?.getAttribute(helpFieldAttribute);
  const fieldKey = `field.${transaction.code}.${field ?? ''}`;

  return field != null && isHelpKey(fieldKey) ? fieldKey : transaction.helpKey;
}

export function openHelp(key: HelpKey): void {
  window.open(helpUrl(key), helpWindowName);
}
