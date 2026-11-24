import { z } from 'zod';

// Convención de listados de la API (E2.10, ListRequest del backend): filter[campo]=valor,
// filter[campo][]=valor para selección múltiple, sort=campo o sort=-campo, page y per_page.
// Los campos admitidos son lista blanca del backend: uno no declarado responde 422.

export const maxPerPage = 200;

export interface ListSort {
  field: string;
  desc: boolean;
}

export interface ListParams {
  page: number;
  perPage: number;
  sort?: ListSort | undefined;
  // Un valor para los filtros simples; una lista para los de selección múltiple.
  filters: Readonly<Record<string, string | readonly string[]>>;
}

export interface ListPage<T> {
  items: T[];
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export function listSearchParams(params: ListParams): URLSearchParams {
  const search = new URLSearchParams();

  for (const [field, value] of Object.entries(params.filters)) {
    if (typeof value === 'string') {
      search.append(`filter[${field}]`, value);
    } else {
      for (const item of value) {
        search.append(`filter[${field}][]`, item);
      }
    }
  }

  if (params.sort !== undefined) {
    search.set('sort', `${params.sort.desc ? '-' : ''}${params.sort.field}`);
  }

  search.set('page', String(params.page));
  search.set('per_page', String(params.perPage));

  return search;
}

export function listPath(path: string, params: ListParams): string {
  return `${path}?${listSearchParams(params).toString()}`;
}

// Respuesta paginada de Laravel (paginate() con un API Resource): data, links y meta.
// Solo se valida lo que usa la grilla.
export function listPageSchema<S extends z.ZodType>(item: S) {
  return z
    .object({
      data: z.array(item),
      meta: z.object({
        current_page: z.number().int().min(1),
        last_page: z.number().int().min(1),
        per_page: z.number().int().min(0),
        total: z.number().int().min(0),
      }),
    })
    .transform(
      (response): ListPage<z.infer<S>> => ({
        items: response.data,
        page: response.meta.current_page,
        lastPage: response.meta.last_page,
        perPage: response.meta.per_page,
        total: response.meta.total,
      }),
    );
}
