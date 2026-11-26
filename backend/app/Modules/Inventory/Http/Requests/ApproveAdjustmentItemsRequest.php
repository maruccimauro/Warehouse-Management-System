<?php

namespace App\Modules\Inventory\Http\Requests;

/**
 * POST /inventory/adjustments/{id}/approve (E3.23): las notas son opcionales.
 */
class ApproveAdjustmentItemsRequest extends DecideAdjustmentItemsRequest
{
    protected function notesRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'max:500'];
    }
}
