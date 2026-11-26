<?php

/*
| Única puerta de escritura sobre el stock (ADR-002, E3.03): ninguna clase
| fuera de StockLedger escribe en stocks ni en stock_movements. Los save() y
| delete() de los modelos los frena en ejecución WrittenOnlyByStockLedger;
| esta prueba busca en el código lo que no pasa por los eventos del modelo:
| DB::table, SQL directo, escrituras masivas del query builder y las
| variantes que no disparan eventos.
*/

const LEDGER_TABLES = 'stocks|stock_movements';

const LEDGER_MODELS = 'Stock|StockMovement';

/**
 * Escrituras sobre las tablas del libro que encuentra en el texto de un archivo.
 *
 * @return list<string>
 */
function ledgerWritesIn(string $source): array
{
    $patterns = [
        '/DB::table\(\s*[\'"]('.LEDGER_TABLES.')[\'"]\s*\)/',
        '/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+"?('.LEDGER_TABLES.')\b/i',
        '/\b('.LEDGER_MODELS.')::(query|where\w*|whereKey)\([^;]*?->(update|delete|forceDelete|insert\w*|upsert|increment\w*|decrement\w*|truncate)\(/s',
        '/\b('.LEDGER_MODELS.')::(insert\w*|upsert|truncate|destroy)\(/',
    ];

    $usesLedgerModels = preg_match('/use App\\\\Modules\\\\Inventory\\\\Models\\\\('.LEDGER_MODELS.');/', $source) === 1;

    if ($usesLedgerModels) {
        $patterns[] = '/\b(saveQuietly|deleteQuietly|updateQuietly|forceDeleteQuietly|withoutEvents)\b/';
    }

    $found = [];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $source, $matches) > 0) {
            array_push($found, ...$matches[0]);
        }
    }

    return $found;
}

function ledgerApplicationRoot(): string
{
    return dirname(__DIR__, 2).'/app';
}

it('finds the writes of StockLedger itself, so the search is not blind', function () {
    $source = (string) file_get_contents(ledgerApplicationRoot().'/Modules/Inventory/Services/StockLedger.php');

    expect(ledgerWritesIn($source))->not->toBeEmpty()
        ->and(ledgerWritesIn("DB::table('stock_movements')->insert([]);"))->not->toBeEmpty()
        ->and(ledgerWritesIn("Stock::query()->where('id', 1)->update(['quantity_on_hand' => 0]);"))->not->toBeEmpty()
        ->and(ledgerWritesIn("Stock::query()->where('id', 1)->sum('quantity_on_hand');"))->toBeEmpty();
});

it('writes stocks and stock_movements only from StockLedger', function () {
    $root = ledgerApplicationRoot();
    $allowed = [
        $root.'/Modules/Inventory/Services/StockLedger.php',
    ];
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        $path = str_replace('\\', '/', $file->getPathname());

        // Las migraciones crean las tablas y sus triggers: no son escrituras de datos.
        if ($file->getExtension() !== 'php' || in_array($path, $allowed, true) || str_contains($path, '/Database/Migrations/')) {
            continue;
        }

        $writes = ledgerWritesIn((string) file_get_contents($path));

        if ($writes !== []) {
            $offenders[substr($path, strlen($root) + 1)] = $writes;
        }
    }

    expect($offenders)->toBe([]);
});
