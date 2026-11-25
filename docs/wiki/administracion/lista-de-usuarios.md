# Lista de usuarios (SUIM)

La transacción `SUIM` lista los usuarios con sus roles, depósitos y estado, para revisar quién tiene qué acceso. Exige el permiso [`users.view`](/referencia/permisos#users-view) o [`users.manage`](/referencia/permisos#users-manage) y está en el menú en **Sistema ▸ Usuarios**.

## Selección {#seleccion}

La transacción empieza con una [pantalla de selección](/primeros-pasos/pantallas-de-seleccion). Todos los criterios son opcionales: sin criterios, <kbd>F8</kbd> lista todos los usuarios, activos e inactivos.

### Usuario {#username}

Restringe los códigos de usuario con un valor, un rango, varios valores o un patrón con `*`. Por ejemplo, `J*` son los que empiezan con `J`. Ver [patrones](/primeros-pasos/pantallas-de-seleccion#patrones).

### Nombre {#name}

Restringe los nombres de la misma forma. Un patrón no distingue mayúsculas: `*pérez*` son los que contienen `Pérez`.

### Rol {#role}

Los usuarios que tienen ese rol, elegido con <kbd>F4</kbd>. Se acepta el nombre del rol, por ejemplo **Operador**.

### Depósito {#warehouse}

Los usuarios que tienen ese depósito asignado, elegido con <kbd>F4</kbd>.

### Estado {#status}

**Activo** o **Inactivo**, elegido con <kbd>F4</kbd>.

## Resultado {#resultado}

La [lista](/primeros-pasos/listas) muestra usuario, nombre, email, roles, depósitos, estado y si la contraseña es inicial.

- Se ordena por usuario, nombre o email.
- Doble clic o <kbd>Enter</kbd> sobre una fila abre el usuario en [Visualizar usuario](./visualizar-usuario).
- <kbd>F3</kbd> vuelve a la selección con los criterios cargados.
