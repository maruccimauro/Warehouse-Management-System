import 'i18next';
import type { defaultNS, resources } from './resources';

// Claves tipadas: t('clave.inexistente') es un error de compilación.
declare module 'i18next' {
  interface CustomTypeOptions {
    defaultNS: typeof defaultNS;
    resources: (typeof resources)['es'];
  }
}
