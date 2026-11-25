// Parámetro de la URL con el que otra transacción abre una de productos en un registro: la
// lista de productos abre la visualización con doble clic.
export const productLinkParams = { sku: 'sku' } as const;

export function productLink(code: 'MM02' | 'MM03', sku: string): string {
  return `/t/${code}?${new URLSearchParams({ [productLinkParams.sku]: sku }).toString()}`;
}
