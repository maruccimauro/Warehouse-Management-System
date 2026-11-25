<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Número interno del proveedor (25/11/2026, decisión del usuario).
 *
 * Lo asigna una secuencia propia, separada del id: el id es la clave técnica
 * y puede cambiar con una migración de datos; el número es el que se dicta,
 * se escribe en XK02 y XK03 y se imprime en los documentos. Se guarda como
 * entero y la API lo muestra con ceros a la izquierda hasta seis dígitos
 * (000001); por encima sigue creciendo sin truncarse. Los proveedores
 * existentes reciben su número en el orden de alta.
 *
 * La columna no tiene DEFAULT nextval: la convención del esquema reserva las
 * secuencias implícitas para nadie (las claves son IDENTITY). El número lo toma
 * el modelo al crear el proveedor (Supplier::booted), como un rango de
 * numeración que asigna la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE suppliers_number_seq AS BIGINT START WITH 1');
        DB::statement('ALTER TABLE suppliers ADD COLUMN number BIGINT');
        DB::statement(<<<'SQL'
            UPDATE suppliers SET number = numbered.position
            FROM (SELECT id, nextval('suppliers_number_seq') AS position FROM (SELECT id FROM suppliers ORDER BY id) AS ordered) AS numbered
            WHERE suppliers.id = numbered.id
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE suppliers
              ALTER COLUMN number SET NOT NULL,
              ADD CONSTRAINT suppliers_number_unique UNIQUE (number),
              ADD CONSTRAINT suppliers_number_check CHECK (number > 0)
            SQL);
        DB::statement('ALTER SEQUENCE suppliers_number_seq OWNED BY suppliers.number');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE suppliers DROP COLUMN number');
    }
};
