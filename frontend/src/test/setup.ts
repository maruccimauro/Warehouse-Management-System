import '@testing-library/jest-dom/vitest';
import '@/i18n';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// jsdom refleja el atributo open de <dialog> pero no implementa showModal() ni close(). Este
// doble cubre lo que usa la aplicación: abrir, cerrar con returnValue y el evento close.
if (typeof HTMLDialogElement.prototype.showModal !== 'function') {
  HTMLDialogElement.prototype.showModal = function showModal(this: HTMLDialogElement) {
    this.open = true;
  };
  HTMLDialogElement.prototype.close = function close(this: HTMLDialogElement, returnValue?: string) {
    if (!this.open) {
      return;
    }

    if (returnValue !== undefined) {
      this.returnValue = returnValue;
    }

    this.open = false;
    this.dispatchEvent(new Event('close'));
  };
}

afterEach(() => {
  cleanup();
  // Estado del navegador que sobrevive entre pruebas en jsdom.
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  localStorage.clear();
});
