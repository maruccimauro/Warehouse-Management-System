import { z } from 'zod';
import { api } from '@/shared/api/apiClient';
import { listPageSchema, listPath, maxPerPage, type ListPage, type ListParams } from '@/shared/api/listing';

// Productos, categorías, unidades de medida y proveedores (módulo Catalog del backend, E2.05 y
// E2.06). El SKU se guarda en mayúsculas (NormalizesCodes); el identificador fiscal, sin
// separadores (Supplier::normalizeTaxId).

// Los SKU se guardan en mayúsculas (NormalizesCodes del backend).
export function normalizeSku(text: string): string {
  return text.trim().toUpperCase();
}

export const productStatuses = ['active', 'discontinued', 'blocked'] as const;
export type ProductStatus = (typeof productStatuses)[number];

// Límites que valida el backend y que la pantalla anticipa.
export const skuMaxLength = 40;
export const productNameMaxLength = 200;
export const descriptionMaxLength = 2000;
export const barcodeMaxLength = 64;
export const supplierNameMaxLength = 200;
export const emailMaxLength = 255;
export const phoneMaxLength = 40;
export const supplierSkuMaxLength = 60;
export const leadTimeMaxDays = 365;
export const dimensionMax = 2147483647;
// Cantidad entera dentro del rango exacto de un number de JavaScript (ADR-005).
export const maxQuantity = Number.MAX_SAFE_INTEGER;

export const productSchema = z.object({
  id: z.number().int(),
  sku: z.string(),
  barcode: z.string().nullable(),
  name: z.string(),
  description: z.string().nullable(),
  category_id: z.number().int().nullable(),
  base_uom_id: z.number().int(),
  weight_grams: z.number().int().nullable(),
  length_mm: z.number().int().nullable(),
  width_mm: z.number().int().nullable(),
  height_mm: z.number().int().nullable(),
  status: z.enum(productStatuses),
  is_lot_tracked: z.boolean(),
});

export const categorySchema = z.object({
  id: z.number().int(),
  parent_id: z.number().int().nullable(),
  name: z.string(),
});

export const unitSchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name: z.string(),
});

export const warehouseSettingSchema = z.object({
  product_id: z.number().int(),
  warehouse_id: z.number().int(),
  min_stock: z.number().int(),
});

// Diez dígitos, como InternalNumber en el backend.
const SUPPLIER_NUMBER_LENGTH = 10;

// Número interno del proveedor tal como lo muestra la API (0000000001): lo escrito con o sin
// ceros a la izquierda se lleva a ese formato.
export function normalizeSupplierNumber(text: string): string {
  const trimmed = text.trim();

  return /^\d+$/.test(trimmed) ? trimmed.replace(/^0+(?=\d)/, '').padStart(SUPPLIER_NUMBER_LENGTH, '0') : trimmed;
}

export const supplierSchema = z.object({
  id: z.number().int(),
  number: z.string(),
  tax_id: z.string(),
  name: z.string(),
  email: z.string().nullable(),
  phone: z.string().nullable(),
  is_active: z.boolean(),
});

// Vínculo producto proveedor (DM-22) con el resumen del otro extremo que traen los listados.
export const productSupplierSchema = z.object({
  product_id: z.number().int(),
  supplier_id: z.number().int(),
  product: z.object({ sku: z.string(), name: z.string(), status: z.enum(productStatuses) }).optional(),
  supplier: z.object({ number: z.string(), tax_id: z.string(), name: z.string(), is_active: z.boolean() }).optional(),
  supplier_sku: z.string().nullable(),
  lead_time_days: z.number().int().nullable(),
});

export type Product = z.infer<typeof productSchema>;
export type Category = z.infer<typeof categorySchema>;
export type Unit = z.infer<typeof unitSchema>;
export type WarehouseSetting = z.infer<typeof warehouseSettingSchema>;
export type Supplier = z.infer<typeof supplierSchema>;
export type ProductSupplier = z.infer<typeof productSupplierSchema>;

