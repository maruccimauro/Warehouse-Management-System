<?php

namespace App\Shared\Localization;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Idiomas admitidos por la API (app.supported_locales). Los usan SetLocale,
 * para resolver Accept-Language, y el módulo Identity, para aplicar el idioma
 * preferido del usuario (users.locale) cuando se autentica.
 */
final readonly class SupportedLocales
{
    public function __construct(private Config $config) {}

    /**
     * @return non-empty-list<string>
     */
    public function all(): array
    {
        $configured = $this->config->get('app.supported_locales');
        $locales = is_array($configured) ? array_values(array_filter($configured, is_string(...))) : [];

        if ($locales === []) {
            $fallback = $this->config->get('app.locale');

            return [is_string($fallback) ? $fallback : 'es'];
        }

        return $locales;
    }

    public function supports(string $locale): bool
    {
        return in_array($locale, $this->all(), true);
    }
}
