import type { KeyShortcut } from '@/shared/ui/contextMenuItems';

// Teclas de función de SAP GUI (ADR-016), compartidas por la barra estándar, el menú
// contextual del área de trabajo y useShellKeyboard.
export const shellShortcuts = {
  save: { display: 'Ctrl+S', aria: 'Control+S' },
  back: { display: 'F3', aria: 'F3' },
  exit: { display: 'Mayús+F3', aria: 'Shift+F3' },
  execute: { display: 'F8', aria: 'F8' },
} as const satisfies Record<string, KeyShortcut>;
