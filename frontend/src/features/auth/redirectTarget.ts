import type { Location } from 'react-router';

export interface LoginRedirectState {
  from: string;
}

const homePath = '/';

// Destino después del login: la ruta protegida que se intentó abrir. Solo se aceptan rutas
// internas ("/algo", nunca "//otro-host" ni URLs absolutas) para no abrir una redirección.
export function redirectTargetFrom(state: unknown): string {
  if (typeof state !== 'object' || state === null || !('from' in state)) {
    return homePath;
  }

  const { from } = state;

  if (typeof from !== 'string' || !from.startsWith('/') || from.startsWith('//')) {
    return homePath;
  }

  return from;
}

export function loginRedirectState(location: Location): LoginRedirectState {
  return { from: `${location.pathname}${location.search}${location.hash}` };
}
