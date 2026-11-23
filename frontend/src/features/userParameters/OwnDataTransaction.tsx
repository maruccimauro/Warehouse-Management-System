import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useShellActions, useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { useUserPreferences } from '@/features/preferences/userPreferences';

const sectionClass = 'rounded-sm border border-border bg-surface';
const sectionTitleClass = 'border-b border-border bg-surface-alt px-3 py-1.5 text-sm font-semibold';
const cellClass = 'border-b border-border px-3 py-1.5 text-start align-middle';

// SU3: datos propios del usuario y sus parámetros. LGN es el depósito por defecto que
// completa el campo Depósito de las pantallas operativas (ADR-016). Como en SAP, los
// cambios se aplican al grabar.
export function OwnDataTransaction() {
  const { t } = useTranslation('su3');
  const user = useAuthenticatedUser();
  const { preferences, setParameter } = useUserPreferences();
  const { show } = useStatusBar();
  const { available } = useShellActions();
  const lgnId = useId();

  // Un depósito guardado que ya no está asignado no se ofrece como valor.
  const assignedCodes = new Set(user.warehouses.map((warehouse) => warehouse.code));
  const savedLgn = preferences.parameters.LGN;
  const [lgn, setLgn] = useState(savedLgn !== undefined && assignedCodes.has(savedLgn) ? savedLgn : '');

  useTransactionActions({
    save: () => {
      setParameter('LGN', lgn === '' ? undefined : lgn);
      show({ type: 'S', text: t('saved') });
    },
  });

  const hasWarehouses = user.warehouses.length > 0;

  return (
    <div className="grid max-w-4xl gap-4">
      <section className={sectionClass} aria-labelledby={`${lgnId}-user`}>
        <h2 id={`${lgnId}-user`} className={sectionTitleClass}>
          {t('user.title')}
        </h2>
        <dl className="grid grid-cols-[minmax(8rem,12rem)_1fr] gap-x-4 gap-y-1.5 px-3 py-2">
          <dt className="text-ink-muted">{t('user.name')}</dt>
          <dd>{user.name}</dd>
          <dt className="text-ink-muted">{t('user.email')}</dt>
          <dd>{user.email}</dd>
        </dl>
      </section>

      <section className={sectionClass} aria-labelledby={`${lgnId}-parameters`}>
        <h2 id={`${lgnId}-parameters`} className={sectionTitleClass}>
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

      {available.save && <p className="text-xs text-ink-muted">{t('saveHint')}</p>}
    </div>
  );
}
