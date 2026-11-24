<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Supplier;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Alta de proveedor con suppliers.manage. Nace activo (REG-018). El
 * identificador fiscal se normaliza antes de validar, para que la regla unique
 * compare contra lo que se va a guardar: 30-71234567-1 y 30712345671 son el
 * mismo proveedor.
 */
class StoreSupplierRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Supplier::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeSupplierFields();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_id' => self::taxIdRules(),
            'name' => ['required', 'string', 'max:200'],
            'is_active' => ['prohibited'],
            ...self::contactRules(),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function taxIdRules(?Supplier $ignore = null): array
    {
        return ['required', 'string', 'regex:'.Supplier::TAX_ID_PATTERN, Rule::unique('suppliers', 'tax_id')->ignore($ignore?->id)];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function contactRules(): array
    {
        return [
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Identificador fiscal sin separadores y en mayúsculas, email en
     * minúsculas y textos vacíos como nulos.
     */
    protected function normalizeSupplierFields(): void
    {
        $normalized = [];

        $taxId = $this->input('tax_id');
        if (is_string($taxId)) {
            $normalized['tax_id'] = Supplier::normalizeTaxId($taxId);
        }

        $email = $this->input('email');
        if (is_string($email)) {
            $email = mb_strtolower(trim($email));
            $normalized['email'] = $email === '' ? null : $email;
        }

        $phone = $this->input('phone');
        if (is_string($phone)) {
            $phone = trim($phone);
            $normalized['phone'] = $phone === '' ? null : $phone;
        }

        $this->merge($normalized);
    }
}
