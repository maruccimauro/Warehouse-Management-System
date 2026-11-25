import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { SearchHelpField, type SearchHelpFieldProps } from '@/shared/ui/searchHelp/SearchHelpField';
import type { SearchHelp } from '@/shared/ui/searchHelp/searchHelpTypes';
import { normalizeTaxId } from '@/shared/ui/form/fieldRules';
import {
  fetchProducts,
  normalizeSku,
  fetchSuppliers,
  productsQueryKey,
  suppliersQueryKey,
  type Category,
  type Product,
  type Supplier,
  type Unit, normalizeSupplierNumber } from './catalogApi';
import { categoryPaths, useCategories, useUnits } from './catalogQueries';

type ProductFieldProps = Omit<SearchHelpFieldProps<Product>, 'searchHelp' | 'check'>;

// Campo Producto con F4 de servidor (E2.13, D2): el catálogo puede tener miles de productos.
// Restricciones: SKU y nombre por fragmento, código de barras exacto. El campo acepta el SKU;
// sin tabla de verificación, porque la pantalla lee el producto al continuar.
export function ProductField(props: ProductFieldProps) {
  const { t } = useTranslation('products');

  const searchHelp = useMemo(
    (): SearchHelp<Product> => ({
      title: t('productHelp.title'),
      columns: [
        { id: 'sku', header: t('fields.sku'), value: (row) => row.sku },
        { id: 'name', header: t('fields.name'), value: (row) => row.name },
        { id: 'barcode', header: t('fields.barcode'), value: (row) => row.barcode },
        { id: 'status', header: t('fields.status'), value: (row) => row.status, format: (value) => t(`statuses.${String(value) as Product['status']}`) },
      ],
      source: {
        kind: 'server',
        queryKey: [...productsQueryKey, 'help'],
        fetchPage: (params, signal) => fetchProducts({ ...params, sort: params.sort ?? { field: 'sku', desc: false } }, signal),
        filtersOf: (criteria) => ({
          ...(criteria['sku'] === undefined ? {} : { sku: [{ sign: 'I', option: 'CP', low: `*${normalizeSku(criteria['sku'])}*` }] }),
          ...(criteria['name'] === undefined ? {} : { name: criteria['name'] }),
          ...(criteria['barcode'] === undefined ? {} : { barcode: criteria['barcode'].trim() }),
        }),
      },
      criteria: ['sku', 'name', 'barcode'],
      valueOf: (row) => row.sku,
      descriptionOf: (row) => row.name,
      normalize: normalizeSku,
      invalidMessage: (sku) => t('messages.notFound', { sku }),
      exportName: 'productos',
    }),
    [t],
  );

  return <SearchHelpField {...props} check={false} searchHelp={searchHelp} />;
}

type SupplierFieldProps = Omit<SearchHelpFieldProps<Supplier>, 'searchHelp' | 'check'>;

// Campo Proveedor con F4 de servidor: por número, nombre (fragmento) o identificador fiscal
// (exacto, sin separadores). El campo acepta el número interno del proveedor, con o sin ceros.
export function SupplierField(props: SupplierFieldProps) {
  const { t } = useTranslation('suppliers');

  const searchHelp = useMemo(
    (): SearchHelp<Supplier> => ({
      title: t('supplierHelp.title'),
      columns: [
        { id: 'number', header: t('fields.number'), value: (row) => row.number },
        { id: 'name', header: t('fields.name'), value: (row) => row.name },
        { id: 'tax_id', header: t('fields.taxId'), value: (row) => row.tax_id },
        { id: 'is_active', header: t('fields.status'), value: (row) => (row.is_active ? 'active' : 'inactive'), format: (value) => t(`statuses.${String(value) as 'active' | 'inactive'}`) },
      ],
      source: {
        kind: 'server',
        queryKey: [...suppliersQueryKey, 'help'],
        fetchPage: (params, signal) => fetchSuppliers({ ...params, sort: params.sort ?? { field: 'name', desc: false } }, signal),
        filtersOf: (criteria) => ({
          ...(criteria['number'] === undefined ? {} : { number: normalizeSupplierNumber(criteria['number']) }),
          ...(criteria['name'] === undefined ? {} : { name: criteria['name'] }),
          ...(criteria['tax_id'] === undefined ? {} : { tax_id: normalizeTaxId(criteria['tax_id']) }),
        }),
      },
      criteria: ['number', 'name', 'tax_id'],
      valueOf: (row) => row.number,
      descriptionOf: (row) => row.name,
      normalize: normalizeSupplierNumber,
      invalidMessage: (number) => t('messages.notFound', { number }),
      exportName: 'proveedores',
    }),
    [t],
  );

  return <SearchHelpField {...props} check={false} searchHelp={searchHelp} />;
}

type CategoryRow = Category & { path: string };
type CategoryFieldProps = Omit<SearchHelpFieldProps<CategoryRow>, 'searchHelp'>;

// Campo Categoría con F4 local (D3): las categorías son pocas. Valor: la ruta desde la raíz,
// que identifica la categoría aunque otra rama tenga una del mismo nombre. Lo escrito se
// compara sin distinguir mayúsculas ni espacios alrededor de la barra.
export function CategoryField(props: CategoryFieldProps) {
  const { t } = useTranslation('products');
  const categories = useCategories();

  const searchHelp = useMemo((): SearchHelp<CategoryRow> => {
    const paths = categoryPaths(categories.data ?? []);
    const rows = (categories.data ?? [])
      .map((category) => ({ ...category, path: paths.get(category.id) ?? category.name }))
      .sort((left, right) => left.path.localeCompare(right.path, 'es'));
    const canonical = new Map(rows.map((row) => [comparable(row.path), row.path]));

    return {
      title: t('categoryHelp.title'),
      columns: [{ id: 'path', header: t('categoryHelp.path'), value: (row) => row.path }],
      source: { kind: 'local', rows, loading: categories.isPending },
      criteria: ['path'],
      valueOf: (row) => row.path,
      descriptionOf: () => '',
      normalize: (text) => canonical.get(comparable(text)) ?? text.trim(),
      invalidMessage: (path) => t('categoryHelp.invalid', { path }),
      exportName: 'categorias',
    };
  }, [t, categories.data, categories.isPending]);

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

function comparable(path: string): string {
  return path
    .split('/')
    .map((part) => part.trim())
    .join('/')
    .toLocaleLowerCase('es');
}

type UnitFieldProps = Omit<SearchHelpFieldProps<Unit>, 'searchHelp'>;

// Campo Unidad de medida con F4 local. Valor: el código de la unidad.
export function UnitField(props: UnitFieldProps) {
  const { t } = useTranslation('products');
  const units = useUnits();

  const searchHelp = useMemo(
    (): SearchHelp<Unit> => ({
      title: t('unitHelp.title'),
      columns: [
        { id: 'code', header: t('unitHelp.code'), value: (row) => row.code },
        { id: 'name', header: t('unitHelp.name'), value: (row) => row.name },
      ],
      source: { kind: 'local', rows: units.data ?? [], loading: units.isPending },
      criteria: ['code', 'name'],
      valueOf: (row) => row.code,
      descriptionOf: (row) => row.name,
      normalize: (text) => text.trim().toUpperCase(),
      invalidMessage: (code) => t('unitHelp.invalid', { code }),
      exportName: 'unidades',
    }),
    [t, units.data, units.isPending],
  );

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}
