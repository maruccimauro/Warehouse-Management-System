import { useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { PasswordChangeDialog } from '@/features/auth/PasswordChangeDialog';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useUserPreferences } from '@/features/preferences/userPreferences';
import { DefaultWarehouseField } from '@/features/warehouses/WarehouseField';
import { fallbackLanguage, isSupportedLanguage, supportedLanguages } from '@/i18n/resources';
import { Button } from '@/shared/ui/Button';
import { FormSection } from '@/shared/ui/form/FormSection';
import { InfoNotice } from '@/shared/ui/InfoNotice';

const cellClass = 'border-b border-border px-3 py-1.5 text-start align-middle';

const su3Schema = z.object({
  locale: z.string(),
  // Código de depósito o vacío; que exista entre los asignados lo controla el campo F4 como
  // tabla de verificación.
  lgn: z.string(),
});

// SU3: datos propios del usuario, valores fijos y parámetros. LGN es el depósito por
// defecto que completa el campo Depósito de las pantallas operativas (ADR-016). Como en
// SAP, los cambios se aplican al grabar (Ctrl+S); desde E2.21 se graban en el servidor y
// desde E2.22 la pantalla usa los bloques de formulario de transacción.
export function OwnDataTransaction() {
  const { t } = useTranslation('su3');
  const user = useAuthenticatedUser();
  const { preferences, saveParameters, saveSettings } = useUserPreferences();
  const { show } = useStatusBar();
  const id = useId();
  const [passwordDialogOpen, setPasswordDialogOpen] = useState(false);

  // Un depósito guardado que ya no está asignado no se ofrece como valor.
  const savedLgn = preferences.parameters.LGN;
  const savedLgnValue = savedLgn !== null && user.warehouses.some((warehouse) => warehouse.code === savedLgn) ? savedLgn : '';
  // Solo se ofrecen los idiomas que la interfaz tiene traducidos (D7 de E2.21). Un idioma
  // guardado que todavía no tiene traducción se muestra como el idioma por defecto.
  const savedLocale = isSupportedLanguage(user.locale) ? user.locale : fallbackLanguage;
  const defaultValues = useMemo(() => ({ locale: savedLocale, lgn: savedLgnValue }), [savedLocale, savedLgnValue]);

  const { Form, Section, Field } = useTransactionForm({
    schema: su3Schema,
    defaultValues,
    namespace: 'su3',
    serverFields: { LGN: 'lgn', locale: 'locale' },
    savedMessage: t('saved'),
    saveFailedMessage: t('saveFailed'),
    onSave: async ({ locale, lgn }) => {
      if (lgn !== defaultValues.lgn) {
        await saveParameters({ ...preferences.parameters, LGN: lgn === '' ? null : lgn });
      }

      if (locale !== defaultValues.locale) {
        await saveSettings({ locale });
      }
    },
  });

  const languageOptions = supportedLanguages.map((language) => ({ value: language, label: t(`languages.${language}`) }));
  const lgnLabelId = `${id}-lgn-label`;
  const lgnDescriptionId = `${id}-lgn-description`;

  return (
    <div className="grid max-w-4xl gap-4">
      <FormSection title={t('user.title')}>
        <dl className="grid grid-cols-[minmax(8rem,12rem)_1fr] gap-x-4 gap-y-1.5">
          <dt className="text-ink-muted">{t('user.username')}</dt>
          <dd>{user.username}</dd>
          <dt className="text-ink-muted">{t('user.name')}</dt>
          <dd>{user.name}</dd>
          <dt className="text-ink-muted">{t('user.email')}</dt>
          <dd>{user.email}</dd>
        </dl>
      </FormSection>

      <Form>
        <Section name="settings">
          <Field name="locale" options={languageOptions} description={t('settings.languageDescription')} />
        </Section>

        <Section name="parameters" plain>
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
                  <th scope="row" id={lgnLabelId} className={`${cellClass} font-mono font-medium`}>
                    {t('fields.lgn')}
                  </th>
                  <td className={cellClass}>
                    {user.warehouses.length > 0 ? (
                      <Field name="lgn" as={DefaultWarehouseField} labelledBy={lgnLabelId} describedBy={lgnDescriptionId} />
                    ) : (
                      <input
                        className="field-input w-40"
                        aria-labelledby={lgnLabelId}
                        disabled
                        value={t('noWarehouses')}
                        readOnly
                      />
                    )}
                  </td>
                  <td id={lgnDescriptionId} className={`${cellClass} text-ink-muted`}>
                    {t('parameters.descriptions.LGN')}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Section>
      </Form>

      <FormSection title={t('password.title')}>
        <div className="flex flex-wrap items-center gap-3">
          <p className="text-ink-muted">{t('password.description')}</p>
          <Button
            onClick={() => {
              setPasswordDialogOpen(true);
            }}
          >
            {t('password.change')}
          </Button>
        </div>
      </FormSection>

      <InfoNotice>{t('saveHint')}</InfoNotice>

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
