import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { findTransaction, transactions } from '@/app/transactions';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { Icon } from '@/shared/ui/icons';
import { parseCommand, transactionPath } from './command';
import { useStatusBar } from './statusMessages';

function openInNewWindow(path: string): void {
  // noopener: la ventana nueva no recibe referencia a esta (window.opener).
  window.open(path, '_blank', 'noopener');
}

export function CommandField() {
  const { t } = useTranslation(['shell', 'transactions']);
  const navigate = useNavigate();
  const { show, clear } = useStatusBar();
  const { preferences, addToHistory } = useUserPreferences();
  const [value, setValue] = useState('');
  const id = useId();
  const listId = `${id}-transactions`;

  // Sugerencias: primero el historial del usuario, después el resto de las transacciones.
  const history = preferences.commandHistory.filter((code) => findTransaction(code) !== undefined);
  const suggestions = [
    ...history,
    ...transactions.map((transaction) => transaction.code).filter((code) => !history.includes(code)),
  ];

  const execute = () => {
    const command = parseCommand(value);

    switch (command.kind) {
      case 'empty':
        return;
      case 'invalid':
        show({ type: 'E', text: t('command.invalid', { command: command.input }) });

        return;
      case 'menu':
        setValue('');
        clear();

        if (command.newWindow) {
          openInNewWindow('/');
        } else {
          void navigate('/');
        }

        return;
      case 'open': {
        if (findTransaction(command.code) === undefined) {
          show({ type: 'E', text: t('command.unknownTransaction', { code: command.code }) });

          return;
        }

        setValue('');
        clear();
        addToHistory(command.code);

        if (command.newWindow) {
          openInNewWindow(transactionPath(command.code));
        } else {
          void navigate(transactionPath(command.code));
        }
      }
    }
  };

  return (
    <form
      className="flex items-center gap-1"
      onSubmit={(event) => {
        event.preventDefault();
        execute();
      }}
    >
      <label htmlFor={id} className="visually-hidden">
        {t('command.label')}
      </label>
      <input
        id={id}
        className="field-input w-44 font-mono uppercase"
        list={listId}
        autoComplete="off"
        autoCapitalize="characters"
        spellCheck={false}
        enterKeyHint="go"
        value={value}
        onChange={(event) => {
          setValue(event.target.value);
        }}
      />
      <datalist id={listId}>
        {suggestions.map((code) => {
          const transaction = findTransaction(code);

          return (
            <option key={code} value={code}>
              {transaction === undefined ? code : t(transaction.titleKey, { ns: 'transactions' })}
            </option>
          );
        })}
      </datalist>
      <button
        type="submit"
        className="inline-flex size-7 items-center justify-center rounded-sm text-success hover:bg-hover pointer-coarse:size-11"
        aria-label={t('command.confirm')}
        title={t('command.confirm')}
      >
        <Icon name="check" />
      </button>
    </form>
  );
}
