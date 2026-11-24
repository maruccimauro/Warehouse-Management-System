import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useTransactionActions } from '@/app/shell/shellActions';
import { errorMessage, useStatusBar } from '@/app/shell/statusMessages';
import { useUnsavedChanges } from '@/app/shell/unsavedChanges';
import { PasswordChangeDialog } from '@/features/auth/PasswordChangeDialog';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { fallbackLanguage, isSupportedLanguage, supportedLanguages } from '@/i18n/resources';
import { Button } from '@/shared/ui/Button';

const sectionClass = 'rounded-sm border border-border bg-surface';
const sectionTitleClass = 'border-b border-border bg-surface-alt px-3 py-1.5 text-sm font-semibold';
const cellClass = 'border-b border-border px-3 py-1.5 text-start align-middle';
const rowClass = 'grid grid-cols-[minmax(8rem,12rem)_1fr] items-center gap-x-4 gap-y-1.5 px-3 py-2';

// SU3: datos propios del usuario, valores fijos y parámetros. LGN es el depósito por
// defecto que completa el campo Depósito de las pantallas operativas (ADR-016). Como en
// SAP, los cambios se aplican al grabar; desde E2.21 se graban en el servidor.
export function OwnDataTransaction() {
  const { t } = useTranslation('su3');
  const user = useAuthenticatedUser();
  const { preferences, saveParameters, saveSettings } = useUserPreferences();
  const { show } = useStatusBar();
  const id = useId();
  const [passwordDialogOpen, setPasswordDialogOpen] = useState(false);
  const saving = useRef(false);

  // Un depósito guardado que ya no está asignado no se ofrece como valor.
  const assignedCodes = new Set(user.warehouses.map((warehouse) => warehouse.code));
  const savedLgn = preferences.parameters.LGN;
  const savedLgnValue = savedLgn !== null && assignedCodes.has(savedLgn) ? savedLgn : '';
  const [lgn, setLgn] = useState(savedLgnValue);

  // Solo se ofrecen los idiomas que la interfaz tiene traducidos (D7). Un idioma guardado
  // que todavía no tiene traducción se muestra como el idioma por defecto.
  const savedLocale = isSupportedLanguage(user.locale) ? user.locale : fallbackLanguage;
  const [locale, setLocale] = useState<string>(savedLocale);

  const lgnChanged = lgn !== savedLgnValue;
  const localeChanged = locale !== savedLocale;

  useUnsavedChanges(lgnChanged || localeChanged);

  const save = async () => {
    // Ctrl+S repetido mientras se graba no duplica la petición.
    if (saving.current) {
      return;
    }

    saving.current = true;

    try {
      if (lgnChanged) {
        await saveParameters({ ...preferences.parameters, LGN: lgn === '' ? null : lgn });
      }

      if (localeChanged) {
        await saveSettings({ locale });
      }

      show({ type: 'S', text: t('saved') });
    } catch (error) {
      show(errorMessage(error, t('saveFailed')));
    } finally {
      saving.current = false;
    }
  };

  useTransactionActions({
    save: () => {
      void save();
    },
  });

  const hasWarehouses = user.warehouses.length > 0;
  const lgnId = `${id}-lgn`;
  const localeId = `${id}-locale`;

  return (
    <div className="grid max-w-4xl gap-4">
      <section className={sectionClass} aria-labelledby={`${id}-user`}>
        <h2 id={`${id}-user`} className={sectionTitleClass}>
          {t('user.title')}
        </h2>
        <dl className="grid grid-cols-[minmax(8rem,12rem)_1fr] gap-x-4 gap-y-1.5 px-3 py-2">
          <dt className="text-ink-muted">{t('user.name')}</dt>
          <dd>{user.name}</dd>
          <dt className="text-ink-muted">{t('user.email')}</dt>
          <dd>{user.email}</dd>
        </dl>
      </section>

      <section className={sectionClass} aria-labelledby={`${id}-settings`}>
        <h2 id={`${id}-settings`} className={sectionTitleClass}>
          {t('settings.title')}
        </h2>
        <div className={rowClass}>
          <label htmlFor={localeId} className="text-ink-muted">
            {t('settings.language')}
          </label>
          <div className="grid gap-1">
            <select
              id={localeId}
              className="field-input max-w-72"
              aria-describedby={`${localeId}-description`}
              value={locale}
              onChange={(event) => {
                setLocale(event.target.value);
              }}
            >
              {supportedLanguages.map((language) => (
                <option key={language} value={language}>
                  {t(`languages.${language}`)}
                </option>
              ))}
            </select>
            <p id={`${localeId}-description`} className="text-xs text-ink-muted">
              {t('settings.languageDescription')}
            </p>
          </div>
        </div>
      </section>

      <section className={sectionClass} aria-labelledby={`${id}-parameters`}>
        <h2 id={`${id}-parameters`} className={sectionTitleClass}>
          {t('parameters.title')}
        </h2>
        <div className="overflow-x-auto">
          <table className="w-full border-collapse">
            <thead className="bg-toolbar text-xs text-ink-muted">
              <tr>
                <th scope="col" className={`${cellClass} w-36`}>
                  {t('parameters.id')}
                </th>
                <th scope="col" className={`${cellClass} w-72`}>
                  {t('parameters.value')}
                </th>
                <th scope="col" className={cellClass}>
                  {t('parameters.description')}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="row" className={`${cellClass} font-mono font-medium`}>
                  <label htmlFor={lgnId}>LGN</label>
                </th>
                <td className={cellClass}>
                  <select
                    id={lgnId}
                    className="field-input"
                    disabled={!hasWarehouses}
                    aria-describedby={`${lgnId}-description`}
                    value={lgn}
                    onChange={(event) => {
                      setLgn(event.target.value);
                    }}
                  >
                    {hasWarehouses ? (
                      <>
                        <option value="">{t('noValue')}</option>
                        {user.warehouses.map((warehouse) => (
                          <option key={warehouse.id} value={warehouse.code}>
                            {t('warehouseOption', { code: warehouse.code, name: warehouse.name })}
                          </option>
                        ))}
                      </>
                    ) : (
                      <option value="">{t('noWarehouses')}</option>
                    )}
                  </select>
                </td>
                <td id={`${lgnId}-description`} className={`${cellClass} text-ink-muted`}>
                  {t('parameters.descriptions.LGN')}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section className={sectionClass} aria-labelledby={`${id}-password`}>
        <h2 id={`${id}-password`} className={sectionTitleClass}>
          {t('password.title')}
        </h2>
        <div className="flex flex-wrap items-center gap-3 px-3 py-2">
          <p className="text-ink-muted">{t('password.description')}</p>
          <Button
            onClick={() => {
              setPasswordDialogOpen(true);
            }}
          >
            {t('password.change')}
          </Button>
        </div>
      </section>

      <p className="text-xs text-ink-muted">{t('saveHint')}</p>

      <PasswordChangeDialog
        open={passwordDialogOpen}
        onChanged={() => {
          setPasswordDialogOpen(false);
          show({ type: 'S', text: t('password.changed') });
        }}
        onClose={() => {
          setPasswordDialogOpen(false);
        }}
      />
    </div>
  );
}
