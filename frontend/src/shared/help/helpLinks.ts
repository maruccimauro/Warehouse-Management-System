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
//   mensaje      referencia/mensajes#code           message.WAREHOUSE_INACTIVE
//   permiso      referencia/permisos#nombre         permission.locations.block

export const wikiBasePath = '/docs/';

const su3Page = 'primeros-pasos/parametros-de-usuario';
const zw01Page = 'administracion/depositos';
const zw02Page = 'administracion/zonas';
const ls01nPage = 'administracion/crear-ubicacion';
const ls02nPage = 'administracion/modificar-ubicacion';
const ls03nPage = 'administracion/visualizar-ubicacion';
const ls10Page = 'administracion/crear-multiples-ubicaciones';
const lx03Page = 'administracion/lista-de-ubicaciones';
const zw05Page = 'supervisor/bloqueo-de-ubicaciones';
const mm01Page = 'administracion/crear-catalogo';
const mm02Page = 'administracion/modificar-catalogo';
const mm03Page = 'administracion/visualizar-catalogo';
const mm60Page = 'administracion/lista-del-catalogo';
const xk01Page = 'compras/crear-proveedor';
const xk02Page = 'compras/modificar-proveedor';
const xk03Page = 'compras/visualizar-proveedor';
const zw06Page = 'compras/catalogo-del-proveedor';
const zw11Page = 'administracion/crear-deposito';
const zw12Page = 'administracion/modificar-deposito';
const zw13Page = 'administracion/visualizar-deposito';
const zw21Page = 'administracion/crear-zona';
const zw22Page = 'administracion/modificar-zona';
const zw23Page = 'administracion/visualizar-zona';
const zw61Page = 'compras/crear-articulo-del-proveedor';
const zw62Page = 'compras/modificar-articulo-del-proveedor';
const zw63Page = 'compras/visualizar-articulo-del-proveedor';
const zw07Page = 'administracion/categorias';
const zw71Page = 'administracion/crear-categoria';
const zw72Page = 'administracion/modificar-categoria';
const zw73Page = 'administracion/visualizar-categoria';
const zw08Page = 'administracion/unidades-de-medida';
const zw81Page = 'administracion/crear-unidad-de-medida';
const zw82Page = 'administracion/modificar-unidad-de-medida';
const zw83Page = 'administracion/visualizar-unidad-de-medida';
const su01Page = 'administracion/usuarios';
const zw91Page = 'administracion/crear-usuario';
const zw92Page = 'administracion/modificar-usuario';
const zw93Page = 'administracion/visualizar-usuario';
const suimPage = 'administracion/lista-de-usuarios';
const pfcgPage = 'administracion/visualizar-rol';

// Campos de la ficha de ubicación: la misma ayuda en crear, modificar y visualizar (la wiki la
// incluye desde un solo archivo en las tres páginas, D6 de E2.12).
const locationFields = ['warehouse', 'code', 'zone', 'status', 'aisle', 'rack', 'level', 'position', 'maxUnits'] as const;

// Campos de la ficha de producto y de proveedor (E2.13), incluidos desde un fragmento en las
// páginas de su trío, como los de ubicación.
const productFields = [
  'sku',
  'warehouse',
  'name',
  'description',
  'status',
  'barcode',
  'category',
  'baseUom',
  'weightGrams',
  'lengthMm',
  'widthMm',
  'heightMm',
  'minStock',
  'adjustmentInThreshold',
  'adjustmentOutThreshold',
] as const;
const supplierFields = ['number', 'taxId', 'name', 'status', 'email', 'phone'] as const;
// Campos de la ficha de usuario (E2.14), incluidos desde un fragmento en SU01 y ZW91 a ZW93.
const userFields = ['username', 'name', 'email', 'status', 'passwordStatus', 'password', 'passwordRepeat', 'roles', 'warehouses'] as const;

function fieldLinks<const Code extends string, const Field extends string>(code: Code, page: string, fields: readonly Field[]) {
  return Object.fromEntries(fields.map((field) => [`field.${code}.${field}`, `${page}#${anchor(field)}`])) as Record<
    `field.${Code}.${Field}`,
    string
  >;
}

