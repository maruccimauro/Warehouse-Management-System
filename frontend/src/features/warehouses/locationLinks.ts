// Parámetros de la URL con los que otra transacción abre una de ubicaciones en un registro: la
// lista de ubicaciones abre la visualización con doble clic.
export const locationLinkParams = { warehouse: 'deposito', location: 'ubicacion' } as const;

export function locationLink(code: 'LS02N' | 'LS03N' | 'ZW05', warehouse: string, location: string): string {
  const search = new URLSearchParams({ [locationLinkParams.warehouse]: warehouse, [locationLinkParams.location]: location });

  return `/t/${code}?${search.toString()}`;
}
