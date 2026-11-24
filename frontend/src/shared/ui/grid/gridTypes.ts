import type { ReactNode } from 'react';
import type { ListPage, ListParams } from '@/shared/api/listing';

// Tipos de la grilla ALV (E2.00, ADR-016). Una pantalla declara sus columnas con estos tipos y
// no con los de TanStack Table, que quedan como detalle interno del componente.

export type GridValue = string | number | boolean | null | undefined;

export type GridColumnType = 'text' | 'number' | 'date' | 'datetime';

// Cómo compara el filtro, igual que FilterMode del backend: contains es Partial, equals es
// Exact y in es la selección múltiple.
export type GridFilterOperator = 'contains' | 'equals' | 'in';

export interface GridOption {
  value: string;
  label: string;
}

export interface GridColumn<TData> {
  id: string;
  header: string;
  value: (row: TData) => GridValue;
  type?: GridColumnType;
  // Texto que se muestra y se exporta (por ejemplo, la etiqueta traducida de un estado). Sin
  // él, el valor se formatea con Intl según el tipo.
  format?: (value: GridValue, row: TData) => string;
  // Contenido propio de la celda (un enlace, un indicador). No se exporta: el CSV usa format.
  cell?: (row: TData) => ReactNode;
  // En modo servidor, ordenar y filtrar existen solo si el listado del backend los declara:
  // por defecto no. En modo local, por defecto sí.
  sortable?: boolean;
  filter?: GridFilterOperator | false;
  // Valores admitidos por el filtro equals o in (estados, por ejemplo).
  options?: readonly GridOption[];
  // Nombre del campo en la API cuando difiere del id de la columna.
  field?: string;
  // Admite total y subtotal (Σ de la ALV). Se declara: sumar un nivel o un id no tiene sentido.
  summable?: boolean;
  hidden?: boolean;
}

export interface GridFilter {
  operator: GridFilterOperator;
  values: readonly string[];
}

export interface GridSort {
  columnId: string;
  desc: boolean;
}

export type GridSource<TData> =
  | { kind: 'local'; rows: readonly TData[] }
  | {
      kind: 'server';
      // Clave base de caché de TanStack Query. Incluye el depósito cuando la consulta depende
      // de él (ADR-016); la grilla agrega página, orden y filtros.
      queryKey: readonly unknown[];
      fetchPage: (params: ListParams, signal: AbortSignal) => Promise<ListPage<TData>>;
    };

export interface GridMessage {
  type: 'S' | 'W' | 'E' | 'I';
  text: string;
  id?: string | undefined;
}

// Disposición de columnas: orden y visibles. Es el estado que un layout de SAP guarda; hoy
// dura lo que la transacción abierta (D7 de E2.00) y su forma permite persistirlo después.
export interface GridLayout {
  order: readonly string[];
  hidden: readonly string[];
}
