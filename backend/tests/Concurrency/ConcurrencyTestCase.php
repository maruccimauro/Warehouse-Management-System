<?php

namespace Tests\Concurrency;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Base de las pruebas de concurrencia (E3.14, ADR-006). Los procesos hijos
 * abren sus propias conexiones y solo ven datos confirmados, así que no hay
 * transacción envolvente: la primera prueba migra la base desde cero y cada
 * una de las siguientes trunca las tablas antes de empezar. El trigger de
 * inmutabilidad es de fila y no intercepta TRUNCATE.
 *
 * movement_types se conserva: es el catálogo de referencia que carga la
 * migración, no datos de una prueba.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['movement_types'];
}
