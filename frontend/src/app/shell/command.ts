// Sintaxis del campo de comando de SAP GUI (ADR-016):
//   MIGO    abre la transacción en esta ventana
//   /nMIGO  sale de la transacción actual y abre MIGO en esta ventana
//   /oMIGO  abre MIGO en una ventana nueva
//   /n      sale de la transacción actual y vuelve al menú
//   /o      abre el menú en una ventana nueva
// Desviación: SAP exige /n para cambiar de transacción desde otra; acá el código solo alcanza.
export type Command =
  | { kind: 'empty' }
  | { kind: 'menu'; newWindow: boolean }
  | { kind: 'open'; code: string; newWindow: boolean }
  | { kind: 'invalid'; input: string };

const transactionCode = /^[A-Z][A-Z0-9_]{1,19}$/;

export function parseCommand(input: string): Command {
  const normalized = input.trim().toUpperCase();

  if (normalized === '') {
    return { kind: 'empty' };
  }

  const prefixed = /^\/([NO])(.*)$/.exec(normalized);
  const newWindow = prefixed?.[1] === 'O';
  const code = prefixed === null ? normalized : (prefixed[2] ?? '').trim();

  if (prefixed !== null && code === '') {
    return { kind: 'menu', newWindow };
  }

  if (!transactionCode.test(code)) {
    return { kind: 'invalid', input: input.trim() };
  }

  return { kind: 'open', code, newWindow };
}

export function transactionPath(code: string): string {
  return `/t/${code}`;
}

export function openInNewWindow(path: string): void {
  // noopener: la ventana nueva no recibe referencia a esta (window.opener).
  window.open(path, '_blank', 'noopener');
}
