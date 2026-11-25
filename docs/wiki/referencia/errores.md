# Errores

Cuando una acción no se realiza, la barra de estado muestra un mensaje E con el código del error entre paréntesis, por ejemplo **El depósito está inactivo. (WAREHOUSE_INACTIVE)**. Esta página explica cada código: qué significa y qué hacer. Los códigos son estables: el texto del mensaje puede cambiar, el código no.

## Sesión y acceso {#sesion-y-acceso}

### `INVALID_CREDENTIALS` {#invalid-credentials}

El correo electrónico o la contraseña no son correctos. El mensaje es el mismo en los dos casos, para no revelar qué cuentas existen.

**Qué hacer:** verifique el correo y la contraseña. Si no la recuerda, pida al administrador que la resetee.

### `USER_INACTIVE` {#user-inactive}

La cuenta está desactivada. El mensaje aparece solo con la contraseña correcta, como el aviso de usuario bloqueado de SAP.

**Qué hacer:** contacte al administrador para que reactive la cuenta.

### `PASSWORD_CHANGE_REQUIRED` {#password-change-required}

La contraseña es inicial o fue reseteada por el administrador, y hay que cambiarla antes de continuar. El sistema muestra la pantalla de [cambio de contraseña](/primeros-pasos/ingreso#cambio-obligatorio).

**Qué hacer:** elija una contraseña nueva.

### `UNAUTHENTICATED` {#unauthenticated}

La sesión expiró, se cerró desde otro lugar (por ejemplo, al cambiar la contraseña en otro equipo) o no se inició.

**Qué hacer:** vuelva a ingresar. Los datos que no se grabaron antes de expirar la sesión se pierden.

### `FORBIDDEN` {#forbidden}

El usuario no tiene el permiso que la acción exige, o el depósito no está asignado al usuario: se puede consultar, pero no operar en él. El mensaje explica cuál de los casos se aplica.

**Qué hacer:** si la tarea corresponde a su puesto, pida al administrador el rol o el depósito que falta. La [referencia de permisos](./permisos) indica qué rol tiene cada permiso.

### `TOO_MANY_REQUESTS` {#too-many-requests}

Se hicieron demasiados intentos en poco tiempo, por ejemplo de ingreso con una contraseña incorrecta.

**Qué hacer:** espere el tiempo indicado antes de volver a intentar.

### `CSRF_TOKEN_MISMATCH` {#csrf-token-mismatch}

La página estuvo inactiva demasiado tiempo y su clave de seguridad expiró.

**Qué hacer:** recargue la página e intente de nuevo.

### `SESSION_REQUIRED` {#session-required}

El ingreso se intentó sin una sesión del navegador, por ejemplo desde un programa externo. No ocurre al usar la aplicación.

**Qué hacer:** ingrese desde la pantalla de inicio de sesión.

## Datos maestros {#datos-maestros}

### `VALIDATION_FAILED` {#validation-failed}

Uno o más datos no son válidos. Cada campo con error muestra el motivo debajo y el primero aparece en la barra de estado ([formularios](/primeros-pasos/formularios#errores)).

**Qué hacer:** corrija los campos marcados y vuelva a grabar.

### `VALUE_ALREADY_EXISTS` {#value-already-exists}

El valor ya está registrado y debe ser único: el código de un depósito, de una zona o de una ubicación dentro del depósito, el correo de un usuario, el SKU o el código de barras de un producto, o el identificador fiscal de un proveedor.

**Qué hacer:** use otro valor o busque el registro existente.

### `INVALID_STATE_TRANSITION` {#invalid-state-transition}

El cambio de estado no está permitido desde el estado actual. Por ejemplo, una ubicación bloqueada no se desactiva: primero se desbloquea. Los cambios posibles de cada objeto están en la [referencia de estados](./estados).

**Qué hacer:** lleve el objeto a un estado desde el que el cambio esté permitido.

### `WAREHOUSE_INACTIVE` {#warehouse-inactive}

El depósito está inactivo y no admite operaciones: bloquear o desbloquear ubicaciones, y, en etapas posteriores, movimientos, recepciones, pedidos e inventarios físicos. Sí admite la consulta y el mantenimiento de sus zonas y ubicaciones.

**Qué hacer:** opere sobre otro depósito o pida su reactivación a quien administra los depósitos.

### `CATEGORY_HIERARCHY_CYCLE` {#category-hierarchy-cycle}

La categoría padre elegida es la propia categoría o una de sus subcategorías: el cambio cerraría un ciclo en la jerarquía.

**Qué hacer:** elija como padre una categoría que no dependa de la que está modificando.

### `CATEGORY_HIERARCHY_TOO_DEEP` {#category-hierarchy-too-deep}

La jerarquía de categorías superaría los 5 niveles, contando la raíz. Al mover una categoría se mueven también sus subcategorías, que cuentan para el límite.

**Qué hacer:** elija un padre de un nivel más alto o reorganice la rama.

### `LAST_ADMINISTRATOR` {#last-administrator}

El cambio dejaría el sistema sin ningún administrador activo, al quitar el rol o desactivar la cuenta.

**Qué hacer:** asigne antes el rol Administrador a otro usuario activo.

### `SELF_DEACTIVATION_NOT_ALLOWED` {#self-deactivation-not-allowed}

Un usuario no puede desactivar su propia cuenta.

**Qué hacer:** si la cuenta debe desactivarse, pídalo a otro administrador.

## Errores técnicos {#errores-tecnicos}

Estos errores no dependen de los datos ingresados. Si se repiten, informe el código y la hora al administrador.

### `NETWORK_ERROR` {#network-error}

No fue posible conectar con el servidor: sin conexión de red o servidor no disponible. Lo informa la aplicación, sin respuesta del servidor.

**Qué hacer:** verifique la conexión e intente de nuevo.

### `SERVICE_UNAVAILABLE` {#service-unavailable}

El servidor no puede atender la solicitud en este momento, por ejemplo durante un mantenimiento.

**Qué hacer:** intente de nuevo en unos minutos.

### `INTERNAL_ERROR` {#internal-error}

Ocurrió un error inesperado en el servidor. La operación no se realizó.

**Qué hacer:** intente de nuevo; si continúa, contacte al administrador.

### `INVALID_RESPONSE` {#invalid-response}

La respuesta del servidor no tiene el formato que la aplicación espera. Suele ocurrir con una pestaña abierta durante la publicación de una versión nueva.

**Qué hacer:** recargue la página.

### `NOT_FOUND` {#not-found}

El recurso no existe, o pertenece a un depósito que el usuario no puede consultar.

**Qué hacer:** verifique el código o la dirección.

### `IDEMPOTENCY_REQUEST_IN_PROGRESS` {#idempotency-request-in-progress}

La misma operación ya se está procesando, por ejemplo por un doble envío.

**Qué hacer:** espere el resultado de la primera antes de repetirla.

### `IDEMPOTENCY_KEY_REUSED` {#idempotency-key-reused}

Una operación repetida llegó con datos distintos de la original. Lo evita la aplicación; no debería verse al usarla.

**Qué hacer:** informe el error al administrador.

## Errores de la solicitud {#errores-de-solicitud}

Los siguientes códigos indican una solicitud que el servidor no acepta por su forma. La aplicación no las genera en el uso normal.

**Qué hacer:** recargue la página; si continúa, informe el código al administrador.

### `BAD_REQUEST` {#bad-request}

La solicitud está mal formada.

### `METHOD_NOT_ALLOWED` {#method-not-allowed}

La operación no existe para ese recurso.

### `CONFLICT` {#conflict}

La solicitud entra en conflicto con el estado actual del recurso.

### `PAYLOAD_TOO_LARGE` {#payload-too-large}

Los datos enviados superan el tamaño máximo admitido.

### `UNSUPPORTED_MEDIA_TYPE` {#unsupported-media-type}

Los datos se enviaron en un formato que el servidor no admite.

### `HTTP_ERROR` {#http-error}

Error del servidor sin un código más específico.
