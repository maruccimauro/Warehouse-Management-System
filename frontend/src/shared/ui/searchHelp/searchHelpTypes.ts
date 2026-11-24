import type { GridColumn } from '../grid/gridTypes';

// Definición de una ayuda de búsqueda (F4, E2.17, ADR-016): el equivalente de la ayuda de
// búsqueda elemental de SAP, con sus campos de restricción, la lista de resultados y el campo
// que se devuelve a la pantalla. El campo que la usa la valida además como tabla de
// verificación: un valor escrito a mano tiene que existir entre las filas.
export interface SearchHelp<TData> {
  // Título del diálogo (por ejemplo, "Depósitos").
  title: string;
  columns: readonly GridColumn<TData>[];
  // Solo modo local por ahora (D5 de E2.17): las ayudas con miles de filas (producto,
  // ubicación) suman una fuente 'server' cuando llegue la primera pantalla que las use.
  source: { kind: 'local'; rows: readonly TData[] };
  // Ids de las columnas que se ofrecen como restricción, en ese orden. Filtran por "contiene"
  // sobre el texto que muestra la columna, como la ALV en modo local.
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
