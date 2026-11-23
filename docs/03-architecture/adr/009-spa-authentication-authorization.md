# ADR-009: Autenticación SPA con Sanctum y autorización por permisos y depósito

- Estado: Propuesto
- Fecha: 22/11/2026

## Contexto

El frontend es una SPA en React. La API necesita autenticación segura y autorización granular por rol, permiso y depósito asignado.

## Decisión

Autenticación (implementada en E1.07):

- Laravel Sanctum en modo SPA: cookie de sesión `HttpOnly` con `SameSite=Lax` y protección CSRF con la cookie `XSRF-TOKEN`, que el cliente obtiene en `GET /sanctum/csrf-cookie` y devuelve en la cabecera `X-XSRF-TOKEN`. Laravel 13 verifica primero el origen (`PreventRequestForgery`): acepta la petición sin comparar tokens si el navegador envía `Sec-Fetch-Site: same-origin`, cabecera que el navegador fija y que ningún script puede modificar (Fetch Metadata). El token queda como respaldo para navegadores sin Fetch Metadata, y el cliente lo envía siempre; ante un 419 renueva la cookie y reintenta una sola vez.
- Frontend y API se sirven bajo el mismo host a través de Nginx en todos los entornos. Las peticiones son del mismo origen: CORS no habilita ningún origen externo salvo configuración explícita (`CORS_ALLOWED_ORIGINS`).
- Solo sesión, sin tokens. La lectura de bearer tokens de Sanctum está desactivada: una petición con `Authorization: Bearer` se trata como no autenticada.
- `POST /api/v1/auth/login` requiere una petición de un dominio con estado (`SANCTUM_STATEFUL_DOMAINS`); sin sesión responde 400 `SESSION_REQUIRED`. Las credenciales incorrectas responden 422 `INVALID_CREDENTIALS`, con el mismo mensaje si falla el email o la contraseña. El login regenera el identificador de sesión para evitar la fijación de sesión, y el logout invalida la sesión y el token CSRF.
- Rate limiting en el login con dos límites: 5 intentos por minuto por email e IP, contra la fuerza bruta sobre una cuenta, y 20 por minuto por IP, contra el recorrido de muchos emails. Al superarlos responde 429 `TOO_MANY_REQUESTS` con `Retry-After`.
- `GET /api/v1/auth/me` devuelve el usuario con `roles`, `permissions` y `warehouses`. Esos tres campos forman parte del contrato desde E1 y se completan en E2.

Autorización (se implementa en E2):

- `spatie/laravel-permission` para roles y permisos con nomenclatura `modulo.accion`.
- Policies que combinan permiso y pertenencia del recurso a un depósito asignado al usuario.
- Un recurso de un depósito ajeno responde 404 para no revelar su existencia.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Tokens guardados en el navegador (JWT en `localStorage`) | Expuestos ante XSS; requieren lógica de renovación |
| Frontend y API en orígenes distintos con CORS | Obliga a configurar `SameSite=None`, cookies seguras y CORS con credenciales en cada entorno; es el riesgo principal de Sanctum SPA |
| Sistema propio de roles y permisos | Reimplementa una solución estándar del ecosistema |

## Consecuencias

- Sin tokens accesibles desde JavaScript.
- Clientes que no sean la SPA (integraciones, scripts) no pueden autenticarse; si aparecen, se evalúan tokens de Sanctum con un ADR nuevo.
- La UI oculta acciones sin permiso solo por usabilidad; la autorización real está en el backend.
- Enmendado el 23/11/2026 con lo implementado en E1.07: detalle de la autenticación, desactivación de tokens, límites de login y códigos de error. Pasa a Aceptado en E2, al implementarse la autorización.
- Enmendado el 23/11/2026 en E1.09: verificación de origen por `Sec-Fetch-Site` previa al token CSRF, comprobada en el navegador contra el backend.
