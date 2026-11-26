---
name: generar-wiki-wms
description: Escribe y mantiene la wiki de usuario del WMS, un sitio VitePress en docs/wiki servido en /docs/. Usar al crear, reescribir o actualizar cualquier página de la wiki, al documentar una transacción, un estado, un error o un permiso nuevos, al agregar una clave de ayuda (F1, HelpLink) o al tocar la arquitectura de la información, el estilo o el build de la wiki.
---

# Generar y mantener la wiki del WMS

La wiki es el manual de usuario. La aplicación enlaza a ella con claves de ayuda estables (DM-11): F1 sobre un campo, el código de un error en la barra de estado y los enlaces junto a estados y permisos. Por eso las anclas son parte del contrato y no se cambian.

## 1. Arquitectura de la información

| Sección | Carpeta | Contenido |
| - | - | - |
| Primeros pasos | `primeros-pasos/` | Ingreso, pantalla principal, formularios, F4, pantallas de selección, listas y las transacciones de cualquier usuario (SU3) |
| Operario | `operario/` | Transacciones de la operación diaria del depósito |
| Supervisor | `supervisor/` | Aprobaciones, reversiones, bloqueos e inventario físico |
| Compras | `compras/` | Proveedores y órdenes de compra |
| Administración | `administracion/` | Depósitos, zonas y ubicaciones, catálogo, usuarios y roles |
| Referencia | `referencia/` | Transacciones, teclas, estados, movimientos, mensajes, permisos y reglas de negocio |

- Una sección se publica (carpeta, `sidebar` y `nav` en `.vitepress/config.ts`) cuando tiene su primera página real. No hay páginas "en construcción".
- Cada transacción tiene una página propia en la sección del puesto que la usa y una fila en `referencia/transacciones.md`.
- Lo que comparten varias transacciones (los campos de la ficha de ubicación en crear, modificar y visualizar) se escribe una sola vez en un fragmento de `<sección>/partes/` y cada página lo incluye con `<!--@include: ./partes/archivo.md-->`. `partes/` está en `srcExclude`: no genera páginas propias. Las anclas del fragmento quedan en cada página que lo incluye, y `check:help-links` expande los `@include` al verificarlas.
- Las rutas y los nombres de archivo van en español, en minúsculas y con guiones: `primeros-pasos/parametros-de-usuario.md`.

## 2. Anclas y claves de ayuda

Toda clave de ayuda lleva un ancla explícita con `{#...}`, que no cambia si cambia el título:

| Objeto | Página | Ancla | Ejemplo |
| - | - | - | - |
| Transacción | Su página | (la página) | `primeros-pasos/parametros-de-usuario` |
| Campo de una transacción | La página de la transacción | Nombre del campo en el formulario | `primeros-pasos/parametros-de-usuario#lgn` |
| Estado | `referencia/estados` | `<objeto>-<estado>` en inglés, como en la API | `estados#location-blocked` |
| Mensaje (E o W, del servidor o de la pantalla) | `referencia/mensajes` | `code` en minúsculas con guiones | `mensajes#warehouse-inactive` |
| Permiso | `referencia/permisos` | Nombre con guiones en lugar de puntos y guiones bajos | `permisos#warehouses-view-all` |
| Movimiento (desde E3) | `referencia/movimientos` | `mov-<código>` | `movimientos#mov-101` |

La ayuda de un campo se documenta en la página de cada transacción que lo usa, con un título por campo (decisión D7 de E2.16), aunque el campo se repita en otras pantallas.

## 3. Plantilla de página

```md
# Título de la página

Párrafo inicial: qué es, para qué sirve y quién la usa.

## Tarea o bloque {#ancla}

Pasos numerados para las tareas; tablas para opciones, teclas y estados.

::: tip
Atajo o buena práctica.
:::
```

