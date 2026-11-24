# ADR-009: Autenticación SPA con Sanctum y autorización por permisos y depósito

- Estado: Aceptado
- Fecha: 22/11/2026
- Aceptado: 23/11/2026, al implementarse la autorización en E2.07 y E2.08

## Contexto

El frontend es una SPA en React. La API necesita autenticación segura y autorización granular por rol, permiso y depósito asignado.

## Decisión

Autenticación (implementada en E1.07):

- Laravel Sanctum en modo SPA: cookie de sesión `HttpOnly` con `SameSite=Lax` y protección CSRF con la cookie `XSRF-TOKEN`, que el cliente obtiene en `GET /sanctum/csrf-cookie` y devuelve en la cabecera `X-XSRF-TOKEN`. Laravel 13 verifica primero el origen (`PreventRequestForgery`): acepta la petición sin comparar tokens si el navegador envía `Sec-Fetch-Site: same-origin`, cabecera que el navegador fija y que ningún script puede modificar (Fetch Metadata). El token queda como respaldo para navegadores sin Fetch Metadata, y el cliente lo envía siempre; ante un 419 renueva la cookie y reintenta una sola vez.
- Frontend y API se sirven bajo el mismo host a través de Nginx en todos los entornos. Las peticiones son del mismo origen: CORS no habilita ningún origen externo salvo configuración explícita (`CORS_ALLOWED_ORIGINS`).
- Solo sesión, sin tokens. La lectura de bearer tokens de Sanctum está desactivada: una petición con `Authorization: Bearer` se trata como no autenticada.
- `POST /api/v1/auth/login` requiere una petición de un dominio con estado (`SANCTUM_STATEFUL_DOMAINS`); sin sesión responde 400 `SESSION_REQUIRED`. Las credenciales incorrectas responden 422 `INVALID_CREDENTIALS`, con el mismo mensaje si falla el email o la contraseña. El login regenera el identificador de sesión para evitar la fijación de sesión, y el logout invalida la sesión y el token CSRF.
- Rate limiting en el login con dos límites: 5 intentos por minuto por email e IP, contra la fuerza bruta sobre una cuenta, y 20 por minuto por IP, contra el recorrido de muchos emails. Al superarlos responde 429 `TOO_MANY_REQUESTS` con `Retry-After`.
- `GET /api/v1/auth/me` devuelve el usuario con `roles`, `permissions` y `warehouses`. Esos tres campos forman parte del contrato desde E1 y se completan en E2: `permissions` son los efectivos y `warehouses` los depósitos activos donde el usuario opera (`id`, `code`, `name`, ordenados por código), que alimentan la ayuda de búsqueda de depósito.

Autorización (implementada en E2.07 y E2.08):

- `spatie/laravel-permission` para roles y permisos con nomenclatura `modulo.accion`, sin la función de equipos. El catálogo está en código (`Permission` y `Role`) y `wms:sync-authorization` lo lleva a la base en cada entorno.
- Alcance por depósito en dos niveles, como el campo `WERKS` de los objetos de autorización de SAP con su actividad:

| Nivel | Equivale en SAP | Alcanza |
| - | - | - |
| Consulta | Actividad 03 | Depósitos asignados en `user_warehouse`, o todos con `warehouses.view_all` |
| Operación | Actividades 01 y 02 | Depósitos asignados, o todos con `warehouses.operate_all` |

- Todos los roles tienen `warehouses.view_all`, para ubicar mercadería entre depósitos; `warehouses.operate_all` es solo del Administrador. Cambiar quién consulta u opera en todos es configuración de `Role::permissions()`, no código de las Policies.
- `App\Modules\Identity\Authorization\WarehouseAccess` resuelve el alcance. Las Policies lo usan en este orden: fuera del alcance de consulta, 404 `NOT_FOUND` para no revelar la existencia del recurso; visible pero sin operación en ese depósito, 403 `FORBIDDEN`; sin el permiso de la acción, 403 `FORBIDDEN`. Un depósito visible no se oculta con 404 porque el usuario ya puede consultarlo.
- Los listados se filtran con los scopes `visibleTo` y `operableBy` del trait `ScopedByWarehouse`, que usan los modelos con `warehouse_id`.
- No se usa un `Gate::before` de superadministrador: saltearía las reglas de segregación por documento (REG-001, INV-010), que también alcanzan al Administrador.

## Alternativas consideradas

| Alternativa | Motivo de descarte |
| - | - |
| Tokens guardados en el navegador (JWT en `localStorage`) | Expuestos ante XSS; requieren lógica de renovación |
| Frontend y API en orígenes distintos con CORS | Obliga a configurar `SameSite=None`, cookies seguras y CORS con credenciales en cada entorno; es el riesgo principal de Sanctum SPA |
| Sistema propio de roles y permisos | Reimplementa una solución estándar del ecosistema |
| Acceso total por nombre de rol (`hasRole('administrator')`) | Ata las Policies a un rol; dar el acceso a otro rol exige cambiar código. En SAP es un valor de autorización del rol |
| Depósito ajeno invisible también para consultar | Impide ubicar mercadería en otros depósitos, que SAP permite con la actividad 03 |
| Consulta libre para cualquier sesión, sin permiso | No permite restringir la consulta de un rol sin cambiar código |

## Consecuencias

- Sin tokens accesibles desde JavaScript.
- Clientes que no sean la SPA (integraciones, scripts) no pueden autenticarse; si aparecen, se evalúan tokens de Sanctum con un ADR nuevo.
- La UI oculta acciones sin permiso solo por usabilidad; la autorización real está en el backend.
- Enmendado el 23/11/2026 con lo implementado en E1.07: detalle de la autenticación, desactivación de tokens, límites de login y códigos de error. Pasa a Aceptado en E2, al implementarse la autorización.
- Enmendado el 23/11/2026 en E1.09: verificación de origen por `Sec-Fetch-Site` previa al token CSRF, comprobada en el navegador contra el backend.
- Aceptado el 23/11/2026 con lo implementado en E2.07 y E2.08: catálogo en código, alcance por depósito en dos niveles (consulta y operación) y orden 404, 403 de las Policies. El alcance de consulta separado reemplaza el 404 para todo depósito ajeno que preveía la versión propuesta.
- Enmendado el 24/11/2026 con la gestión de usuarios (E2.09). Estado de la cuenta: el login verifica la contraseña antes que el estado (`attemptWhen`, que conserva el timebox) y responde 403 `USER_INACTIVE` solo con la contraseña correcta (REG-020); dos middlewares del grupo `api`, `EnsureUserIsActive` y `RequirePasswordChange`, cierran con 401 la sesión de un usuario inactivo y limitan a `GET /auth/me`, el cierre de sesión y `PUT /me/password` la de un usuario con contraseña inicial (REG-021). Desactivar o resetear la contraseña borra las sesiones del usuario en `sessions`; el middleware es la garantía de fondo, porque cubre un login simultáneo y las sesiones fuera de la base (ADR-011). Contra la escalada de privilegios, `UserPolicy` compara permisos y no nombres de rol (REG-023), con el mismo criterio que descartó `hasRole('administrator')`. Contraseñas de al menos 12 caracteres con `Password::defaults()`, sin consulta a servicios externos de contraseñas filtradas.
