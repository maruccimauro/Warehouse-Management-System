import type { MouseEvent } from 'react';
import type { IconName } from './icons';

export interface KeyShortcut {
  // Como se muestra en el menú y en los tooltips, y en el formato de aria-keyshortcuts.
  display: string;
  aria: string;
}

export type ContextMenuItem =
  | {
      kind: 'action';
      id: string;
      label: string;
      icon?: IconName | undefined;
      shortcut?: KeyShortcut | undefined;
      disabled?: boolean | undefined;
      onSelect: () => void;
    }
  | { kind: 'checkbox'; id: string; label: string; checked: boolean; onChange: (checked: boolean) => void }
  | { kind: 'separator'; id: string };

// Destinos donde el menú del navegador es más útil que el propio: campos editables (copiar,
// pegar, corrector ortográfico) y texto seleccionado. Los enlaces se suman con allowLinks:
// false donde el menú propio no ofrece abrirlos en otra ventana.
const editableSelector = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])';

export function keepsNativeContextMenu(event: MouseEvent<HTMLElement>, { allowLinks = true } = {}): boolean {
  const target = event.target instanceof Element ? event.target : null;

  if (target?.closest(editableSelector) !== null) {
    return true;
  }

  if (!allowLinks && target.closest('a[href]') !== null) {
    return true;
  }

  const selection = window.getSelection();

  return selection?.isCollapsed === false && event.currentTarget.contains(selection.anchorNode);
}
