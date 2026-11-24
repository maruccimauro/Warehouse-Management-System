import type { ComponentType } from 'react';
import type { SelectOption } from '@/shared/api/listing';
import type { GridMessage } from '../grid/gridTypes';

// Tipos de la pantalla de selección (E2.18, ADR-016): el equivalente de PARAMETERS y
// SELECT-OPTIONS de un programa de SAP. La pantalla declara sus campos con estos tipos.

// Tipo del valor: decide el teclado en pantalla y la validación. Las fechas llegan con E3.
export type SelectionValueType = 'text' | 'integer';

// Props de un campo con ayuda de búsqueda que la pantalla puede usar como entrada (`as`):
// SearchHelpField y los campos que lo envuelven, como WarehouseField. check en false apaga la
// tabla de verificación en los límites de un SELECT-OPTIONS (D4 de E2.18).
export interface SelectionInputProps {
  id: string;
  value: string;
  onChange: (value: string, valid: boolean) => void;
  onMessage: (message: GridMessage) => void;
  check?: boolean;
  required?: boolean;
  className?: string;
  'aria-describedby'?: string;
  'aria-labelledby'?: string;
}

interface SelectionFieldBase {
  // Identificador del campo en la pantalla y en los valores.
  id: string;
  label: string;
  // Nombre del filtro en la API (ListRequest) cuando difiere del id.
  field?: string;
  type?: SelectionValueType;
  // Campo con F4 propio (as={WarehouseField}). Sin él, un <input> de texto.
  as?: ComponentType<SelectionInputProps>;
  // Normalización de lo escrito en un <input> propio (mayúsculas para los códigos). Un campo
  // `as` normaliza por su cuenta.
  normalize?: (text: string) => string;
}

// PARAMETERS: un único valor, con la tabla de verificación del campo `as` si la tiene.
export interface ParameterField extends SelectionFieldBase {
  kind: 'parameter';
  // OBLIGATORY de SAP: F8 no ejecuta sin valor.
  required?: boolean;
}

// SELECT-OPTIONS: desde, hasta y selección múltiple.
export interface SelectOptionsField extends SelectionFieldBase {
  kind: 'selectOptions';
}

export type SelectionField = ParameterField | SelectOptionsField;

// Valores de la pantalla por id de campo: texto para un parámetro y entradas para un
// SELECT-OPTIONS. Se leen con parameterValue y selectOptionsOf.
export type SelectionValue = string | readonly SelectOption[];
export type SelectionValues = Readonly<Record<string, SelectionValue>>;
