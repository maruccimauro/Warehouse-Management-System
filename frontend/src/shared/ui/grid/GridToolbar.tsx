import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Icon, type IconName } from '../icons';
import { useRovingToolbar } from '../useRovingToolbar';

// focusgroup aún no está en los tipos de React: se pasa como atributo sin tipar.
const focusGroupAttribute = { focusgroup: 'toolbar wrap' } as Record<string, string>;

export interface GridToolbarAction {
  id: string;
  icon: IconName;
  label: string;
  disabled?: boolean;
  pressed?: boolean | undefined;
  onClick: () => void;
}

// Barra de funciones de la ALV. Mismo patrón que la barra estándar: una parada de Tab, flechas
// entre botones y aria-disabled para que un botón no disponible siga alcanzable y se anuncie.
export function GridToolbar({ groups }: { groups: readonly (readonly GridToolbarAction[])[] }) {
  const { t } = useTranslation('grid');
  const ref = useRef<HTMLDivElement>(null);

  useRovingToolbar(ref);

  return (
    <div
      ref={ref}
      role="toolbar"
      aria-label={t('toolbar.label')}
      {...focusGroupAttribute}
      className="flex flex-wrap items-center gap-0.5 rounded-sm border border-border bg-toolbar px-1 py-0.5"
    >
      {groups
        .filter((group) => group.length > 0)
        .map((group, index) => (
          <span key={group[0]?.id ?? index} className="contents">
            {index > 0 && (
              <span role="separator" aria-orientation="vertical" className="mx-1 h-5 border-s border-border-strong/40" />
            )}
            {group.map((action) => (
              <button
                key={action.id}
                type="button"
                className="inline-flex size-7 items-center justify-center rounded-sm text-ink hover:bg-hover aria-disabled:cursor-not-allowed aria-disabled:opacity-40 aria-pressed:bg-selected pointer-coarse:size-11"
                aria-label={action.label}
                title={action.label}
                aria-disabled={action.disabled === true}
                aria-pressed={action.pressed}
                onClick={() => {
                  if (action.disabled !== true) {
                    action.onClick();
                  }
                }}
              >
                <Icon name={action.icon} />
              </button>
            ))}
          </span>
        ))}
    </div>
  );
}
