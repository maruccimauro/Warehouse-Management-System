import { useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { normalizeTaxId } from '@/shared/ui/form/fieldRules';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionInputProps } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { findSupplier, type Supplier } from './catalogApi';
import { SupplierField } from './CatalogFields';
import { InfoNotice } from '@/shared/ui/InfoNotice';

interface SupplierInitialScreenProps {
  // Título de la pantalla: modificar, visualizar o los productos del proveedor.
  title: 'change' | 'display' | 'products';
  onContinue: (supplier: Supplier) => void;
  // Identificador fiscal de la última entrada: F3 desde la ficha vuelve con él.
  initial: string | undefined;
}

// Pantalla inicial de modificar y visualizar un proveedor y de sus productos (E2.13, D1): el
// identificador fiscal, escrito o elegido con F4 por nombre. F8 lee el proveedor.
export function SupplierInitialScreen({ title, onContinue, initial }: SupplierInitialScreenProps) {
  const { t } = useTranslation('suppliers');
  const { show } = useStatusBar();

  const fields = useMemo(
    (): SelectionField[] => [{ kind: 'parameter', id: 'supplier', label: t('fields.supplier'), as: SupplierInput, required: true }],
    [t],
  );
  const selection = useSelectionScreen(fields, { supplier: initial ?? '' });
  const running = useRef(false);

  const execute = async () => {
    const values = selection.validate(show);

    if (values === undefined || running.current) {
      return;
    }

    const taxId = normalizeTaxId(parameterValue(values, 'supplier'));
    running.current = true;

    try {
      const supplier = await findSupplier(taxId);

      if (supplier === undefined) {
        show({ type: 'E', text: t('messages.notFound', { taxId }), id: 'SUPPLIER_NOT_FOUND' });

        return;
      }

      onContinue(supplier);
    } catch (error) {
      show(errorMessage(error, t('messages.loadFailed')));
    } finally {
      running.current = false;
    }
  };

  useTransactionActions({
    execute: () => {
      void execute();
    },
  });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t(`initial.${title}`)} onMessage={show} />
      <InfoNotice>{t('initial.hint')}</InfoNotice>
    </div>
  );
}

function SupplierInput(props: SelectionInputProps) {
  return <SupplierField {...props} />;
}
