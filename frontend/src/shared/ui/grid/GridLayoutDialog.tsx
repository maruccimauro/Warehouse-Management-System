import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../Button';
import { Icon } from '../icons';
import { GridDialog, GridDialogForm } from './GridDialog';
import type { GridLayout } from './gridTypes';

interface GridLayoutDialogProps {
  open: boolean;
  columns: readonly { id: string; header: string }[];
  layout: GridLayout;
  initialLayout: GridLayout;
  onApply: (layout: GridLayout) => void;
  onClose: () => void;
}

// "Modificar disposición" de la ALV: qué columnas se ven y en qué orden. Subir y bajar con
// botones en lugar de arrastrar: funciona igual con teclado, puntero y lector de pantalla.
export function GridLayoutDialog({ open, columns, layout, initialLayout, onApply, onClose }: GridLayoutDialogProps) {
  const { t } = useTranslation('grid');

  return (
    <GridDialog open={open} title={t('columnsDialog.title')} onCancel={onClose}>
      {open && (
        <LayoutForm
          columns={columns}
          layout={layout}
          initialLayout={initialLayout}
          onApply={(next) => {
            onApply(next);
            onClose();
          }}
          onCancel={onClose}
        />
      )}
    </GridDialog>
  );
}

interface LayoutFormProps {
  columns: readonly { id: string; header: string }[];
  layout: GridLayout;
  initialLayout: GridLayout;
  onApply: (layout: GridLayout) => void;
  onCancel: () => void;
}

function LayoutForm({ columns, layout, initialLayout, onApply, onCancel }: LayoutFormProps) {
  const { t } = useTranslation('grid');
  const [draft, setDraft] = useState<GridLayout>(layout);
  const headers = new Map(columns.map((column) => [column.id, column.header]));
  const visibleCount = draft.order.filter((id) => !draft.hidden.includes(id)).length;

  // aria-disabled y no disabled en los extremos: el botón conserva el foco después de mover la
  // columna al principio o al final, en lugar de perderlo en el cuerpo del diálogo.
  const move = (index: number, offset: -1 | 1) => {
    setDraft((previous) => {
      const order = [...previous.order];
      const target = index + offset;
      const current = order[index];
      const other = order[target];

      if (current === undefined || other === undefined) {
        return previous;
      }

      order[index] = other;
      order[target] = current;

      return { ...previous, order };
    });
  };

  return (
    <GridDialogForm
      onSubmit={() => {
        if (visibleCount > 0) {
          onApply(draft);
        }
      }}
      footer={
        <>
          <Button type="submit" variant="primary" disabled={visibleCount === 0}>
            {t('columnsDialog.apply')}
          </Button>
          <Button
            onClick={() => {
              setDraft(initialLayout);
            }}
          >
            {t('columnsDialog.reset')}
          </Button>
          <Button onClick={onCancel}>{t('columnsDialog.cancel')}</Button>
        </>
      }
    >
      <fieldset>
        <legend className="mb-2 font-medium">{t('columnsDialog.visible')}</legend>
        <ul className="flex flex-col">
          {draft.order.map((id, index) => {
            const header = headers.get(id) ?? id;

            return (
              <li key={id} className="flex min-h-7 items-center gap-2 pointer-coarse:min-h-11">
                <label className="flex grow items-center gap-2">
                  <input
                    type="checkbox"
                    checked={!draft.hidden.includes(id)}
                    onChange={(event) => {
                      const { checked } = event.target;

                      setDraft((previous) => ({
                        ...previous,
                        hidden: checked ? previous.hidden.filter((hidden) => hidden !== id) : [...previous.hidden, id],
                      }));
                    }}
                  />
                  {header}
                </label>
                <Button
                  variant="ghost"
                  className="px-1 aria-disabled:cursor-not-allowed aria-disabled:opacity-40"
                  aria-label={t('columnsDialog.moveUp', { column: header })}
                  aria-disabled={index === 0}
                  onClick={() => {
                    move(index, -1);
                  }}
                >
                  <Icon name="arrowUp" />
                </Button>
                <Button
                  variant="ghost"
                  className="px-1 aria-disabled:cursor-not-allowed aria-disabled:opacity-40"
                  aria-label={t('columnsDialog.moveDown', { column: header })}
                  aria-disabled={index === draft.order.length - 1}
                  onClick={() => {
                    move(index, 1);
                  }}
                >
                  <Icon name="arrowDown" />
                </Button>
              </li>
            );
          })}
        </ul>
        {visibleCount === 0 && (
          <p role="alert" className="mt-2 text-error">
            {t('columnsDialog.atLeastOne')}
          </p>
        )}
      </fieldset>
    </GridDialogForm>
  );
}
