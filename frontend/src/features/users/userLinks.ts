// Parámetro de la URL con el que otra transacción abre una de usuarios en un registro: la lista
// de usuarios y la pestaña Usuarios de un rol abren la visualización con doble clic.
export const userLinkParams = { user: 'user' } as const;

export function userLink(code: 'ZW92' | 'ZW93', username: string): string {
  return `/t/${code}?${new URLSearchParams({ [userLinkParams.user]: username }).toString()}`;
}
