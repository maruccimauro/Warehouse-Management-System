import esAuth from './locales/es/auth.json';
import esCommon from './locales/es/common.json';
import esErrors from './locales/es/errors.json';
import esHome from './locales/es/home.json';
import esNotFound from './locales/es/notFound.json';
import esShell from './locales/es/shell.json';
import esSu3 from './locales/es/su3.json';
import esTransactions from './locales/es/transactions.json';

// Un namespace por pantalla y dos compartidos (common y errors). Agregar un idioma es
// agregar su carpeta en locales/ y registrarla acá y en languages: los componentes no cambian.
export const resources = {
  es: {
    auth: esAuth,
    common: esCommon,
    errors: esErrors,
    home: esHome,
    notFound: esNotFound,
    shell: esShell,
    su3: esSu3,
    transactions: esTransactions,
  },
} as const;

export type Language = keyof typeof resources;

export const defaultNS = 'common';
export const fallbackLanguage: Language = 'es';

// Convención regional usada por Intl para cada idioma: "es" a secas no agrupa miles
// en números de cuatro cifras, por eso se fija la variante regional.
export const languages: Record<Language, { intlLocale: string }> = {
  es: { intlLocale: 'es-AR' },
};

export const supportedLanguages = Object.keys(resources) as Language[];

export function isSupportedLanguage(value: string): value is Language {
  return Object.hasOwn(resources, value);
}
