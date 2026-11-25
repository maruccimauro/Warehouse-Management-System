import { z } from 'zod';
import { api } from '@/shared/api/apiClient';
import { listPageSchema, listPath, maxPerPage, type ListPage, type ListParams } from '@/shared/api/listing';

// Depósitos, zonas y ubicaciones (módulo Warehouse del backend, E2.02 a E2.04). Los códigos se
// guardan en mayúsculas (NormalizesCodes): el cliente normaliza igual antes de comparar.

// Los códigos se guardan en mayúsculas (NormalizesCodes del backend).
export function normalizeCode(text: string): string {
  return text.trim().toUpperCase();
}

export const zoneTypes = ['receiving', 'storage', 'picking', 'packing', 'dispatch', 'quarantine'] as const;
export const locationStatuses = ['active', 'blocked', 'counting', 'inactive'] as const;
export const systemLocationRoles = ['receiving', 'packing', 'dispatch'] as const;

export type ZoneType = (typeof zoneTypes)[number];
export type LocationStatus = (typeof locationStatuses)[number];

// Límites que valida el backend y que la pantalla anticipa (GenerateLocations, LocationGrid).
export const maxGeneratedLocations = 2000;
export const maxGridNumber = 99;
export const addressMaxLength = 500;
export const blockReasonMaxLength = 255;

export const warehouseSchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name: z.string(),
  address: z.string().nullable(),
  is_active: z.boolean(),
});

export const zoneSchema = z.object({
  id: z.number().int(),
  warehouse_id: z.number().int(),
  code: z.string(),
  name: z.string(),
  type: z.enum(zoneTypes),
  is_system: z.boolean(),
});

export const locationSchema = z.object({
  id: z.number().int(),
  warehouse_id: z.number().int(),
  zone_id: z.number().int(),
  code: z.string(),
  aisle: z.string().nullable(),
  rack: z.string().nullable(),
  level: z.string().nullable(),
  position: z.string().nullable(),
  status: z.enum(locationStatuses),
  status_reason: z.string().nullable(),
  max_units: z.number().int().nullable(),
  system_role: z.enum(systemLocationRoles).nullable(),
  is_system: z.boolean(),
});

export type Warehouse = z.infer<typeof warehouseSchema>;
export type Zone = z.infer<typeof zoneSchema>;
export type Location = z.infer<typeof locationSchema>;

const warehousePage = listPageSchema(warehouseSchema);
const zonePage = listPageSchema(zoneSchema);
const locationPage = listPageSchema(locationSchema);
const warehouseResponse = z.object({ data: warehouseSchema }).transform((response) => response.data);
const zoneResponse = z.object({ data: zoneSchema }).transform((response) => response.data);
const locationResponse = z.object({ data: locationSchema }).transform((response) => response.data);
const generatedResponse = z.object({ data: z.object({ created: z.number().int() }) }).transform((response) => response.data.created);

export const warehousesQueryKey = ['warehouses'] as const;

export function zonesQueryKey(warehouseId: number) {
  return ['warehouses', warehouseId, 'zones'] as const;
}

export function locationsQueryKey(warehouseId: number) {
  return ['warehouses', warehouseId, 'locations'] as const;
}

// Depósitos.

export function fetchWarehouses(params: ListParams, signal?: AbortSignal): Promise<ListPage<Warehouse>> {
  return api.request('GET', listPath('/warehouses', params), { schema: warehousePage, signal });
}

// Todos los depósitos visibles (alcance de consulta, REG-005). Son pocos: una página de 200
// alcanza; más que eso ya pediría una ayuda con fuente de servidor.
export async function fetchAllWarehouses(signal?: AbortSignal): Promise<Warehouse[]> {
  const page = await fetchWarehouses({ page: 1, perPage: maxPerPage, sort: { field: 'code', desc: false }, filters: {} }, signal);

  return page.items;
}

export interface WarehouseInput {
  name: string;
  address: string | null;
}

export function createWarehouse(input: WarehouseInput & { code: string }): Promise<Warehouse> {
  return api.request('POST', '/warehouses', { body: input, schema: warehouseResponse });
}

