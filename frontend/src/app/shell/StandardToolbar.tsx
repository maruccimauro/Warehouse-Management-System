import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import type { KeyShortcut } from '@/shared/ui/contextMenuItems';
import { Icon, type IconName } from '@/shared/ui/icons';
import { useRovingToolbar } from '@/shared/ui/useRovingToolbar';
import { shellShortcuts } from './shortcuts';

type ToolbarCommand = 'save' | 'back' | 'exit' | 'execute' | 'favorite' | 'help';

interface ButtonConfig {
  icon: IconName;
  shortcut?: KeyShortcut;
  tone: string;
}

// Íconos, teclas y colores de la barra estándar de SAP GUI (ADR-016).
const buttons: Record<ToolbarCommand, ButtonConfig> = {
  save: { icon: 'save', shortcut: shellShortcuts.save, tone: 'text-ink' },
  back: { icon: 'back', shortcut: shellShortcuts.back, tone: 'text-success' },
  exit: { icon: 'exit', shortcut: shellShortcuts.exit, tone: 'text-warning' },
  execute: { icon: 'execute', shortcut: shellShortcuts.execute, tone: 'text-success' },
  favorite: { icon: 'star', tone: 'text-ink' },
  help: { icon: 'help', shortcut: shellShortcuts.help, tone: 'text-info' },
};

// focusgroup aún no está en los tipos de React: se pasa como atributo sin tipar.
const focusGroupAttribute = { focusgroup: 'toolbar wrap' } as Record<string, string>;

interface ToolbarButtonProps {
  command: ToolbarCommand;
  disabled: boolean;
  pressed?: boolean;
  onClick: () => void;
}

// aria-disabled en lugar de disabled: el botón sigue alcanzable con las flechas y anuncia
// que no está disponible. Los clics se ignoran mientras tanto.
function ToolbarButton({ command, disabled, pressed, onClick }: ToolbarButtonProps) {
  const { t } = useTranslation('shell');
  const { icon, shortcut, tone } = buttons[command];
  const label = t(`toolbar.${command}`);

  return (
    <button
      type="button"
      className={`inline-flex size-7 items-center justify-center rounded-sm ${tone} hover:bg-hover aria-disabled:cursor-not-allowed aria-disabled:opacity-40 aria-pressed:bg-selected aria-pressed:text-warning pointer-coarse:size-11`}
      aria-label={label}
      title={shortcut === undefined ? label : `${label} (${shortcut.display})`}
      aria-keyshortcuts={shortcut?.aria}
      aria-disabled={disabled}
      aria-pressed={pressed}
      onClick={() => {
        if (!disabled) {
          onClick();
        }
      }}
    >
      <Icon name={icon} />
    </button>
  );
}

function Separator() {
  return <span role="separator" aria-orientation="vertical" className="mx-1 h-5 border-s border-border-strong/40" />;
}

interface StandardToolbarProps {
  inTransaction: boolean;
  canSave: boolean;
  canExecute: boolean;
  isFavorite: boolean;
  onSave: () => void;
  onBack: () => void;
  onExit: () => void;
  onExecute: () => void;
  onToggleFavorite: () => void;
  onHelp: () => void;
}

// Barra de herramientas estándar. Todos los botones se renderizan siempre: el conjunto
// estable mantiene la navegación con flechas y la posición de cada uno.
export function StandardToolbar(props: StandardToolbarProps) {
  const { t } = useTranslation('shell');
  const ref = useRef<HTMLDivElement>(null);

  useRovingToolbar(ref);

  return (
    <div ref={ref} role="toolbar" aria-label={t('toolbar.label')} {...focusGroupAttribute} className="flex items-center gap-0.5">
      <ToolbarButton command="save" disabled={!props.canSave} onClick={props.onSave} />
      <Separator />
      <ToolbarButton command="back" disabled={!props.inTransaction} onClick={props.onBack} />
      <ToolbarButton command="exit" disabled={!props.inTransaction} onClick={props.onExit} />
      <Separator />
      <ToolbarButton command="execute" disabled={!props.canExecute} onClick={props.onExecute} />
      <ToolbarButton
        command="favorite"
        disabled={!props.inTransaction}
        pressed={props.isFavorite}
        onClick={props.onToggleFavorite}
      />
      <Separator />
      <ToolbarButton command="help" disabled={false} onClick={props.onHelp} />
    </div>
  );
}
