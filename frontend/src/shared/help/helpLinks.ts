// Claves de ayuda (DM-11, E2.16): cada una apunta a una página de la wiki de usuario y, si
// corresponde, a un ancla explícita ({#...}) que no cambia con el título. La wiki se sirve en
// /docs/ del mismo host. docs/wiki/scripts/check-help-links.ts verifica que cada destino
// exista en el CI: el archivo no importa nada en tiempo de ejecución para que Node lo
// ejecute sin compilar.
//
// Convenciones de ancla (skill generar-wiki-wms):
//   transacción  página propia                     transaction.SU3
//   campo        página de la transacción #campo   field.SU3.lgn
//   estado       referencia/estados#objeto-estado   state.location.blocked
//   error        referencia/errores#code            error.WAREHOUSE_INACTIVE
//   permiso      referencia/permisos#nombre         permission.locations.block

export const wikiBasePath = '/docs/';

const su3Page = 'primeros-pasos/parametros-de-usuario';

// Códigos de error documentados en referencia/errores: los que la aplicación puede mostrar
// hoy, del backend (ADR-010) y del cliente (NETWORK_ERROR, INVALID_RESPONSE).
const documentedErrorCodes = [
  'INVALID_CREDENTIALS',
  'USER_INACTIVE',
  'PASSWORD_CHANGE_REQUIRED',
  'UNAUTHENTICATED',
  'FORBIDDEN',
  'TOO_MANY_REQUESTS',
  'CSRF_TOKEN_MISMATCH',
  'SESSION_REQUIRED',
  'VALIDATION_FAILED',
  'VALUE_ALREADY_EXISTS',
  'INVALID_STATE_TRANSITION',
  'WAREHOUSE_INACTIVE',
  'CATEGORY_HIERARCHY_CYCLE',
  'CATEGORY_HIERARCHY_TOO_DEEP',
  'LAST_ADMINISTRATOR',
  'SELF_DEACTIVATION_NOT_ALLOWED',
  'NETWORK_ERROR',
  'SERVICE_UNAVAILABLE',
  'INTERNAL_ERROR',
  'INVALID_RESPONSE',
  'NOT_FOUND',
  'IDEMPOTENCY_REQUEST_IN_PROGRESS',
  'IDEMPOTENCY_KEY_REUSED',
  'BAD_REQUEST',
  'METHOD_NOT_ALLOWED',
  'CONFLICT',
  'PAYLOAD_TOO_LARGE',
  'UNSUPPORTED_MEDIA_TYPE',
  'HTTP_ERROR',
] as const;

const documentedStates = {
  warehouse: ['active', 'inactive'],
  location: ['active', 'blocked', 'counting', 'inactive'],
  product: ['active', 'discontinued', 'blocked'],
  supplier: ['active', 'inactive'],
  user: ['active', 'inactive'],
} as const;

const documentedPermissions = [
  'warehouses.view_all',
  'warehouses.operate_all',
  'warehouses.manage',
  'locations.manage',
  'locations.block',
  'catalog.manage',
  'suppliers.manage',
  'users.manage',
] as const;

type DocumentedErrorCode = (typeof documentedErrorCodes)[number];
type DocumentedStateKey = {
  [Entity in keyof typeof documentedStates]: `state.${Entity}.${(typeof documentedStates)[Entity][number]}`;
}[keyof typeof documentedStates];
type DocumentedPermission = (typeof documentedPermissions)[number];

// Ancla en minúsculas con guiones: WAREHOUSE_INACTIVE -> warehouse-inactive,
// warehouses.view_all -> warehouses-view-all.
function anchor(value: string): string {
  return value.toLowerCase().replaceAll(/[._]/g, '-');
}

const fixedLinks = {
  home: '',
  'transaction.SU3': su3Page,
  'field.SU3.locale': `${su3Page}#locale`,
  'field.SU3.lgn': `${su3Page}#lgn`,
} as const;

export type HelpKey =
  | keyof typeof fixedLinks
  | `error.${DocumentedErrorCode}`
  | DocumentedStateKey
  | `permission.${DocumentedPermission}`;

// Destino de cada clave, relativo a la base de la wiki: "página" o "página#ancla".
export const helpLinks: Readonly<Record<HelpKey, string>> = {
  ...fixedLinks,
  ...(Object.fromEntries(
    documentedErrorCodes.map((code) => [`error.${code}`, `referencia/errores#${anchor(code)}`]),
  ) as Record<`error.${DocumentedErrorCode}`, string>),
  ...(Object.fromEntries(
    Object.entries(documentedStates).flatMap(([entity, states]) =>
      states.map((state) => [`state.${entity}.${state}`, `referencia/estados#${entity}-${state}`]),
    ),
  ) as Record<DocumentedStateKey, string>),
  ...(Object.fromEntries(
    documentedPermissions.map((permission) => [`permission.${permission}`, `referencia/permisos#${anchor(permission)}`]),
  ) as Record<`permission.${DocumentedPermission}`, string>),
};

export function isHelpKey(value: string): value is HelpKey {
  return Object.hasOwn(helpLinks, value);
}

export function helpUrl(key: HelpKey): string {
  return `${wikiBasePath}${helpLinks[key]}`;
}