export function updateWarehouse(id: number, input: Partial<WarehouseInput> & { is_active?: boolean }): Promise<Warehouse> {
  return api.request('PATCH', `/warehouses/${String(id)}`, { body: input, schema: warehouseResponse });
}

// Zonas.

export function fetchZones(warehouseId: number, params: ListParams, signal?: AbortSignal): Promise<ListPage<Zone>> {
  return api.request('GET', listPath(`/warehouses/${String(warehouseId)}/zones`, params), { schema: zonePage, signal });
}

export async function fetchAllZones(warehouseId: number, signal?: AbortSignal): Promise<Zone[]> {
  const page = await fetchZones(warehouseId, { page: 1, perPage: maxPerPage, sort: { field: 'code', desc: false }, filters: {} }, signal);

  return page.items;
}

export interface ZoneInput {
  name: string;
  type: ZoneType;
}

export function createZone(warehouseId: number, input: ZoneInput & { code: string }): Promise<Zone> {
  return api.request('POST', `/warehouses/${String(warehouseId)}/zones`, { body: input, schema: zoneResponse });
}

export function updateZone(id: number, input: Partial<ZoneInput>): Promise<Zone> {
  return api.request('PATCH', `/zones/${String(id)}`, { body: input, schema: zoneResponse });
}

// Ubicaciones.

export function fetchLocations(warehouseId: number, params: ListParams, signal?: AbortSignal): Promise<ListPage<Location>> {
  return api.request('GET', listPath(`/warehouses/${String(warehouseId)}/locations`, params), { schema: locationPage, signal });
}

// Lee una ubicación por código, como la pantalla inicial de modificar o visualizar.
export async function findLocation(warehouseId: number, code: string, signal?: AbortSignal): Promise<Location | undefined> {
  const page = await fetchLocations(
    warehouseId,
    { page: 1, perPage: 1, filters: { code: [{ sign: 'I', option: 'EQ', low: code }] } },
    signal,
  );

  return page.items[0];
}

export interface LocationInput {
  zone_id?: number;
  aisle?: string | null;
  rack?: string | null;
  level?: string | null;
  position?: string | null;
  max_units?: number | null;
  status?: 'active' | 'inactive';
}

export function createLocation(warehouseId: number, input: LocationInput & { code: string; zone_id: number }): Promise<Location> {
  return api.request('POST', `/warehouses/${String(warehouseId)}/locations`, { body: input, schema: locationResponse });
}

export function updateLocation(id: number, input: LocationInput): Promise<Location> {
  return api.request('PATCH', `/locations/${String(id)}`, { body: input, schema: locationResponse });
}

export interface LocationTemplate {
  zone_id: number;
  aisles: { from: string; to: string };
  racks: { from: number; to: number };
  levels: { from: number; to: number };
  positions?: { from: number; to: number } | null;
  max_units?: number | null;
}

export function generateLocations(warehouseId: number, template: LocationTemplate): Promise<number> {
  return api.request('POST', `/warehouses/${String(warehouseId)}/locations/bulk`, { body: template, schema: generatedResponse });
}

// Cantidad que genera la plantilla, la misma cuenta que LocationGrid::count() del backend.
export function templateSize(template: Pick<LocationTemplate, 'aisles' | 'racks' | 'levels' | 'positions'>): number {
  const span = (from: number, to: number) => Math.max(0, to - from + 1);
  const aisles = span(template.aisles.from.charCodeAt(0), template.aisles.to.charCodeAt(0));
  const positions = template.positions == null ? 1 : span(template.positions.from, template.positions.to);

  return aisles * span(template.racks.from, template.racks.to) * span(template.levels.from, template.levels.to) * positions;
}

export function blockLocation(id: number, reason: string): Promise<Location> {
  return api.request('POST', `/locations/${String(id)}/block`, { body: { reason }, schema: locationResponse });
}

export function unblockLocation(id: number): Promise<Location> {
  return api.request('POST', `/locations/${String(id)}/unblock`, { schema: locationResponse });
}
