<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Numbering\InternalNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proveedor, como el maestro de acreedores de SAP. El identificador fiscal
 * equivale a STCD1: se guarda normalizado y se puede corregir. Un proveedor
 * inactivo no admite órdenes de compra nuevas y deja terminar las abiertas,
 * como el bloqueo de compras (SPERM) de SAP (REG-018).
 *
 * @property int $id
 * @property int $number
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
    /**
     * Número interno con diez dígitos (0000000012), como el número de acreedor
     * de SAP.
     */
    public static function formatNumber(int $number): string
    {
        return InternalNumber::format($number);
    }

    /**
     * Número escrito por el usuario (con o sin ceros) a entero; null si no es un número.
     */
    public static function parseNumber(string $value): ?int
    {
        return InternalNumber::parse($value);
    }

    /**
     * Próximo número interno de la secuencia de proveedores.
     */
    public static function nextNumber(): int
    {
        $next = DB::scalar("SELECT nextval('suppliers_number_seq')");

        return is_numeric($next) ? (int) $next : throw new \LogicException('suppliers_number_seq no devolvió un número.');
    }

    /**
     * Respaldo para los caminos que crean sin la Action (pruebas). CreateSupplier
     * asigna el número de forma explícita, porque los seeders corren sin eventos
     * de modelo (WithoutModelEvents).
     */
    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if (! isset($supplier->attributes['number'])) {
                $supplier->number = self::nextNumber();
            }
        });
    }

    public static function normalizeTaxId(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', $value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'number' => 'integer'];
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
