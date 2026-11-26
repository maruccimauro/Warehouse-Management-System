<?php

return [
    'insufficient_stock' => [
        'title' => 'There is not enough stock.',
        'detail' => 'Item :sku has :available units available in location :location and the operation removes :requested.',
    ],
    'reserved_stock_not_available' => [
        'title' => 'The stock is reserved.',
        'detail' => 'Item :sku has :reserved units reserved in location :location: :available remain available and the operation removes :requested.',
    ],
    'location_blocked' => [
        'title' => 'The location does not accept movements.',
        'detail' => 'Location :location is not active: it is blocked, being counted or inactive.',
    ],
    'location_capacity_exceeded' => [
        'title' => 'The operation exceeds the location capacity.',
        'detail' => 'Location :location holds up to :max units: it has :current and the operation adds :incoming.',
    ],
    'product_not_operable' => [
        'title' => 'The item status does not allow the operation.',
        'detail' => 'Item :sku is blocked: it only allows adjustments, physical inventory and reversals.',
    ],
    'reversal_exceeds_remaining' => [
        'title' => 'The reversal exceeds what remains to be reversed.',
        'detail' => 'The quantity to reverse on a line exceeds its unreversed balance.',
    ],
];
