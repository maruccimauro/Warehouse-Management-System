<?php

namespace App\Modules\Inventory\Http\Requests;

/**
 * POST /inventory/adjustments/{id}/reject (E3.23): el motivo es obligatorio
 * (REG-024) y se copia en cada posición rechazada.
 */
class RejectAdjustmentItemsRequest extends DecideAdjustmentItemsRequest
{
    protected function notesRules(): array
    {
        return ['required', 'string', 'max:500'];
    }

    public function reason(): string
    {
        return (string) $this->notes();
    }
}