// Códigos de error documentados en referencia/mensajes: los que la aplicación puede mostrar
// hoy, del backend (ADR-010) y del cliente (NETWORK_ERROR, INVALID_RESPONSE).
const documentedMessageCodes = [
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
  'CONCURRENCY_CONFLICT',
  'INTERNAL_ERROR',
  'INVALID_RESPONSE',
  'NOT_FOUND',
  'IDEMPOTENCY_REQUEST_IN_PROGRESS',
  'IDEMPOTENCY_KEY_REQUIRED',
  'IDEMPOTENCY_KEY_REUSED',
  'BAD_REQUEST',
  'METHOD_NOT_ALLOWED',
  'CONFLICT',
  'PAYLOAD_TOO_LARGE',
  'UNSUPPORTED_MEDIA_TYPE',
  'HTTP_ERROR',
  // Mensajes del cliente (E2.12): la pantalla los emite antes de llamar al servidor.
  'REQUIRED_FIELDS_MISSING',
  'VALUE_NOT_FOUND',
  'INTEGER_EXPECTED',
  'RANGE_LOW_MISSING',
  'RANGE_INVERTED',
  'PATTERN_IN_RANGE',
  'GRID_EXPORT_TRUNCATED',
  'LOCATION_NOT_FOUND',
  'LOCATION_BATCH_TOO_LARGE',
  'PRODUCT_NOT_FOUND',
  'SUPPLIER_NOT_FOUND',
  'SUPPLIER_PRODUCT_NOT_FOUND',
  'WAREHOUSE_SETTING_NOT_SAVED',
  'USER_NOT_FOUND',
  'PASSWORD_MISMATCH',
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
  'users.view',
  'users.manage',
] as const;

type DocumentedMessageCode = (typeof documentedMessageCodes)[number];
type DocumentedStateKey = {
  [Entity in keyof typeof documentedStates]: `state.${Entity}.${(typeof documentedStates)[Entity][number]}`;
}[keyof typeof documentedStates];
type DocumentedPermission = (typeof documentedPermissions)[number];

// Ancla en minúsculas con guiones: WAREHOUSE_INACTIVE -> warehouse-inactive,
// warehouses.view_all -> warehouses-view-all.
// maxUnits -> max-units.
function anchor(value: string): string {
  return value
    .replaceAll(/([a-z])([A-Z])/g, '$1-$2')
    .toLowerCase()
    .replaceAll(/[._]/g, '-');
}

