<?php

use Tests\Support\Concurrency\ConcurrentRun;

/*
| Reintento ante deadlock (E3.19, ADR-004): dos procesos toman los bloqueos
| consultivos 999002 y 999003 en orden cruzado. PostgreSQL aborta a uno con
| 40P01; DB::transaction lo reconoce con PostgresConcurrencyErrorDetector y lo
| reintenta. Protege el detector propio ante cambios de Laravel.
*/

const DEADLOCK_KEYS = [999002, 999003];

/**
 * @return list<array<string, int>>
 */
function crossedLocks(int $attempts): array
{
    [$first, $second] = DEADLOCK_KEYS;

    return [
        ['first' => $first, 'second' => $second, 'attempts' => $attempts],
        ['first' => $second, 'second' => $first, 'attempts' => $attempts],
    ];
}

it('retries the deadlock victim until both transactions commit', function () {
    $results = ConcurrentRun::scenario('deadlock', crossedLocks(attempts: 3));

    expect(array_column($results, 'status'))->toBe(['ok', 'ok'])
        ->and(array_sum(array_column($results, 'attempts')))->toBe(3);
});

it('answers 503 CONCURRENCY_CONFLICT when the attempts run out', function () {
    $results = ConcurrentRun::scenario('deadlock', crossedLocks(attempts: 1));

    $rejected = array_values(array_filter($results, fn (array $result): bool => $result['status'] === 'rejected'));

    expect(array_column($results, 'status'))->toContain('ok')
        ->and($rejected)->toHaveCount(1)
        ->and($rejected[0])->toMatchArray(['code' => 'CONCURRENCY_CONFLICT', 'http_status' => 503, 'attempts' => 1]);
});
