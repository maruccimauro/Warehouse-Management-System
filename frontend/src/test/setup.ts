import '@testing-library/jest-dom/vitest';
import '@/i18n';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => {
  cleanup();
  // Estado del navegador que sobrevive entre pruebas en jsdom.
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  localStorage.clear();
});
