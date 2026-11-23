import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { z } from 'zod';
import { defaultNS, fallbackLanguage, isSupportedLanguage, resources, supportedLanguages } from './resources';

// Mensajes de validación por defecto de Zod en el idioma activo. Los formularios definen
// sus propios mensajes con claves de traducción; estos cubren lo que quede sin mensaje.
const zodLocales = {
  es: z.locales.es,
} as const;

function applyLanguage(language: string): void {
  const resolved = isSupportedLanguage(language) ? language : fallbackLanguage;
  document.documentElement.lang = resolved;
  z.config(zodLocales[resolved]());
}

i18n.on('languageChanged', applyLanguage);

// Recursos incluidos en el bundle e inicialización síncrona: no hay parpadeo de claves
// sin traducir en el primer render. La preferencia del usuario (users.locale) llega en E2.
void i18n.use(initReactI18next).init({
  resources,
  lng: fallbackLanguage,
  fallbackLng: fallbackLanguage,
  supportedLngs: supportedLanguages,
  ns: Object.keys(resources[fallbackLanguage]),
  defaultNS,
  initAsync: false,
  interpolation: {
    // React ya escapa el contenido al renderizar.
    escapeValue: false,
  },
});

export { i18n };
