<?php

return [
    'insufficient_stock' => [
        'title' => 'No hay stock suficiente.',
        'detail' => 'El artículo :sku tiene :available unidades disponibles en la ubicación :location y la operación retira :requested.',
    ],
    'reserved_stock_not_available' => [
        'title' => 'El stock está reservado.',
        'detail' => 'El artículo :sku tiene :reserved unidades reservadas en la ubicación :location: quedan :available disponibles y la operación retira :requested.',
    ],
    'location_blocked' => [
        'title' => 'La ubicación no admite movimientos.',
        'detail' => 'La ubicación :location no está activa: está bloqueada, en conteo o inactiva.',
    ],
    'location_capacity_exceeded' => [
        'title' => 'La operación supera la capacidad de la ubicación.',
        'detail' => 'La ubicación :location admite :max unidades: tiene :current y la operación agrega :incoming.',
    ],
    'product_not_operable' => [
        'title' => 'El estado del artículo no admite la operación.',
        'detail' => 'El artículo :sku está bloqueado: admite solo ajustes, inventario físico y reversiones.',
    ],
    'reversal_exceeds_remaining' => [
        'title' => 'La reversión supera lo que queda por revertir.',
        'detail' => 'La cantidad a revertir de una línea supera su saldo sin revertir.',
    ],
    'duplicate_stock_in_items' => 'La posición :item mueve el mismo artículo en la misma ubicación que la posición :other: cada existencia se mueve en una sola posición.',
    'same_source_and_destination' => [
        'title' => 'El origen y el destino son la misma ubicación.',
        'detail' => 'La posición :item transfiere de la ubicación :location a sí misma.',
    ],
    'system_location_not_allowed' => [
        'title' => 'La ubicación de sistema no admite esta operación.',
        'detail' => 'La posición :item tiene como destino la ubicación de sistema :location: solo la cargan sus documentos.',
    ],
    'adjustment_item_not_pending' => [
        'title' => 'La posición de ajuste ya no está pendiente.',
        'detail' => 'La posición :item ya no está pendiente de aprobación: se contabilizó al crear el ajuste o ya la aprobó o rechazó otro usuario.',
    ],
];
