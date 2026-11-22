# ADR-007: Idempotencia en operaciones que modifican stock

- Estado: Propuesto
- Fecha: 22/11/2026
- Relacionados: ADR-004

## Contexto

Los operadores trabajan con conexiones inestables y botones que pueden presionarse dos veces. Un reintento de red sobre una transferencia o un despacho no debe duplicar la operación.

## Decisión

- Los endpoints que modifican stock exigen la cabecera `Idempotency-Key`, generada por el cliente al iniciar el intento de operación y reutilizada en sus reintentos.
- La tabla `idempotency_keys` guarda `(user_id, key)` único, el hash del cuerpo y la respuesta.
- Misma clave y mismo cuerpo: se devuelve la respuesta almacenada. Misma clave con otro cuerpo: 422 `IDEMPOTENCY_KEY_REUSED`. Clave en uso por otra petición en curso: 409.
- La clave y la respuesta se registran en la misma transacción que la operación.
- Los documentos tienen además `operation_id UNIQUE`, lo que impide confirmar dos veces una recepción o un despacho aunque llegue con otra clave.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Deshabilitar el botón en el frontend | Necesario pero insuficiente: no cubre reintentos de red |
| Detectar duplicados por similitud de contenido | Ambiguo: dos transferencias idénticas pueden ser legítimas |

## Consecuencias

- Reintentos seguros desde el cliente.
- Tabla adicional con limpieza programada de claves antiguas.
- Doble defensa: idempotencia por petición y unicidad por documento.
