# Mensajes

La barra de estado informa el resultado de cada acción. Cuando una acción no se realiza (mensaje E) o se realiza con una observación (mensaje W), el mensaje lleva un código entre paréntesis, por ejemplo **El depósito está inactivo. (WAREHOUSE_INACTIVE)**. El código es un enlace a esta página, que explica qué significa cada uno y qué hacer. Los códigos son estables: el texto del mensaje puede cambiar, el código no.

Algunos mensajes los emite el servidor al rechazar una operación; otros, la propia pantalla, antes de enviar nada, cuando lo ingresado no puede funcionar (por ejemplo, una plantilla que supera el límite de un lote).

## Sesión y acceso {#sesion-y-acceso}

### `INVALID_CREDENTIALS` {#invalid-credentials}

El correo electrónico o la contraseña no son correctos. El mensaje es el mismo en los dos casos, para no revelar qué cuentas existen.

**Qué hacer:** verifique el correo y la contraseña. Si no la recuerda, pida al administrador que la resetee.

### `USER_INACTIVE` {#user-inactive}

La cuenta está desactivada. El mensaje aparece solo con la contraseña correcta.

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

El valor ya está registrado y debe ser único: el código de un depósito, de una zona o de una ubicación dentro del depósito, el código o el email de un usuario, el SKU o el código de barras de un artículo, el identificador fiscal de un proveedor o el código del proveedor entre sus artículos. También se informa al vincular a un proveedor un artículo que ya tiene vinculado.

**Qué hacer:** use otro valor o busque el registro existente.

### `INVALID_STATE_TRANSITION` {#invalid-state-transition}

El cambio de estado no está permitido desde el estado actual. Por ejemplo, una ubicación bloqueada no se desactiva: primero se desbloquea. Los cambios posibles de cada objeto están en la [referencia de estados](./estados).

**Qué hacer:** lleve el objeto a un estado desde el que el cambio esté permitido.

### `WAREHOUSE_INACTIVE` {#warehouse-inactive}

El depósito está inactivo y no admite operaciones, como bloquear o desbloquear ubicaciones. Sí admite la consulta y el mantenimiento de sus zonas y ubicaciones.

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

## Mensajes de las pantallas {#mensajes-de-pantallas}

La pantalla los emite al verificar lo ingresado, antes de consultar o grabar.

### `REQUIRED_FIELDS_MISSING` {#required-fields-missing}

Falta completar un campo obligatorio de una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion). El foco va al campo.

**Qué hacer:** complete el campo y vuelva a ejecutar.

### `VALUE_NOT_FOUND` {#value-not-found}

