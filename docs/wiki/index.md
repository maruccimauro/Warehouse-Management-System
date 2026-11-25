---
layout: home

hero:
  name: WMS
  text: Sistema de gestión de depósitos
  tagline: Manual de usuario. Inventario, recepción, preparación de pedidos y despacho, operados con el teclado.
  actions:
    - theme: brand
      text: Primeros pasos
      link: /primeros-pasos/ingreso
    - theme: alt
      text: Referencia
      link: /referencia/transacciones

features:
  - title: Transacciones
    details: Cada pantalla es una transacción con un código. Se abre desde el menú o escribiendo el código en el campo de comando.
    link: /primeros-pasos/pantalla-principal
  - title: Teclado primero
    details: F3 vuelve atrás, F4 busca valores, F8 ejecuta y Ctrl+S graba. Todo se opera sin mouse.
    link: /referencia/teclas
  - title: Mensajes con código
    details: La barra de estado informa el resultado de cada acción con su tipo y su código, que se explica en la referencia de mensajes.
    link: /referencia/mensajes
---

## Ideas clave

**Una transacción es una pantalla con un código.** `SU3` abre los parámetros de usuario.

**El depósito es un campo de cada pantalla operativa.** No hay un depósito activo global: cada pantalla muestra sobre qué depósito opera, con el valor por defecto que fija el parámetro de usuario `LGN`.

**El menú muestra lo que el usuario puede hacer.** Las transacciones aparecen según los permisos de los roles asignados; la [referencia de permisos](/referencia/permisos) explica qué habilita cada uno.

**La barra de estado informa el resultado.** Cada acción termina con un mensaje de éxito (S), advertencia (W), error (E) o información (I). Los errores muestran su código entre paréntesis, que se busca en la [referencia de mensajes](/referencia/mensajes).
