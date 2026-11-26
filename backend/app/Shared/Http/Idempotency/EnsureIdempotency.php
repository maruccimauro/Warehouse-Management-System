<?php

namespace App\Shared\Http\Idempotency;

use App\Shared\Exceptions\IdempotencyKeyRequired;
use App\Shared\Exceptions\IdempotencyKeyReused;
use App\Shared\Exceptions\IdempotencyRequestInProgress;
use Closure;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware de idempotencia (ADR-007, INV-007), alias idempotent.
 *
 * Abre la transacción más externa de la petición, con los intentos de
 * ADR-004, y adentro:
 *
 * 1. Reclama la clave con INSERT ... ON CONFLICT DO NOTHING. Si otra petición
 *    con la misma clave está en curso, este INSERT espera en el índice único
 *    hasta que la otra termina; si la espera supera el lock_timeout
 *    configurado, 409 IDEMPOTENCY_REQUEST_IN_PROGRESS.
 * 2. Si la clave ya estaba confirmada: con el mismo hash devuelve la
 *    respuesta almacenada (cabecera Idempotent-Replayed); con otro, 422
 *    IDEMPOTENCY_KEY_REUSED. El hash cubre método, ruta y cuerpo.
 * 3. Si no, ejecuta la petición. Una respuesta 2xx se guarda en la fila de la
 *    clave y se confirma junto con la operación; cualquier otra revierte la
 *    transacción, no deja clave, y su reintento se evalúa de nuevo.
 *
 * El controlador corre dentro de esta transacción: la Action abre la suya,
 * que queda anidada, y un deadlock en ella llega como respuesta con la
 * excepción adjunta. El middleware la vuelve a lanzar para que esta
 * transacción, la externa, reintente la petición completa (ADR-004).
 */
final class EnsureIdempotency
{
    /** Intentos de la transacción externa ante un deadlock (ADR-004). */
    private const ATTEMPTS = 3;

    public function __construct(
        private readonly ConcurrencyErrorDetector $concurrencyErrors,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = Idempotency::keyOf($request) ?? throw new IdempotencyKeyRequired;
        $identifier = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($identifier) ? (int) $identifier : throw new LogicException('El middleware idempotent va después de auth:sanctum.');
        $hash = self::hash($request);

        try {
            return DB::transaction(function () use ($request, $next, $key, $userId, $hash): Response {
                $stored = $this->claim($userId, $key, $request, $hash);

                if ($stored !== null) {
                    return self::replay($stored, $hash);
                }

                $response = $next($request);
                $exception = property_exists($response, 'exception') ? $response->exception : null;

                if ($exception instanceof Throwable && $this->concurrencyErrors->causedByConcurrencyError($exception)) {
                    throw $exception;
                }

                if (! $response->isSuccessful()) {
                    throw new UnstoredResponse($response);
                }

                $content = (string) $response->getContent();

                DB::table('idempotency_keys')->where('user_id', $userId)->where('key', $key)->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $content === '' ? 'null' : $content,
                ]);

                return $response;
            }, attempts: self::ATTEMPTS);
        } catch (UnstoredResponse $unstored) {
            return $unstored->response;
        }
    }

    /**
     * Inserta la clave con una respuesta provisional, que nadie más ve hasta
     * el commit. Devuelve la fila confirmada si la clave ya existía.
     *
     * @return object{request_hash: string, response_status: int, response_body: string}|null
     */
    private function claim(int $userId, string $key, Request $request, string $hash): ?object
    {
        $previousTimeout = DB::scalar('SHOW lock_timeout');
        $previousTimeout = is_string($previousTimeout) ? $previousTimeout : '0';
        DB::select("SELECT set_config('lock_timeout', ?, true)", [self::lockTimeout()]);

        try {
            $inserted = DB::select(
                "INSERT INTO idempotency_keys (user_id, key, request_method, request_path, request_hash, response_status, response_body)
                 VALUES (?, ?, ?, ?, ?, 0, 'null')
                 ON CONFLICT ON CONSTRAINT idempotency_keys_user_id_key_unique DO NOTHING
                 RETURNING id",
                [$userId, $key, $request->method(), mb_substr($request->path(), 0, 255), $hash],
            );
        } catch (QueryException $exception) {
            throw ($exception->errorInfo[0] ?? null) === '55P03' ? new IdempotencyRequestInProgress(previous: $exception) : $exception;
        }

        // El resto de la operación vuelve al lock_timeout de la sesión.
        DB::select("SELECT set_config('lock_timeout', ?, true)", [$previousTimeout]);

        if ($inserted !== []) {
            return null;
        }

        /** @var object{request_hash: string, response_status: int, response_body: string} */
        return DB::table('idempotency_keys')
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first(['request_hash', 'response_status', 'response_body']);
    }

    /**
     * @param  object{request_hash: string, response_status: int, response_body: string}  $stored
     */
    private static function replay(object $stored, string $hash): Response
    {
        if (! hash_equals($stored->request_hash, $hash)) {
            throw new IdempotencyKeyReused;
        }

        return response($stored->response_body === 'null' ? '' : $stored->response_body, (int) $stored->response_status, [
            'Content-Type' => 'application/json',
            Idempotency::REPLAYED_HEADER => 'true',
        ]);
    }

    /**
     * Hash del método, la ruta y el cuerpo, con las claves de los objetos
     * ordenadas: el mismo contenido en otro orden es la misma petición.
     */
    private static function hash(Request $request): string
    {
        $body = json_encode(self::canonical($request->all()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', $request->method()."\n".$request->path()."\n".$body);
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private static function lockTimeout(): string
    {
        $milliseconds = config('wms.idempotency.lock_timeout_ms');

        return (is_numeric($milliseconds) ? (int) $milliseconds : 5000).'ms';
    }
}