const fixedLinks = {
  home: '',
  'transaction.SU3': su3Page,
  'field.SU3.locale': `${su3Page}#locale`,
  'field.SU3.lgn': `${su3Page}#lgn`,
  'transaction.ZW01': zw01Page,
  'transaction.ZW02': zw02Page,
  'transaction.LS01N': ls01nPage,
  'transaction.LS02N': ls02nPage,
  'transaction.LS03N': ls03nPage,
  'transaction.LS10': ls10Page,
  'transaction.LX03': lx03Page,
  'transaction.ZW05': zw05Page,
  'transaction.MM01': mm01Page,
  'transaction.MM02': mm02Page,
  'transaction.MM03': mm03Page,
  'transaction.MM60': mm60Page,
  'transaction.XK01': xk01Page,
  'transaction.XK02': xk02Page,
  'transaction.XK03': xk03Page,
  'transaction.ZW06': zw06Page,
  'transaction.ZW11': zw11Page,
  'transaction.ZW12': zw12Page,
  'transaction.ZW13': zw13Page,
  'transaction.ZW21': zw21Page,
  'transaction.ZW22': zw22Page,
  'transaction.ZW23': zw23Page,
  'transaction.ZW61': zw61Page,
  'transaction.ZW62': zw62Page,
  'transaction.ZW63': zw63Page,
  'transaction.ZW07': zw07Page,
  'transaction.ZW71': zw71Page,
  'transaction.ZW72': zw72Page,
  'transaction.ZW73': zw73Page,
  'transaction.ZW08': zw08Page,
  'transaction.ZW81': zw81Page,
  'transaction.ZW82': zw82Page,
  'transaction.ZW83': zw83Page,
  'transaction.SU01': su01Page,
  'transaction.ZW91': zw91Page,
  'transaction.ZW92': zw92Page,
  'transaction.ZW93': zw93Page,
  'transaction.SUIM': suimPage,
  'transaction.PFCG': pfcgPage,
  ...fieldLinks('ZW01', zw01Page, ['code', 'name', 'address']),
  ...fieldLinks('ZW02', zw02Page, ['warehouse', 'code', 'name', 'type']),
  ...fieldLinks('LS01N', ls01nPage, locationFields),
  ...fieldLinks('LS02N', ls02nPage, locationFields),
  ...fieldLinks('LS03N', ls03nPage, locationFields),
  ...fieldLinks('LS10', ls10Page, [
    'warehouse',
    'zone',
    'aislesFrom',
    'aislesTo',
    'racksFrom',
    'racksTo',
    'levelsFrom',
    'levelsTo',
    'positionsFrom',
    'positionsTo',
    'maxUnits',
  ]),
  ...fieldLinks('LX03', lx03Page, ['warehouse', 'code']),
  ...fieldLinks('ZW05', zw05Page, ['warehouse', 'code', 'reason']),
  ...fieldLinks('MM01', mm01Page, productFields),
  ...fieldLinks('MM02', mm02Page, productFields),
  ...fieldLinks('MM03', mm03Page, productFields),
  ...fieldLinks('MM60', mm60Page, ['sku', 'name', 'barcode', 'category']),
  ...fieldLinks('XK01', xk01Page, supplierFields),
  ...fieldLinks('XK02', xk02Page, ['supplier', ...supplierFields]),
  ...fieldLinks('XK03', xk03Page, ['supplier', ...supplierFields]),
  ...fieldLinks('ZW06', zw06Page, ['supplier', 'product', 'supplierSku', 'leadTimeDays']),
  ...fieldLinks('ZW11', zw11Page, ['code', 'name', 'address']),
  ...fieldLinks('ZW12', zw12Page, ['warehouse', 'code', 'name', 'address']),
  ...fieldLinks('ZW13', zw13Page, ['warehouse', 'code', 'name', 'address']),
  ...fieldLinks('ZW21', zw21Page, ['warehouse', 'code', 'name', 'type']),
  ...fieldLinks('ZW22', zw22Page, ['warehouse', 'zone', 'code', 'name', 'type']),
  ...fieldLinks('ZW23', zw23Page, ['warehouse', 'zone', 'code', 'name', 'type']),
  ...fieldLinks('ZW61', zw61Page, ['supplier', 'product', 'supplierSku', 'leadTimeDays']),
  ...fieldLinks('ZW62', zw62Page, ['supplier', 'product', 'supplierSku', 'leadTimeDays']),
  ...fieldLinks('ZW63', zw63Page, ['supplier', 'product', 'supplierSku', 'leadTimeDays']),
  ...fieldLinks('ZW07', zw07Page, ['name', 'parent']),
  ...fieldLinks('ZW71', zw71Page, ['name', 'parent']),
  ...fieldLinks('ZW72', zw72Page, ['category', 'name', 'parent']),
  ...fieldLinks('ZW73', zw73Page, ['category', 'name', 'parent']),
  ...fieldLinks('ZW08', zw08Page, ['code', 'name']),
  ...fieldLinks('ZW81', zw81Page, ['code', 'name']),
  ...fieldLinks('ZW82', zw82Page, ['unit', 'code', 'name']),
  ...fieldLinks('ZW83', zw83Page, ['unit', 'code', 'name']),
  ...fieldLinks('SU01', su01Page, ['user', ...userFields]),
  ...fieldLinks('ZW91', zw91Page, userFields),
  ...fieldLinks('ZW92', zw92Page, ['user', ...userFields]),
  ...fieldLinks('ZW93', zw93Page, ['user', ...userFields]),
  ...fieldLinks('SUIM', suimPage, ['username', 'name', 'role', 'warehouse', 'status']),
  ...fieldLinks('PFCG', pfcgPage, ['role']),
} as const;

export type HelpKey =
  | keyof typeof fixedLinks
  | `message.${DocumentedMessageCode}`
  | DocumentedStateKey
  | `permission.${DocumentedPermission}`;

// Destino de cada clave, relativo a la base de la wiki: "página" o "página#ancla".
export const helpLinks: Readonly<Record<HelpKey, string>> = {
  ...fixedLinks,
  ...(Object.fromEntries(
    documentedMessageCodes.map((code) => [`message.${code}`, `referencia/mensajes#${anchor(code)}`]),
  ) as Record<`message.${DocumentedMessageCode}`, string>),
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
