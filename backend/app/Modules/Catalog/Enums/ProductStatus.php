<?php

namespace App\Modules\Catalog\Enums;

/**
 * Estados de un producto; coinciden con products_status_check. Equivalen al
 * estado de material de SAP (MSTAE), que define qué operaciones se rechazan
 * (REG-017, domain-model.md):
 *
 * - Active: admite todo.
 * - Discontinued: rechaza documentos nuevos (orden de compra en E4, pedido en
 *   E5) y deja terminar lo abierto para agotar el stock.
 * - Blocked: rechaza todo movimiento salvo el inventario físico y los ajustes,
 *   que sirven para corregir.
 *
 * Las transiciones entre los tres son libres y se auditan.
 */
enum ProductStatus: string
{
    case Active = 'active';
    case Discontinued = 'discontinued';
    case Blocked = 'blocked';
}