- Página de transacción: párrafo inicial con código, permiso y ruta en el menú (**Sistema ▸ Datos de usuario**); un `##` por bloque de la pantalla y un `###` con ancla por campo; mensajes y errores posibles con enlace a `referencia/mensajes`. Todo aviso o error que la pantalla puede mostrar (límites, valores inexistentes, rangos) se explica en la página de la transacción y tiene su entrada con código en `referencia/mensajes`, para que el usuario sepa qué pasó, por qué y cómo seguir.
- Página de transacción que mueve stock: lleva una sección `## Movimientos que genera {#movimientos}` con una tabla de los códigos que registra (código con enlace a `referencia/movimientos#mov-NNN`, dirección y cuándo se genera) y el código con que se revierte. En el mismo lote, la fila del código en `referencia/movimientos` nombra la transacción en la columna **Lo genera**, con enlace a su página. Un código entra en `referencia/movimientos` en el lote que implementa la operación que lo genera, no antes.
- Reglas de negocio: `referencia/reglas-de-negocio` reúne las reglas que el sistema controla siempre, agrupadas por tema, cada una con un `###` y ancla descriptiva en español (`{#deposito-inactivo}`, nunca el identificador interno REG o INV), qué dice, **Por qué:** y **Mensajes:** con enlace a `referencia/mensajes`. Una regla entra en el lote que la hace visible desde la interfaz, no antes; el lote que la cambia actualiza su entrada. La tabla de temas al principio de la página nombra cada regla.
- Página de referencia: tabla de cambios posibles al principio y un `###` con ancla por entrada, con **Qué hacer:** en los errores.

## 4. Estilo

- Registro de la aplicación: usted (**Ingrese**, **Elija**), oraciones cortas, sin marketing.
- Textos de la interfaz (botones, campos, títulos) en negrita y exactamente como en `frontend/src/i18n/locales/es/`: **Grabar**, **Mostrar códigos de transacción**.
- Rutas de menú con `▸`: **Sistema ▸ Datos de usuario**.
- Teclas con `<kbd>`, con los nombres de teclado en español: <kbd>Mayús</kbd>+<kbd>F3</kbd>, <kbd>Inicio</kbd>, <kbd>RePág</kbd>.
- Códigos de transacción, de error, de estado y de permiso en `código`.
- Definir cada término la primera vez que aparece en la página.
- Sin guion doble (`--`) en el texto. Registro profesional.
- La wiki describe el sistema por sí mismo. No menciona SAP ni ningún otro sistema de referencia ("como en SAP", "equivale a", "los códigos siguen los de SAP"): la orientación en SAP es un criterio interno de diseño y vive en los ADR y la planificación, no en la documentación de usuario. Tampoco menciona etapas, tareas, decisiones (DM, REG, ADR) ni el MVP: lo que no existe todavía no se describe.
- Avisos con contenedores de VitePress: `::: tip` (atajo), `::: info` (contexto), `::: warning` (riesgo de pérdida de datos o de un efecto no evidente).

## 5. Verificar antes de documentar

Lo que dice la wiki sale del código o de la aplicación, no del plan:

- Textos y etiquetas: archivos de `frontend/src/i18n/locales/es/`.
- Comportamiento: el componente o la Action correspondiente, ADR-016 y `domain-model.md`.
- Errores: `docs/02-analysis/business-rules.md` (catálogo) y `backend/lang/es/`.
- Permisos y roles: `backend/app/Modules/Identity/Authorization/`.
- Ante una duda de comportamiento, probarlo en la aplicación (Chrome) antes de escribirlo.

## 6. Configuración y build

- Paquete propio en `docs/wiki/` (`package.json` y `package-lock.json`), separado del frontend: VitePress 1.6 trae Vite 5 y el frontend usa Vite 8.
- `.vitepress/config.ts`: `base: '/docs/'`, `cleanUrls`, `ignoreDeadLinks: false`, textos del tema en español y búsqueda local. Cada página nueva se registra en `sidebar`.
- Desarrollo: servicio `wiki` de `docker-compose.yml` (`vitepress dev` en el puerto 5174); Nginx reenvía `/docs/` y la wiki se ve en `http://localhost:8080/docs/`.
- Build: `npm run build` en el servicio `wiki`. Falla con enlaces rotos entre páginas; las anclas de las claves de ayuda las verifica `npm run check:help-links` (E2.16).
- Publicación en producción: E8.12.

## 7. Lista de control al agregar o actualizar una página

1. Ubicarla en la sección correcta (§1) y registrarla en `sidebar`.
2. Seguir la plantilla (§3) y el estilo (§4).
3. Agregar las anclas de las claves de ayuda nuevas (§2) y sus entradas en `frontend/src/shared/help/helpLinks.ts`.
4. Verificar cada afirmación contra el código o la aplicación (§5).
5. Quitar lo que la funcionalidad nueva deja obsoleto.
6. Build y verificación de claves sin errores (§6).
