import { useRef } from 'react';
import { Button } from './Button';
import { useRovingToolbar } from './useRovingToolbar';

// focusgroup aún no está en los tipos de React: se pasa como atributo sin tipar.
const focusGroupAttribute = { focusgroup: 'toolbar wrap' } as Record<string, string>;

export interface ApplicationAction {
  id: string;
  label: string;
  onClick: () => void;
  disabled?: boolean;
  // La acción principal de la pantalla (Modificar en una ficha en visualizar): botón destacado.
  primary?: boolean;
}

interface ApplicationToolbarProps {
  // Nombre accesible de la barra (por ejemplo, "Acciones de depósitos").
  label: string;
  actions: readonly ApplicationAction[];
}

// Barra de aplicación de la transacción, debajo de la barra estándar (ADR-016): las acciones
// propias de la pantalla (Crear, Modificar, Bloquear). Una sola parada de tabulación y
// flechas entre botones, como la barra estándar.
export function ApplicationToolbar({ label, actions }: ApplicationToolbarProps) {
  const ref = useRef<HTMLDivElement>(null);

  useRovingToolbar(ref);

  if (actions.length === 0) {
    return null;
  }

  return (
    <div
      ref={ref}
      role="toolbar"
      aria-label={label}
      // focusgroup nativo donde existe; useRovingToolbar es el respaldo.
      {...focusGroupAttribute}
      className="flex flex-wrap gap-2"
    >
      {actions.map((action) => (
        <Button key={action.id} variant={action.primary === true ? 'primary' : 'default'} disabled={action.disabled} onClick={action.onClick}>
          {action.label}
        </Button>
      ))}
    </div>
  );
}
