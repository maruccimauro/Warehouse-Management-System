<?php

use App\Modules\Catalog\Rules\Barcode;

/*
| Dígito verificador GS1 de los GTIN (D4 de E2.05). Los códigos válidos son
| reales o calculados con el algoritmo de módulo 10.
*/

it('accepts GTINs with a valid check digit', function (string $gtin) {
    expect(Barcode::hasValidCheckDigit($gtin))->toBeTrue();
})->with([
    'EAN-8' => '96385074',
    'UPC-A' => '036000291452',
    'EAN-13' => '4006381333931',
    'EAN-13 con dígito 0' => '7790000000010',
    'GTIN-14' => '10614141000415',
]);

it('rejects GTINs with a wrong check digit', function (string $gtin) {
    expect(Barcode::hasValidCheckDigit($gtin))->toBeFalse();
})->with(['96385075', '036000291453', '4006381333932', '10614141000416']);
