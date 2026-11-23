<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Las pruebas de Feature arrancan la aplicación con Tests\TestCase y corren
| contra PostgreSQL real (base wms_testing). Unit y Architecture no arrancan
| la aplicación.
|
| RefreshDatabase se aplica por directorio de módulo, solo donde las pruebas
| usan tablas: cada prueba corre dentro de una transacción que se revierte.
*/

pest()->extend(TestCase::class)->in('Feature');

pest()->use(RefreshDatabase::class)->in('Feature/Identity', 'Feature/Database', 'Feature/Warehouse');
