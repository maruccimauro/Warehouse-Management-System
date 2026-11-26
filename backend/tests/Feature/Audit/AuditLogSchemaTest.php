<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Registro de auditoría (E3.02, INV-012): de solo inserción, como el libro.
*/

beforeEach(function () {
    $this->auditLogId = DB::table('audit_logs')->insertGetId([
        'action' => 'warehouse.activated',
        'auditable_type' => 'warehouse',
        'auditable_id' => 1,
    ]);
});

it('records an entry with its occurrence time', function () {
    expect(DB::table('audit_logs')->where('id', $this->auditLogId)->value('occurred_at'))->not->toBeNull();
});

it('rejects a direct update or delete of an entry', function (string $statement) {
    try {
        DB::transaction(fn () => DB::statement($statement, [$this->auditLogId]));
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('audit_logs is append only');

        return;
    }

    throw new RuntimeException('La sentencia no falló.');
})->with([
    'UPDATE' => "UPDATE audit_logs SET action = 'otra.accion' WHERE id = ?",
    'DELETE' => 'DELETE FROM audit_logs WHERE id = ?',
]);
