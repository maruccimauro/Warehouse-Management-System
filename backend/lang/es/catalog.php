<?php

return [
    'barcode_invalid' => 'El campo :attribute solo admite caracteres ASCII imprimibles sin espacios, hasta 64.',
    'barcode_check_digit' => 'El campo :attribute tiene el largo de un GTIN y su dígito verificador no es válido.',
    'category_hierarchy_cycle' => [
        'title' => 'El cambio cerraría un ciclo en la jerarquía de categorías.',
        'detail' => 'La categoría :parent es :category o una de sus subcategorías y no puede ser su padre.',
    ],
    'category_hierarchy_too_deep' => [
        'title' => 'La jerarquía de categorías superaría la profundidad máxima.',
        'detail' => 'La jerarquía de categorías admite hasta :max niveles.',
    ],
];
