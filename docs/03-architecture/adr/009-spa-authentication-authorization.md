# ADR-009: Autenticación SPA con Sanctum y autorización por permisos y depósito

- Estado: Propuesto
- Fecha: 22/11/2026

## Contexto

El frontend es una SPA en React. La API necesita autenticación segura y autorización granular por rol, permiso y depósito asignado.

## Decisión

- Laravel Sanctum en modo SPA: cookie de sesión `HttpOnly` y protección CSRF. Frontend y API se sirven bajo el mismo host.
- `spatie/laravel-permission` para roles y permisos con nomenclatura `modulo.accion`.
- Policies que combinan permiso y pertenencia del recurso a un depósito asignado al usuario.
- Un recurso de un depósito ajeno responde 404 para no revelar su existencia.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Tokens guardados en el navegador (JWT en `localStorage`) | Expuestos ante XSS; requieren lógica de renovación |
| Sistema propio de roles y permisos | Reimplementa una solución estándar del ecosistema |

## Consecuencias

- Sin tokens accesibles desde JavaScript.
- Requiere configurar dominios con estado, CORS y cookies de forma consistente entre entornos.
- La UI oculta acciones sin permiso solo por usabilidad; la autorización real está en el backend.
