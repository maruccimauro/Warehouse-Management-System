// Permisos con nomenclatura modulo.accion (ADR-009). El catálogo vive en el backend
// (App\Modules\Identity\Authorization\Permission) y llega en me.permissions; el frontend
// solo los usa para ocultar lo que el usuario no puede hacer: la autorización real es del
// backend.
export type PermissionName = `${string}.${string}`;

export function permissionSet(permissions: readonly string[]): ReadonlySet<string> {
  return new Set(permissions);
}
