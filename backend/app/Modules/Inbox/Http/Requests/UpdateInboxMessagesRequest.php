<?php

namespace App\Modules\Inbox\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marcas sobre varios mensajes propios a la vez (selección múltiple de SBWP):
 * leído o no leído, archivado o en la entrada. Un identificador que no es un
 * mensaje del usuario es 422 en ids.N, sin distinguir si existe para otro.
 */
class UpdateInboxMessagesRequest extends FormRequest
{
    public const MAX_IDS = 200;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $userId = $user instanceof User ? $user->id : 0;

        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct', Rule::exists('inbox_messages', 'id')->where('recipient_id', $userId)],
            'read' => ['required_without:archived', 'boolean'],
            'archived' => ['required_without:read', 'boolean'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = $this->validated('ids');

        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []));
    }

    public function flag(string $name): ?bool
    {
        return $this->has($name) ? $this->boolean($name) : null;
    }
}
