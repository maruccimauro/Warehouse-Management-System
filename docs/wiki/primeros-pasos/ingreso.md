# Ingreso al sistema

Para trabajar en el WMS se necesita una cuenta de usuario, que crea el administrador del sistema con uno o más roles y los depósitos en los que el usuario opera.

## Iniciar sesión {#iniciar-sesion}

1. Abra la dirección del sistema en el navegador. Sin una sesión iniciada aparece la pantalla **Iniciar sesión**.
2. Ingrese el **Correo electrónico** y la **Contraseña** de la cuenta. La casilla **Mostrar contraseña** deja ver lo escrito.
3. Elija **Ingresar**.

Si se intentó abrir una pantalla concreta antes de iniciar la sesión (por ejemplo, un enlace a `/t/SU3`), el sistema la abre después del ingreso; si no, muestra la pantalla principal.

::: info
Por seguridad, el mensaje es el mismo cuando el correo no existe y cuando la contraseña es incorrecta ([`INVALID_CREDENTIALS`](/referencia/errores#invalid-credentials)).
:::

### Demasiados intentos {#demasiados-intentos}

Después de varios intentos fallidos seguidos, el sistema deja de aceptar ingresos durante un tiempo. El botón muestra la cuenta regresiva (**Disponible en 30 segundos**) y se habilita solo al terminar.

### Cuenta desactivada {#cuenta-desactivada}

Una cuenta desactivada por el administrador no puede ingresar, aunque la contraseña sea correcta: el sistema lo informa con [`USER_INACTIVE`](/referencia/errores#user-inactive). Desactivar una cuenta también cierra sus sesiones abiertas.

## Cambio de contraseña obligatorio {#cambio-obligatorio}

La contraseña que fija el administrador al crear la cuenta, o al resetearla, es **inicial**: sirve para ingresar una sola vez. Después del ingreso, el sistema muestra la pantalla **Cambiar contraseña** en lugar del menú, y no deja continuar hasta elegir una contraseña nueva.

1. Ingrese la **Contraseña actual** (la inicial).
2. Ingrese la **Contraseña nueva** y repítala en **Repetir contraseña nueva**. Debe tener al menos 12 caracteres y ser distinta de la actual.
3. Elija **Cambiar contraseña**.

Si prefiere hacerlo más tarde, **Cerrar sesión** sale del sistema; en el próximo ingreso se vuelve a pedir el cambio.

::: tip
La contraseña también se cambia en cualquier momento desde la transacción [SU3](./parametros-de-usuario#contrasena).
:::

## Cerrar sesión {#cerrar-sesion}

El botón **Cerrar sesión** está en el encabezado, junto al nombre del usuario. Si la transacción abierta tiene datos sin grabar, el sistema pide confirmación antes de salir ([datos sin grabar](./pantalla-principal#datos-sin-grabar)).

Una sesión que expiró se informa con [`UNAUTHENTICATED`](/referencia/errores#unauthenticated) y vuelve a la pantalla de ingreso.
