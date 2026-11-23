<?php

use Illuminate\Support\Facades\DB;

/*
| Convenciones del esquema (database-design.md, E2.01). Recorre las tablas
| reales después de migrar, así una migración nueva que use timestamp(),
| timestamps() o id() sin generatedAs() rompe la suite.
|
| Las tablas de infraestructura de Laravel quedan como sus migraciones
| publicadas y se excluyen.
*/

const LARAVEL_INFRASTRUCTURE_TABLES = [
    'migrations', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
    'jobs', 'job_batches', 'failed_jobs',
];

/**
 * @return list<string>
 */
function columnsWhere(string $condition): array
{
    $placeholders = implode(', ', array_fill(0, count(LARAVEL_INFRASTRUCTURE_TABLES), '?'));

    return DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->whereRaw("table_name NOT IN ({$placeholders})", LARAVEL_INFRASTRUCTURE_TABLES)
        ->whereRaw($condition)
        ->orderBy('table_name')
        ->orderBy('column_name')
        ->get(['table_name', 'column_name'])
        ->map(fn (object $column): string => "{$column->table_name}.{$column->column_name}")
        ->all();
}

it('stores every date and time as TIMESTAMPTZ', function () {
    expect(columnsWhere("data_type = 'timestamp without time zone'"))->toBe([]);
});

it('keeps microsecond precision in TIMESTAMPTZ columns', function () {
    expect(columnsWhere("data_type = 'timestamp with time zone' AND datetime_precision <> 6"))->toBe([]);
});

it('uses identity columns instead of serial sequences', function () {
    expect(columnsWhere("column_default LIKE 'nextval(%'"))->toBe([]);
});

it('checks the conventions against at least one domain table', function () {
    expect(columnsWhere("is_identity = 'YES'"))->toContain('users.id');
});
