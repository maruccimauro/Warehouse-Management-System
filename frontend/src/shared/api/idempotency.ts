import { useCallback, useState } from 'react';

// Clave de idempotencia (ADR-007). crypto.randomUUID requiere contexto seguro: localhost en
// desarrollo y HTTPS en producción.
export function createIdempotencyKey(): string {
  return crypto.randomUUID();
}

// Una clave por intención de operación, no por envío (ADR-007): el mismo formulario reenviado
// tras un error de red o un 503 conserva la clave, y el servidor devuelve la respuesta guardada
// en lugar de repetir la operación. Se renueva cuando la operación se confirma o cuando el
// usuario cambia los datos; si no, el servidor responde IDEMPOTENCY_KEY_REUSED.
export function useIdempotencyKey(): { key: string; renew: () => void } {
  const [key, setKey] = useState(createIdempotencyKey);
  const renew = useCallback(() => {
    setKey(createIdempotencyKey());
  }, []);

  return { key, renew };
}
