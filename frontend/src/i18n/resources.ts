import esAuth from './locales/es/auth.json';
import esCategories from './locales/es/categories.json';
import esCommon from './locales/es/common.json';
import esErrors from './locales/es/errors.json';
import esForm from './locales/es/form.json';
import esGrid from './locales/es/grid.json';
import esHome from './locales/es/home.json';
import esLocations from './locales/es/locations.json';
import esNotFound from './locales/es/notFound.json';
import esProducts from './locales/es/products.json';
import esRoles from './locales/es/roles.json';
import esSearchHelp from './locales/es/searchHelp.json';
import esSelection from './locales/es/selection.json';
import esShell from './locales/es/shell.json';
import esSu3 from './locales/es/su3.json';
import esSuppliers from './locales/es/suppliers.json';
import esTransactions from './locales/es/transactions.json';
import esUnits from './locales/es/units.json';
import esUsers from './locales/es/users.json';
import esWarehouses from './locales/es/warehouses.json';
import esZones from './locales/es/zones.json';

// Un namespace por pantalla y dos compartidos (common y errors). Agregar un idioma es
// agregar su carpeta en locales/ y registrarla acá y en languages: los componentes no cambian.
export const resources = {
  es: {
    auth: esAuth,
    categories: esCategories,
    common: esCommon,
    errors: esErrors,
    form: esForm,
    grid: esGrid,
    home: esHome,
    locations: esLocations,
    notFound: esNotFound,
    products: esProducts,
    roles: esRoles,
    searchHelp: esSearchHelp,
    selection: esSelection,
    shell: esShell,
    su3: esSu3,
    suppliers: esSuppliers,
    transactions: esTransactions,
    units: esUnits,
    users: esUsers,
    warehouses: esWarehouses,
    zones: esZones,
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
