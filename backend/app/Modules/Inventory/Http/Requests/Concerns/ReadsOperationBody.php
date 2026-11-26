<?php

namespace App\Modules\Inventory\Http\Requests\Concerns;

/**
 * Campos comunes del cuerpo de una operación de inventario manual
 * (transferencia, ajuste, carga inicial): el depósito, que la Policy necesita
 * antes de validar, y las notas ya validadas.
 */
trait ReadsOperationBody
{
    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
    }

    /**
     * Depósito del cuerpo si es un identificador válido; si no, null y la
     * validación lo rechaza.
     */
    public function warehouseId(): ?int
    {
        $id = filter_var($this->input('warehouse_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }
}
