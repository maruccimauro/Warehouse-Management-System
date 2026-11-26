# Regla: la wiki va en el mismo lote que la funcionalidad

La página de wiki de una funcionalidad se escribe o se actualiza en el mismo lote (commit o serie de commits de la tarea) que la funcionalidad, nunca en una sesión posterior. Una funcionalidad sin su página no está terminada.

- Lo que se reemplaza se quita de la wiki: no queda contenido obsoleto al lado del nuevo.
- Se documenta solo lo que existe y se verificó en el código o en la aplicación. Lo planificado para etapas posteriores no se describe como disponible.
- La wiki y los textos de la aplicación no mencionan SAP ni equivalencias con SAP: esa referencia es interna (ADR, planificación, comentarios de código).
- Toda clave de ayuda nueva (transacción, campo, estado, mensaje, permiso) tiene su ancla explícita `{#...}` en la página que corresponde, en el mismo lote.
- Toda regla de negocio que una funcionalidad hace visible desde la interfaz, o que cambia, se escribe o se actualiza en `referencia/reglas-de-negocio` en el mismo lote (D16.8 del plan E3).
- Todo mensaje E o W que una pantalla puede mostrar (del servidor o propio, como un límite superado) lleva código (`id` del mensaje), entrada en `referencia/mensajes` con qué significa y qué hacer, y la clave en `documentedMessageCodes` de `helpLinks.ts`.

## Dónde y cómo

- La wiki de usuario es un sitio VitePress en `docs/wiki/`, en español, servido en `/docs/` del mismo host que la aplicación (DM-11).
- Se escribe y se mantiene con la skill `generar-wiki-wms`, que define la arquitectura de la información, la plantilla de página, el estilo y las anclas.
- Antes de cerrar el lote, `npm run build` en el servicio `wiki` sin errores ni enlaces rotos:

```sh
MSYS_NO_PATHCONV=1 timeout 300 docker compose exec -T wiki npm run build
```
