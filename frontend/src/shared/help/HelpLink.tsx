import type { ReactNode } from 'react';
import { helpWindowName } from './help';
import { helpUrl, type HelpKey } from './helpLinks';

interface HelpLinkProps {
  helpKey: HelpKey;
  // Texto visible del enlace: el código o el estado que explica.
  children: ReactNode;
  // Nombre accesible cuando el texto visible no alcanza ("Ayuda: WAREHOUSE_INACTIVE").
  label?: string;
  className?: string;
}

// Enlace a la explicación de un estado, un código de error o un permiso en la wiki (DM-11).
// Es un enlace común, no un botón: se abre con clic medio o en otra pestaña, y la pestaña de
// ayuda nombrada se reutiliza igual que con F1. Lleva data-help-key para que F1 con el foco
// en el enlace abra lo mismo.
export function HelpLink({ helpKey, children, label, className = '' }: HelpLinkProps) {
  return (
    <a
      href={helpUrl(helpKey)}
      target={helpWindowName}
      data-help-key={helpKey}
      aria-label={label}
      className={`underline decoration-dotted underline-offset-2 hover:text-ink hover:decoration-solid ${className}`}
    >
      {children}
    </a>
  );
}
