<?php

namespace App\Modules\Inbox\Http\Resources;

use App\Modules\Inbox\Models\InboxMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InboxMessage
 */
class InboxMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Tipo del aviso (entidad.evento); la interfaz arma el texto con él y los parámetros. */
            'type' => $this->type,
            /** @var array<string, scalar|null> */
            'parameters' => (object) $this->parameters,
            /** Objeto al que se refiere el aviso, para abrirlo desde la bandeja. */
            'subject' => $this->subject_type === null ? null : [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
            ],
            'read_at' => $this->read_at?->toJSON(),
            'archived_at' => $this->archived_at?->toJSON(),
            'created_at' => $this->created_at->toJSON(),
        ];
    }
}
