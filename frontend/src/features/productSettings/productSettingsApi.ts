import { z } from 'zod';
import { productStatuses, warehouseSettingSchema, type WarehouseSetting } from '@/features/catalog/catalogApi';
import { api } from '@/shared/api/apiClient';
import { listPageSchema, listPath, type ListPage, type ListParams } from '@/shared/api/listing';

// Datos de los artículos en un depósito (DM-21), con dos responsables
// (.claude/rules/responsabilidades-por-transaccion.md): el stock mínimo lo mantiene el
// supervisor del depósito (catalog.min_stock.manage, ZW04) y los umbrales de aprobación de
// ajustes son un control central (inventory.thresholds.manage, ZW35). Un artículo sin fila en
// el depósito tiene los valores por defecto: stock mínimo 0 y sin umbrales.

export const warehouseProductSettingSchema = z.object({
  product_id: z.number().int(),
  sku: z.string(),
  name: z.string(),
  status: z.enum(productStatuses),
  warehouse_id: z.number().int(),
  min_stock: z.number().int(),
  adjustment_in_approval_threshold: z.number().int().nullable(),
  adjustment_out_approval_threshold: z.number().int().nullable(),
});

export type WarehouseProductSetting = z.infer<typeof warehouseProductSettingSchema>;

const settingPage = listPageSchema(warehouseProductSettingSchema);
const settingResponse = z.object({ data: warehouseSettingSchema }).transform((response) => response.data);

export const productSettingsQueryKey = ['product-settings'] as const;

export function warehouseProductSettingsQueryKey(warehouseId: number) {
  return [...productSettingsQueryKey, warehouseId] as const;
}

// Todos los artículos del catálogo con sus datos en el depósito, paginado y con los filtros de
// la lista del catálogo (sku como selección, name por fragmento, status).
export function fetchWarehouseProductSettings(
  warehouseId: number,
  params: ListParams,
  signal?: AbortSignal,
): Promise<ListPage<WarehouseProductSetting>> {
  return api.request('GET', listPath(`/warehouses/${String(warehouseId)}/product-settings`, params), { schema: settingPage, signal });
}

function settingsPath(productId: number, warehouseId: number): string {
  return `/products/${String(productId)}/warehouse-settings/${String(warehouseId)}`;
}

export function setMinStock(productId: number, warehouseId: number, minStock: number): Promise<WarehouseSetting> {
  return api.request('PUT', `${settingsPath(productId, warehouseId)}/min-stock`, { body: { min_stock: minStock }, schema: settingResponse });
}

// Es un PUT: se envían los dos umbrales y uno nulo es "sin umbral" (E3.05).
export interface AdjustmentThresholdsInput {
  adjustment_in_approval_threshold: number | null;
  adjustment_out_approval_threshold: number | null;
}

export function setAdjustmentThresholds(productId: number, warehouseId: number, input: AdjustmentThresholdsInput): Promise<WarehouseSetting> {
  return api.request('PUT', `${settingsPath(productId, warehouseId)}/adjustment-thresholds`, { body: input, schema: settingResponse });
}
