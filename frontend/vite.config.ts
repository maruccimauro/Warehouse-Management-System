import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// En desarrollo la SPA se sirve detrás de Nginx en el mismo host que la API (ADR-009):
// el navegador habla con http://localhost:8080 y Nginx reenvía a este servidor.
const publicPort = Number(process.env.WEB_PORT ?? 8080);

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    rolldownOptions: {
      output: {
        // Dependencias en chunks propios, separados del código de la aplicación: cambian con
        // menos frecuencia, así que el navegador las conserva en caché entre despliegues, y
        // ningún chunk supera el límite de 500 kB de Vite. Las transacciones se separan solas
        // por la carga diferida del registro (src/app/transactions.ts).
        codeSplitting: {
          groups: [
            { name: 'vendor-react', test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/ },
            { name: 'vendor-router', test: /node_modules[\\/]react-router[\\/]/ },
            { name: 'vendor-query', test: /node_modules[\\/]@tanstack[\\/](react-query|query-core)[\\/]/ },
            // La grilla (E2.00) solo la cargan las transacciones con listas: chunk propio.
            { name: 'vendor-table', test: /node_modules[\\/]@tanstack[\\/](react-table|table-core|react-store|store)[\\/]/ },
            { name: 'vendor-i18n', test: /node_modules[\\/](i18next|react-i18next)[\\/]/ },
            { name: 'vendor-forms', test: /node_modules[\\/](zod|react-hook-form|@hookform)[\\/]/ },
          ],
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Nginx reenvía con Host: localhost. Vite rechaza los nombres de host que no conoce
    // (protección contra DNS rebinding); las IP se aceptan siempre.
    allowedHosts: ['localhost'],
    // El cliente de HMR abre el WebSocket contra el puerto público de Nginx, no contra 5173.
    hmr: {
      clientPort: publicPort,
    },
    // Los eventos de archivos no atraviesan el montaje de Windows o macOS hacia el contenedor:
    // sin polling, Vite no detecta los cambios.
    watch: {
      usePolling: true,
      interval: 300,
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    restoreMocks: true,
    unstubGlobals: true,
  },
});
