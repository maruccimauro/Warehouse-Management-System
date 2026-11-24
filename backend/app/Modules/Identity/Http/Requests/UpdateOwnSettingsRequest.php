<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Preferencias propias en users (PATCH /me, E2.21): idioma y mostrar los
 * códigos de transacción. Cada clave ausente queda como está.
 */
class UpdateOwnSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'required', 'string', Rule::in(config()->array('app.supported_locales'))],
            'show_technical_names' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array{locale?: string, show_technical_names?: bool}
     */
    public function settings(): array
    {
        $settings = [];

        if ($this->has('locale')) {
            $settings['locale'] = $this->string('locale')->toString();
        }

        if ($this->has('show_technical_names')) {
            $settings['show_technical_names'] = $this->boolean('show_technical_names');
        }

        return $settings;
    }
}
