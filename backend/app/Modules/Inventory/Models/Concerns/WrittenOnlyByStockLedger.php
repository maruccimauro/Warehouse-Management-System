<?php

namespace App\Modules\Inventory\Models\Concerns;

use App\Modules\Inventory\Services\StockLedger;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Única puerta de escritura sobre el stock (ADR-002, E3.03): el modelo se
 * graba o se borra solo mientras StockLedger contabiliza una operación. Un
 * save() o delete() desde cualquier otro lugar lanza LogicException.
 *
 * Cubre los eventos del modelo; las escrituras que no los disparan
 * (DB::table, SQL directo, update o delete masivos del query builder,
 * saveQuietly) las detecta la prueba de arquitectura
 * tests/Architecture/StockLedgerWritesTest.php.
 *
 * @phpstan-require-extends Model
 */
trait WrittenOnlyByStockLedger
{
    public static function bootWrittenOnlyByStockLedger(): void
    {
        $guard = static function (Model $model): void {
            if (! StockLedger::isApplying()) {
                throw new LogicException($model->getTable().' se escribe solo desde StockLedger::apply.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }
}
