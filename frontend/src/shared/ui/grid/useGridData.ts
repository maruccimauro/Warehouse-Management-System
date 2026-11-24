import { keepPreviousData, skipToken, useQuery } from '@tanstack/react-query';
import { maxPerPage, type ListPage, type ListParams } from '@/shared/api/listing';
import type { GridSource } from './gridTypes';

// Tope de la exportación en modo servidor (D5 de E2.00): 25 páginas de 200. Más que eso pide
// un endpoint CSV con streaming en el backend, que llega con el libro de movimientos de E3.
export const maxExportRows = 5000;

export interface GridData<TData> {
  rows: readonly TData[];
  // Total del servidor; en modo local, las filas recibidas.
  total: number;
  lastPage: number;
  isLoading: boolean;
  isFetching: boolean;
  error: unknown;
  refetch: () => void;
}

const noRows: readonly never[] = [];

export function useGridData<TData>(source: GridSource<TData>, params: ListParams): GridData<TData> {
  const server = source.kind === 'server' ? source : undefined;
  const query = useQuery({
    queryKey: [...(server?.queryKey ?? ['grid', 'local']), 'list', params],
    queryFn: server === undefined ? skipToken : ({ signal }) => server.fetchPage(params, signal),
    // Al cambiar de página u orden se mantiene la página anterior hasta que llega la nueva: la
    // grilla no parpadea ni pierde la posición del foco.
    placeholderData: keepPreviousData,
  });

  if (source.kind === 'local') {
    return {
      rows: source.rows,
      total: source.rows.length,
      lastPage: 1,
      isLoading: false,
      isFetching: false,
      error: null,
      refetch: () => undefined,
    };
  }

  return {
    rows: query.data?.items ?? noRows,
    total: query.data?.total ?? 0,
    lastPage: query.data?.lastPage ?? 1,
    isLoading: query.isPending,
    isFetching: query.isFetching,
    error: query.error,
    refetch: () => {
      void query.refetch();
    },
  };
}

export interface ExportResult<TData> {
  rows: TData[];
  total: number;
}

// Recorre las páginas con el mismo orden y filtros que se ven, hasta el tope. Las peticiones
// van en serie: no compiten con la sesión del usuario ni saturan el backend.
export async function fetchAllPages<TData>(
  fetchPage: Extract<GridSource<TData>, { kind: 'server' }>['fetchPage'],
  params: Omit<ListParams, 'page' | 'perPage'>,
  signal: AbortSignal,
): Promise<ExportResult<TData>> {
  const rows: TData[] = [];
  let page = 1;
  let result: ListPage<TData>;

  do {
    result = await fetchPage({ ...params, page, perPage: maxPerPage }, signal);
    rows.push(...result.items);
    page += 1;
  } while (page <= result.lastPage && rows.length < maxExportRows);

  return { rows: rows.slice(0, maxExportRows), total: result.total };
}