const productPage = listPageSchema(productSchema);
const categoryPage = listPageSchema(categorySchema);
const unitPage = listPageSchema(unitSchema);
const supplierPage = listPageSchema(supplierSchema);
const productSupplierPage = listPageSchema(productSupplierSchema);
const productResponse = z.object({ data: productSchema }).transform((response) => response.data);
const supplierResponse = z.object({ data: supplierSchema }).transform((response) => response.data);
const warehouseSettingResponse = z.object({ data: warehouseSettingSchema }).transform((response) => response.data);
const warehouseSettingList = z.object({ data: z.array(warehouseSettingSchema) }).transform((response) => response.data);
const productSupplierResponse = z.object({ data: productSupplierSchema }).transform((response) => response.data);
const productSupplierList = z.object({ data: z.array(productSupplierSchema) }).transform((response) => response.data);

export const productsQueryKey = ['products'] as const;
export const suppliersQueryKey = ['suppliers'] as const;
export const categoriesQueryKey = ['categories'] as const;
export const unitsQueryKey = ['units'] as const;

export function productDetailQueryKey(productId: number) {
  return ['products', productId] as const;
}

export function supplierProductsQueryKey(supplierId: number) {
  return ['suppliers', supplierId, 'products'] as const;
}

// Productos.

export function fetchProducts(params: ListParams, signal?: AbortSignal): Promise<ListPage<Product>> {
  return api.request('GET', listPath('/products', params), { schema: productPage, signal });
}

// Lee un producto por SKU, como la pantalla inicial de modificar o visualizar.
export async function findProduct(sku: string, signal?: AbortSignal): Promise<Product | undefined> {
  const page = await fetchProducts({ page: 1, perPage: 1, filters: { sku: [{ sign: 'I', option: 'EQ', low: sku }] } }, signal);

  return page.items[0];
}

export interface ProductInput {
  name?: string;
  barcode?: string | null;
  description?: string | null;
  category_id?: number | null;
  weight_grams?: number | null;
  length_mm?: number | null;
  width_mm?: number | null;
  height_mm?: number | null;
  status?: ProductStatus;
}

export function createProduct(input: ProductInput & { sku: string; name: string; base_uom_id: number }): Promise<Product> {
  return api.request('POST', '/products', { body: input, schema: productResponse });
}

export function updateProduct(id: number, input: ProductInput): Promise<Product> {
  return api.request('PATCH', `/products/${String(id)}`, { body: input, schema: productResponse });
}

// Datos del producto en los depósitos que el usuario consulta (DM-21); a lo sumo uno por depósito.
export function fetchWarehouseSettings(productId: number, signal?: AbortSignal): Promise<WarehouseSetting[]> {
  return api.request('GET', `/products/${String(productId)}/warehouse-settings`, { schema: warehouseSettingList, signal });
}

export function setWarehouseSetting(productId: number, warehouseId: number, minStock: number): Promise<WarehouseSetting> {
  return api.request('PUT', `/products/${String(productId)}/warehouse-settings/${String(warehouseId)}`, {
    body: { min_stock: minStock },
    schema: warehouseSettingResponse,
  });
}

export function fetchProductSuppliers(productId: number, signal?: AbortSignal): Promise<ProductSupplier[]> {
  return api.request('GET', `/products/${String(productId)}/suppliers`, { schema: productSupplierList, signal });
}

// Categorías y unidades: pocas filas, se leen enteras para las ayudas locales. Una página de
// 200 alcanza; más que eso pediría una ayuda con fuente de servidor.

export async function fetchAllCategories(signal?: AbortSignal): Promise<Category[]> {
  const page = await api.request('GET', listPath('/categories', { page: 1, perPage: maxPerPage, filters: {} }), { schema: categoryPage, signal });

  return page.items;
}

