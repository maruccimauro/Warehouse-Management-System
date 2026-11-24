import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../Button';
import { GridDialog, GridDialogForm } from './GridDialog';
import type { GridColumn, GridFilter, GridFilterOperator } from './gridTypes';

export interface GridFilterTarget<TData> {
  column: GridColumn<TData>;
  operator: GridFilterOperator;
  current: GridFilter | undefined;
}

interface GridFilterDialogProps<TData> {
  target: GridFilterTarget<TData> | null;
  onApply: (columnId: string, filter: GridFilter | undefined) => void;
  onClose: () => void;
}

// "Definir filtro" de la ALV para una columna (D4 de E2.00). El operador sale de la columna:
// en modo servidor, el modo que el listado del backend declara para ese campo.
export function GridFilterDialog<TData>({ target, onApply, onClose }: GridFilterDialogProps<TData>) {
  const { t } = useTranslation('grid');

  return (
    <GridDialog
      open={target !== null}
      title={target === null ? '' : t('filterDialog.title', { column: target.column.header })}
      onCancel={onClose}
    >
      {target !== null && (
        <FilterForm
          key={target.column.id}
          target={target}
          onApply={(filter) => {
            onApply(target.column.id, filter);
            onClose();
          }}
          onCancel={onClose}
        />
      )}
    </GridDialog>
  );
}

function inputType(type: GridColumn<unknown>['type']): string {
  switch (type) {
    case 'number':
      return 'number';
    case 'date':
    case 'datetime':
      return 'date';
    default:
      return 'text';
  }
}

interface FilterFormProps<TData> {
  target: GridFilterTarget<TData>;
  onApply: (filter: GridFilter | undefined) => void;
  onCancel: () => void;
}

function FilterForm<TData>({ target: { column, operator, current }, onApply, onCancel }: FilterFormProps<TData>) {
  const { t } = useTranslation('grid');
  const fieldId = useId();
  const [values, setValues] = useState<readonly string[]>(current?.values ?? []);
  const single = values[0] ?? '';

  const apply = () => {
    const cleaned = values.map((value) => value.trim()).filter((value) => value !== '');

    onApply(cleaned.length === 0 ? undefined : { operator, values: operator === 'in' ? cleaned : cleaned.slice(0, 1) });
  };

  return (
    <GridDialogForm
      onSubmit={apply}
      footer={
        <>
          <Button type="submit" variant="primary">
            {t('filterDialog.apply')}
          </Button>
          {current !== undefined && (
            <Button
              onClick={() => {
                onApply(undefined);
              }}
            >
              {t('filterDialog.remove')}
            </Button>
          )}
          <Button onClick={onCancel}>{t('filterDialog.cancel')}</Button>
        </>
      }
    >
      {operator === 'in' ? (
        <fieldset className="flex flex-col gap-1">
          <legend className="mb-1 font-medium">{t('filterDialog.in')}</legend>
          {(column.options ?? []).map((option) => (
            <label key={option.value} className="flex min-h-7 items-center gap-2 pointer-coarse:min-h-11">
              <input
                type="checkbox"
                checked={values.includes(option.value)}
                onChange={(event) => {
                  const { checked } = event.target;

                  setValues((previous) =>
                    checked ? [...previous, option.value] : previous.filter((value) => value !== option.value),
                  );
                }}
              />
              {option.label}
            </label>
          ))}
        </fieldset>
      ) : (
        <div className="flex flex-col gap-1">
          <label htmlFor={fieldId} className="font-medium">
            {t(operator === 'contains' ? 'filterDialog.contains' : 'filterDialog.equals')}
          </label>
          {operator === 'equals' && column.options !== undefined ? (
            <select
              id={fieldId}
              className="field-input"
              value={single}
              onChange={(event) => {
                setValues([event.target.value]);
              }}
            >
              <option value="">{t('filterDialog.any')}</option>
              {column.options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          ) : (
            <input
              id={fieldId}
              className="field-input"
              type={operator === 'contains' ? 'text' : inputType(column.type)}
              value={single}
              maxLength={100}
              onChange={(event) => {
                setValues([event.target.value]);
              }}
            />
          )}
        </div>
      )}
    </GridDialogForm>
  );
}
