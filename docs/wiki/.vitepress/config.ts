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
          { text: 'Parámetros de usuario (SU3)', link: '/primeros-pasos/parametros-de-usuario' },
        ],
      },
      {
        text: 'Supervisor',
        items: [{ text: 'Bloqueo de ubicaciones (ZW05)', link: '/supervisor/bloqueo-de-ubicaciones' }],
      },
      {
        text: 'Compras',
        items: [
          { text: 'Crear proveedor (XK01)', link: '/compras/crear-proveedor' },
          { text: 'Modificar proveedor (XK02)', link: '/compras/modificar-proveedor' },
          { text: 'Visualizar proveedor (XK03)', link: '/compras/visualizar-proveedor' },
          { text: 'Productos del proveedor (ZW06)', link: '/compras/productos-del-proveedor' },
        ],
      },
      {
        text: 'Administración',
        items: [
          { text: 'Depósitos (ZW01)', link: '/administracion/depositos' },
          { text: 'Zonas (ZW02)', link: '/administracion/zonas' },
          { text: 'Crear ubicación (LS01N)', link: '/administracion/crear-ubicacion' },
          { text: 'Modificar ubicación (LS02N)', link: '/administracion/modificar-ubicacion' },
          { text: 'Visualizar ubicación (LS03N)', link: '/administracion/visualizar-ubicacion' },
          { text: 'Generar ubicaciones (LS10)', link: '/administracion/generar-ubicaciones' },
          { text: 'Lista de ubicaciones (LX03)', link: '/administracion/lista-de-ubicaciones' },
          { text: 'Crear producto (MM01)', link: '/administracion/crear-producto' },
          { text: 'Modificar producto (MM02)', link: '/administracion/modificar-producto' },
          { text: 'Visualizar producto (MM03)', link: '/administracion/visualizar-producto' },
          { text: 'Lista de productos (MM60)', link: '/administracion/lista-de-productos' },
        ],
      },
      {
        text: 'Referencia',
        items: [
          { text: 'Transacciones', link: '/referencia/transacciones' },
          { text: 'Teclas', link: '/referencia/teclas' },
          { text: 'Estados', link: '/referencia/estados' },
          { text: 'Mensajes', link: '/referencia/mensajes' },
          { text: 'Permisos y roles', link: '/referencia/permisos' },
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
