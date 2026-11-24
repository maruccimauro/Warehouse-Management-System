import { z } from 'zod';

// Lo que queda en el navegador desde E2.21: el historial del campo de comando, como el
// historial local de SAP GUI. Parámetros, favoritos y mostrar códigos pasaron al servidor.
const maxHistory = 10;

function historyKey(userId: number): string {
  return `wms.commandHistory.${userId}`;
}

// Clave de E1, donde estaban todas las preferencias. Se lee una sola vez para subir lo
// guardado al servidor y después se borra.
function legacyKey(userId: number): string {
  return `wms.preferences.${userId}`;
}

const historySchema = z.array(z.string()).catch([]);

// Cada campo tiene su valor por defecto: un dato inválido de E1 no rompe la migración.
const legacySchema = z.object({
  parameters: z.object({ LGN: z.string().optional() }).catch({}),
  favorites: z.array(z.string()).catch([]),
  showTechnicalNames: z.boolean().catch(false),
  commandHistory: z.array(z.string()).catch([]),
});

export type LegacyPreferences = z.infer<typeof legacySchema>;

function readJson(key: string): unknown {
  try {
    const raw = localStorage.getItem(key);

    return raw === null ? undefined : (JSON.parse(raw) as unknown);
  } catch {
    // Almacenamiento bloqueado o JSON corrupto.
    return undefined;
  }
}

export function readLegacyPreferences(userId: number): LegacyPreferences | undefined {
  const raw = readJson(legacyKey(userId));

  if (raw === undefined) {
    return undefined;
  }

  const parsed = legacySchema.safeParse(raw);

  return parsed.success ? parsed.data : undefined;
}

export function removeLegacyPreferences(userId: number): void {
  try {
    localStorage.removeItem(legacyKey(userId));
  } catch {
    // Sin almacenamiento no hay nada que borrar.
  }
}

// Sin historial propio se parte del de E1, si quedó guardado.
export function readCommandHistory(userId: number): string[] {
  const raw = readJson(historyKey(userId));

  if (raw !== undefined) {
    return historySchema.parse(raw);
  }

  return readLegacyPreferences(userId)?.commandHistory ?? [];
}

export function writeCommandHistory(userId: number, history: readonly string[]): void {
  try {
    localStorage.setItem(historyKey(userId), JSON.stringify(history));
  } catch {
    // Sin almacenamiento el historial dura lo que dure la pestaña.
  }
}

export function withHistoryEntry(history: readonly string[], code: string): string[] {
  return [code, ...history.filter((entry) => entry !== code)].slice(0, maxHistory);
}
