<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Inbox\Http\Requests\InboxListRequest;
use App\Modules\Inbox\Http\Requests\UpdateInboxMessagesRequest;
use App\Modules\Inbox\Http\Resources\InboxMessageResource;
use App\Modules\Inbox\Models\InboxMessage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Bandeja de entrada propia (SBWP, E3.27). Cada usuario ve y marca solo sus
 * mensajes: no hace falta un permiso, como en los datos propios de SU3.
 */
class InboxController extends Controller
{
    /**
     * Mensajes propios, del más nuevo al más viejo.
     */
    public function index(InboxListRequest $request): AnonymousResourceCollection
    {
        return InboxMessageResource::collection($request->paginate(InboxMessage::query()->ownedBy(self::user($request))));
    }

    /**
     * Mensajes sin leer de la entrada, para el contador del encabezado.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            /** @var int */
            'unread' => InboxMessage::query()->ownedBy(self::user($request))->unread()->count(),
        ]]);
    }

    /**
     * Marca varios mensajes propios como leídos o no leídos, o los archiva o
     * los devuelve a la entrada. Archivar también marca leído; marcar no leído
     * un archivado lo devuelve a la entrada, porque un archivado siempre está
     * leído.
     */
    public function update(UpdateInboxMessagesRequest $request): Response
    {
        $read = $request->flag('read');
        $archived = $request->flag('archived');
        $values = [];

        if ($archived === true || $read === true) {
            $values['read_at'] = DB::raw('COALESCE(read_at, now())');
        }

        if ($archived === true) {
            $values['archived_at'] = DB::raw('COALESCE(archived_at, now())');
        }

        if ($read === false) {
            $values['read_at'] = null;
        }

        if ($archived === false || $read === false) {
            $values['archived_at'] = null;
        }

        InboxMessage::query()->ownedBy(self::user($request))->whereIn('id', $request->ids())->toBase()->update($values);

        return response()->noContent();
    }

    private static function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