El valor escrito en un campo con [ayuda de búsqueda](/primeros-pasos/ayuda-de-busqueda#verificacion) no está entre los valores posibles: no existe o no está disponible para el usuario, por ejemplo un depósito no asignado o una zona de sistema donde solo se admiten zonas propias.

**Qué hacer:** elija el valor con <kbd>F4</kbd> o corrija lo escrito.

### `INTEGER_EXPECTED` {#integer-expected}

Un criterio numérico de una pantalla de selección tiene un valor que no es un número entero.

**Qué hacer:** escriba un número entero, sin decimales ni letras.

### `RANGE_LOW_MISSING` {#range-low-missing}

Un rango tiene el valor **hasta** pero no el **desde**.

**Qué hacer:** complete el desde, o pase el valor al desde si busca un único valor.

### `RANGE_INVERTED` {#range-inverted}

En un rango numérico, el desde es mayor que el hasta.

**Qué hacer:** intercambie los límites.

### `PATTERN_IN_RANGE` {#pattern-in-range}

Un rango tiene el comodín `*`. Los [patrones](/primeros-pasos/pantallas-de-seleccion#patrones) solo se admiten en valores individuales.

**Qué hacer:** use un valor individual con `*`, por ejemplo `A-01*`, o quite el comodín del rango.

### `GRID_EXPORT_TRUNCATED` {#grid-export-truncated}

Mensaje W. La [exportación](/primeros-pasos/listas#exportar) de una lista paginada se detuvo en las primeras 5.000 filas: el archivo no tiene todo el resultado.

**Qué hacer:** restrinja la selección o los filtros y exporte por partes.

### `LOCATION_NOT_FOUND` {#location-not-found}

La ubicación indicada no existe en el depósito. La ficha no se abre.

**Qué hacer:** verifique el depósito y el código, o busque la ubicación con <kbd>F4</kbd>.

### `LOCATION_BATCH_TOO_LARGE` {#location-batch-too-large}

La plantilla de [Crear múltiples ubicaciones](/administracion/crear-multiples-ubicaciones#limite) crea más ubicaciones que el máximo por lote (2.000). El mensaje indica la cantidad calculada y el máximo; no se crea ninguna ubicación.

**Qué hacer:** divida la estructura en varios lotes, por ejemplo uno por grupo de pasillos.

### `PRODUCT_NOT_FOUND` {#product-not-found}

No existe un artículo con el SKU indicado. La ficha no se abre o, al vincular un artículo a un proveedor, el vínculo no se graba.

**Qué hacer:** verifique el SKU o busque el artículo con <kbd>F4</kbd>, por nombre o por código de barras.

### `SUPPLIER_NOT_FOUND` {#supplier-not-found}

No existe un proveedor con el número indicado. La ficha no se abre.

**Qué hacer:** verifique el número o busque el proveedor con <kbd>F4</kbd> por nombre o identificador fiscal.


### `USER_NOT_FOUND` {#user-not-found}

No existe un usuario con el código indicado. La ficha no se abre.

**Qué hacer:** verifique el código o busque el usuario con <kbd>F4</kbd> por nombre o email. Para darlo de alta, use [Crear usuario](/administracion/crear-usuario).

### `PASSWORD_MISMATCH` {#password-mismatch}

Al [asignar una contraseña inicial](/administracion/modificar-usuario#contrasena-inicial), la repetición no coincide con la contraseña. El diálogo queda abierto y la contraseña no se asigna.

**Qué hacer:** ingrese la misma contraseña en los dos campos y elija **Aplicar**.

### `SUPPLIER_PRODUCT_NOT_FOUND` {#supplier-product-not-found}

El artículo existe, pero no está vinculado al proveedor indicado. La ficha no se abre.

**Qué hacer:** verifique el proveedor y el artículo, o vincúlelos con [Crear artículo del proveedor](/compras/crear-articulo-del-proveedor).

### `WAREHOUSE_SETTING_NOT_SAVED` {#warehouse-setting-not-saved}

Mensaje W. Al grabar un artículo con un depósito en la pantalla inicial, los datos generales se grabaron, pero el stock mínimo del depósito no. El mensaje indica el motivo, por ejemplo que el usuario no opera en ese depósito.

**Qué hacer:** corrija la causa y cargue el stock mínimo en [Modificar catálogo](/administracion/modificar-catalogo) con el mismo depósito.

## Errores técnicos {#errores-tecnicos}

Estos errores no dependen de los datos ingresados. Si se repiten, informe el código y la hora al administrador.

### `NETWORK_ERROR` {#network-error}

No fue posible conectar con el servidor: sin conexión de red o servidor no disponible. Lo informa la aplicación, sin respuesta del servidor.

**Qué hacer:** verifique la conexión e intente de nuevo.

### `SERVICE_UNAVAILABLE` {#service-unavailable}

El servidor no puede atender la solicitud en este momento, por ejemplo durante un mantenimiento.

**Qué hacer:** intente de nuevo en unos minutos.

### `CONCURRENCY_CONFLICT` {#concurrency-conflict}

Otra operación modificaba los mismos datos al mismo tiempo y el servidor no pudo completar la suya después de varios intentos. La operación no se realizó y no quedó ningún cambio a medias.

**Qué hacer:** repita la operación en unos segundos. Si el mensaje se repite, contacte al administrador.

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
