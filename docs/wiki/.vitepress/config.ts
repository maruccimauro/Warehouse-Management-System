import { defineConfig } from 'vitepress';

// Wiki de usuario del WMS (DM-11). Se sirve en /docs/ del mismo host que la aplicación:
// en desarrollo, Nginx reenvía /docs/ al servicio wiki de docker-compose; en producción,
// Caddy sirve el build estático (E8.12). La arquitectura de la información y el estilo
// están en la skill generar-wiki-wms.
//
// En desarrollo el navegador habla con Nginx (WEB_PORT): el cliente de HMR abre el
// WebSocket contra ese puerto, como el servidor de Vite del frontend.
const publicPort = Number(process.env.WEB_PORT ?? 8080);

export default defineConfig({
  lang: 'es',
  title: 'WMS',
  description: 'Manual de usuario del sistema de gestión de depósitos.',
  base: '/docs/',
  cleanUrls: true,
  // Sin lastUpdated: necesita el historial de git, que el contenedor no ve, y con el
  // checkout superficial del CI daría la misma fecha a todas las páginas.
  // Los enlaces rotos hacen fallar el build: la ayuda F1 y los enlaces de la aplicación
  // apuntan a estas páginas.
  ignoreDeadLinks: false,
  // Fragmentos que varias páginas incluyen con <!--@include: ...-->: una sola fuente para la
  // ayuda de campos que comparten transacciones (los de la ficha de ubicación). No son
  // páginas propias.
  srcExclude: ['**/partes/**'],
  head: [['meta', { name: 'theme-color', content: '#1f4e79' }]],
  vite: {
    server: {
      host: '0.0.0.0',
      port: 5174,
      strictPort: true,
      allowedHosts: ['localhost'],
      hmr: { clientPort: publicPort },
      // Los eventos de archivos no atraviesan el montaje de Windows o macOS hacia el
      // contenedor: sin polling, VitePress no detecta los cambios.
      watch: { usePolling: true, interval: 300 },
    },
  },
  themeConfig: {
    nav: [
      { text: 'Primeros pasos', link: '/primeros-pasos/ingreso' },
      { text: 'Supervisor', link: '/supervisor/bloqueo-de-ubicaciones' },
      { text: 'Compras', link: '/compras/crear-proveedor' },
      { text: 'Administración', link: '/administracion/depositos' },
      { text: 'Referencia', link: '/referencia/transacciones' },
    ],
    sidebar: [
      {
        text: 'Primeros pasos',
        items: [
          { text: 'Ingreso al sistema', link: '/primeros-pasos/ingreso' },
          { text: 'La pantalla principal', link: '/primeros-pasos/pantalla-principal' },
          { text: 'Formularios', link: '/primeros-pasos/formularios' },
          { text: 'Ayuda de búsqueda (F4)', link: '/primeros-pasos/ayuda-de-busqueda' },
          { text: 'Pantallas de selección', link: '/primeros-pasos/pantallas-de-seleccion' },
          { text: 'Listas', link: '/primeros-pasos/listas' },
          { text: 'Gestionar parámetros de usuario (SU3)', link: '/primeros-pasos/parametros-de-usuario' },
        ],
      },
      {
        text: 'Supervisor',
        items: [
          { text: 'Bloqueo de ubicaciones (ZW05)', link: '/supervisor/bloqueo-de-ubicaciones' },
          { text: 'Gestionar stock mínimo (ZW04)', link: '/supervisor/stock-minimo' },
          { text: 'Modificar stock mínimo (ZW42)', link: '/supervisor/modificar-stock-minimo' },
          { text: 'Visualizar stock mínimo (ZW43)', link: '/supervisor/visualizar-stock-minimo' },
        ],
      },
      {
        text: 'Compras',
        items: [
          { text: 'Crear proveedor (XK01)', link: '/compras/crear-proveedor' },
          { text: 'Modificar proveedor (XK02)', link: '/compras/modificar-proveedor' },
          { text: 'Visualizar proveedor (XK03)', link: '/compras/visualizar-proveedor' },
          { text: 'Gestionar catálogo del proveedor (ZW06)', link: '/compras/catalogo-del-proveedor' },
          { text: 'Crear artículo del proveedor (ZW61)', link: '/compras/crear-articulo-del-proveedor' },
          { text: 'Modificar artículo del proveedor (ZW62)', link: '/compras/modificar-articulo-del-proveedor' },
          { text: 'Visualizar artículo del proveedor (ZW63)', link: '/compras/visualizar-articulo-del-proveedor' },
        ],
      },
      {
        text: 'Administración',
        items: [
          { text: 'Gestionar depósitos (ZW01)', link: '/administracion/depositos' },
          { text: 'Crear depósito (ZW11)', link: '/administracion/crear-deposito' },
          { text: 'Modificar depósito (ZW12)', link: '/administracion/modificar-deposito' },
          { text: 'Visualizar depósito (ZW13)', link: '/administracion/visualizar-deposito' },
          { text: 'Gestionar zonas (ZW02)', link: '/administracion/zonas' },
          { text: 'Crear zona (ZW21)', link: '/administracion/crear-zona' },
          { text: 'Modificar zona (ZW22)', link: '/administracion/modificar-zona' },
          { text: 'Visualizar zona (ZW23)', link: '/administracion/visualizar-zona' },
          { text: 'Crear ubicación (LS01N)', link: '/administracion/crear-ubicacion' },
          { text: 'Modificar ubicación (LS02N)', link: '/administracion/modificar-ubicacion' },
          { text: 'Visualizar ubicación (LS03N)', link: '/administracion/visualizar-ubicacion' },
          { text: 'Crear múltiples ubicaciones (LS10)', link: '/administracion/crear-multiples-ubicaciones' },
          { text: 'Lista de ubicaciones (LX03)', link: '/administracion/lista-de-ubicaciones' },
          { text: 'Crear catálogo (MM01)', link: '/administracion/crear-catalogo' },
          { text: 'Modificar catálogo (MM02)', link: '/administracion/modificar-catalogo' },
          { text: 'Visualizar catálogo (MM03)', link: '/administracion/visualizar-catalogo' },
          { text: 'Lista del catálogo (MM60)', link: '/administracion/lista-del-catalogo' },
          { text: 'Gestionar umbrales de ajuste (ZW35)', link: '/administracion/umbrales-de-ajuste' },
          { text: 'Modificar umbrales de ajuste (ZW36)', link: '/administracion/modificar-umbrales-de-ajuste' },
          { text: 'Visualizar umbrales de ajuste (ZW37)', link: '/administracion/visualizar-umbrales-de-ajuste' },
          { text: 'Gestionar categorías (ZW07)', link: '/administracion/categorias' },
          { text: 'Crear categoría (ZW71)', link: '/administracion/crear-categoria' },
          { text: 'Modificar categoría (ZW72)', link: '/administracion/modificar-categoria' },
          { text: 'Visualizar categoría (ZW73)', link: '/administracion/visualizar-categoria' },
          { text: 'Gestionar unidades de medida (ZW08)', link: '/administracion/unidades-de-medida' },
          { text: 'Crear unidad de medida (ZW81)', link: '/administracion/crear-unidad-de-medida' },
          { text: 'Modificar unidad de medida (ZW82)', link: '/administracion/modificar-unidad-de-medida' },
          { text: 'Visualizar unidad de medida (ZW83)', link: '/administracion/visualizar-unidad-de-medida' },
          { text: 'Gestionar usuarios (SU01)', link: '/administracion/usuarios' },
          { text: 'Crear usuario (ZW91)', link: '/administracion/crear-usuario' },
          { text: 'Modificar usuario (ZW92)', link: '/administracion/modificar-usuario' },
          { text: 'Visualizar usuario (ZW93)', link: '/administracion/visualizar-usuario' },
          { text: 'Lista de usuarios (SUIM)', link: '/administracion/lista-de-usuarios' },
          { text: 'Visualizar rol (PFCG)', link: '/administracion/visualizar-rol' },
        ],
      },
      {
        text: 'Referencia',
        items: [
          { text: 'Transacciones', link: '/referencia/transacciones' },
          { text: 'Teclas', link: '/referencia/teclas' },
          { text: 'Estados', link: '/referencia/estados' },
          { text: 'Movimientos', link: '/referencia/movimientos' },
          { text: 'Mensajes', link: '/referencia/mensajes' },
          { text: 'Permisos y roles', link: '/referencia/permisos' },
          { text: 'Reglas de negocio', link: '/referencia/reglas-de-negocio' },
        ],
      },
    ],
    outline: { level: 'deep', label: 'En esta página' },
    editLink: {
      pattern: 'https://github.com/maruccimauro/Warehouse-Management-System/edit/main/docs/wiki/:path',
      text: 'Editar esta página en GitHub',
    },
    socialLinks: [{ icon: 'github', link: 'https://github.com/maruccimauro/Warehouse-Management-System' }],
    docFooter: { prev: 'Anterior', next: 'Siguiente' },
    darkModeSwitchLabel: 'Apariencia',
    lightModeSwitchTitle: 'Cambiar a modo claro',
    darkModeSwitchTitle: 'Cambiar a modo oscuro',
    sidebarMenuLabel: 'Menú',
    returnToTopLabel: 'Volver arriba',
    langMenuLabel: 'Idioma',
    notFound: {
      title: 'Página no encontrada',
      quote: 'La dirección no existe o la página fue movida.',
      linkLabel: 'Ir al inicio',
      linkText: 'Ir al inicio',
    },
    footer: {
      message: 'Sistema de gestión de depósitos.',
    },
    search: {
      provider: 'local',
      options: {
        translations: {
          button: { buttonText: 'Buscar', buttonAriaLabel: 'Buscar' },
          modal: {
            displayDetails: 'Mostrar la lista detallada',
            resetButtonTitle: 'Borrar la búsqueda',
            backButtonTitle: 'Cerrar la búsqueda',
            noResultsText: 'Sin resultados para',
            footer: {
              selectText: 'para elegir',
              selectKeyAriaLabel: 'Enter',
              navigateText: 'para moverse',
              navigateUpKeyAriaLabel: 'Flecha arriba',
              navigateDownKeyAriaLabel: 'Flecha abajo',
              closeText: 'para cerrar',
              closeKeyAriaLabel: 'Escape',
            },
          },
        },
      },
    },
  },
});
