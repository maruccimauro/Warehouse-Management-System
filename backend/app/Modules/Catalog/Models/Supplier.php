<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Proveedor, como el maestro de acreedores de SAP. El identificador fiscal
 * equivale a STCD1: se guarda normalizado y se puede corregir. Un proveedor
 * inactivo no admite órdenes de compra nuevas y deja terminar las abiertas,
 * como el bloqueo de compras (SPERM) de SAP (REG-018).
 *
 * @property int $id
 * @property string $tax_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['tax_id', 'name', 'email', 'phone', 'is_active'])]
class Supplier extends Model
{
    /** Formato del identificador ya normalizado (suppliers_tax_id_format_check). */
    public const TAX_ID_PATTERN = '/^[A-Z0-9]{5,20}$/';

    /**
     * Quita espacios, puntos, guiones y barras y pasa a mayúsculas: el CUIT
     * 30-71234567-1, el RUT 76.123.456-K y el NIF B-12345678 quedan sin
     * separadores. Sirve para cualquier país porque no valida dígitos
     * verificadores.
     */
    public static function normalizeTaxId(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', $value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function taxId(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeTaxId($value));
    }

    /**
     * El email se guarda en minúsculas (suppliers_email_lowercase_check).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)));
    }

    /**
     * @return HasMany<ProductSupplier, $this>
     */
    public function productLinks(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }
}
