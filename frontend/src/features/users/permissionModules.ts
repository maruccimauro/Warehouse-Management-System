// Módulo de cada permiso por su prefijo (modulo.accion, ADR-009), en el orden del catálogo.
const moduleOfPrefix: Readonly<Record<string, string>> = {
  warehouses: 'warehouses',
  locations: 'warehouses',
  catalog: 'catalog',
  suppliers: 'catalog',
  users: 'users',
  inventory: 'inventory',
  procurement: 'procurement',
  receiving: 'procurement',
  putaway: 'procurement',
  orders: 'orders',
  picking: 'orders',
  packing: 'orders',
  dispatch: 'orders',
  stocktake: 'stocktake',
  audit: 'audit',
};
export const moduleOrder = ['warehouses', 'catalog', 'users', 'inventory', 'procurement', 'orders', 'stocktake', 'audit'];

export function permissionModules(permissions: readonly string[]): { module: string; permissions: string[] }[] {
  const groups = new Map<string, string[]>();

  for (const permission of permissions) {
    const module = moduleOfPrefix[permission.split('.')[0] ?? ''] ?? 'other';
    groups.set(module, [...(groups.get(module) ?? []), permission]);
  }

  return [...groups.entries()]
    .sort(([left], [right]) => moduleRank(left) - moduleRank(right))
    .map(([module, names]) => ({ module, permissions: names }));
}

function moduleRank(module: string): number {
  const index = moduleOrder.indexOf(module);

  return index === -1 ? moduleOrder.length : index;
}
