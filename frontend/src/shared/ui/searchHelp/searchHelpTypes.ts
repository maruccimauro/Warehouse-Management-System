import type { ListFilterValue, ListPage, ListParams } from '@/shared/api/listing';
import type { GridColumn } from '../grid/gridTypes';

// Fuente de servidor (E2.12): para las ayudas sobre miles de filas (ubicación, producto). Las
// restricciones se traducen a filtros del listado y la grilla pagina en el servidor. No hay
// tabla de verificación en el campo: la valida la pantalla al leer el registro, como la
// pantalla inicial de una transacción de modificar o visualizar.
export interface ServerSearchHelpSource<TData> {
  kind: 'server';
  // Clave base de caché; el diálogo agrega las restricciones.
  queryKey: readonly unknown[];
  fetchPage: (params: ListParams, signal: AbortSignal) => Promise<ListPage<TData>>;
  // Filtros del listado para las restricciones escritas (solo las no vacías).
  filtersOf: (criteria: Readonly<Record<string, string>>) => Record<string, ListFilterValue>;
}

// loading: las filas todavía no llegaron; el campo no rechaza lo escrito hasta tenerlas.
export type SearchHelpSource<TData> =
  | { kind: 'local'; rows: readonly TData[]; loading?: boolean }
  | ServerSearchHelpSource<TData>;

// Definición de una ayuda de búsqueda (F4, E2.17, ADR-016): el equivalente de la ayuda de
// búsqueda elemental de SAP, con sus campos de restricción, la lista de resultados y el campo
// que se devuelve a la pantalla. El campo que la usa la valida además como tabla de
// verificación: un valor escrito a mano tiene que existir entre las filas.
export interface SearchHelp<TData> {
  // Título del diálogo (por ejemplo, "Depósitos").
  title: string;
  columns: readonly GridColumn<TData>[];
  // Local: las filas están en el cliente (depósitos, zonas). Servidor: ver ServerSearchHelpSource.
  source: SearchHelpSource<TData>;
  // Ids de las columnas que se ofrecen como restricción, en ese orden. En modo local filtran
  // por "contiene" sobre el texto que muestra la columna, como la ALV; en modo servidor, según
  // filtersOf.
  criteria: readonly string[];
  // Valor que se devuelve al campo (el código del depósito, por ejemplo) y texto descriptivo
  // que se muestra a su derecha.
  valueOf: (row: TData) => string;
  descriptionOf: (row: TData) => string;
  // Normaliza lo escrito antes de validarlo (mayúsculas para los códigos). Sin ella, recorta
  // espacios.
  normalize?: (text: string) => string;
  // Mensaje E cuando lo escrito no existe entre las filas.
  invalidMessage: (value: string) => string;
  // Base del nombre del archivo si se exporta la lista.
  exportName: string;
}