export async function fetchAllUnits(signal?: AbortSignal): Promise<Unit[]> {
  const page = await api.request('GET', listPath('/units', { page: 1, perPage: maxPerPage, filters: {} }), { schema: unitPage, signal });

  return page.items;
}

// Mantenimiento de categorías y unidades (ZW07 y ZW08, E2.23) con catalog.manage. La
// profundidad y los ciclos del árbol los valida el backend (CATEGORY_HIERARCHY_TOO_DEEP y
// CATEGORY_HIERARCHY_CYCLE); el código de la unidad no cambia después del alta.
export const categoryNameMaxLength = 120;
export const unitCodeMaxLength = 10;
export const unitNameMaxLength = 60;

const categoryResponse = z.object({ data: categorySchema }).transform((response) => response.data);
const unitResponse = z.object({ data: unitSchema }).transform((response) => response.data);

export interface CategoryInput {
  name: string;
  parent_id: number | null;
}

export function createCategory(input: CategoryInput): Promise<Category> {
  return api.request('POST', '/categories', { body: input, schema: categoryResponse });
}

export function updateCategory(id: number, input: CategoryInput): Promise<Category> {
  return api.request('PATCH', `/categories/${String(id)}`, { body: input, schema: categoryResponse });
}

export function fetchUnits(params: ListParams, signal?: AbortSignal): Promise<ListPage<Unit>> {
  return api.request('GET', listPath('/units', params), { schema: unitPage, signal });
}

export function createUnit(input: { code: string; name: string }): Promise<Unit> {
  return api.request('POST', '/units', { body: input, schema: unitResponse });
}

export function updateUnit(id: number, input: { name: string }): Promise<Unit> {
  return api.request('PATCH', `/units/${String(id)}`, { body: input, schema: unitResponse });
}

// Proveedores.

export function fetchSuppliers(params: ListParams, signal?: AbortSignal): Promise<ListPage<Supplier>> {
  return api.request('GET', listPath('/suppliers', params), { schema: supplierPage, signal });
}

export function fetchSupplier(id: number, signal?: AbortSignal): Promise<Supplier> {
  return api.request('GET', `/suppliers/${String(id)}`, { schema: supplierResponse, signal });
}

// Lee un proveedor por identificador fiscal (ya normalizado), como la pantalla inicial.
export async function findSupplier(number: string, signal?: AbortSignal): Promise<Supplier | undefined> {
  const page = await fetchSuppliers({ page: 1, perPage: 1, filters: { number } }, signal);

  return page.items[0];
}

export interface SupplierInput {
  tax_id?: string;
  name?: string;
  email?: string | null;
  phone?: string | null;
  is_active?: boolean;
}

export function createSupplier(input: SupplierInput & { tax_id: string; name: string }): Promise<Supplier> {
  return api.request('POST', '/suppliers', { body: input, schema: supplierResponse });
}

export function updateSupplier(id: number, input: SupplierInput): Promise<Supplier> {
  return api.request('PATCH', `/suppliers/${String(id)}`, { body: input, schema: supplierResponse });
}

export function fetchSupplierProducts(supplierId: number, params: ListParams, signal?: AbortSignal): Promise<ListPage<ProductSupplier>> {
  return api.request('GET', listPath(`/suppliers/${String(supplierId)}/products`, params), { schema: productSupplierPage, signal });
}

export interface ProductSupplierInput {
  supplier_sku: string | null;
  lead_time_days: number | null;
}

// PUT reemplaza el vínculo completo: crea o modifica.
export function setProductSupplier(supplierId: number, productId: number, input: ProductSupplierInput): Promise<ProductSupplier> {
  return api.request('PUT', `/suppliers/${String(supplierId)}/products/${String(productId)}`, {
    body: input,
    schema: productSupplierResponse,
  });
}

export function removeProductSupplier(supplierId: number, productId: number): Promise<void> {
  return api.request('DELETE', `/suppliers/${String(supplierId)}/products/${String(productId)}`);
}
