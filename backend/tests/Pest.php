<?php

use Tests\TestCase;

/*
| Las pruebas de Feature arrancan la aplicación con Tests\TestCase y corren
| contra PostgreSQL real (base wms_testing). Unit y Architecture no arrancan
| la aplicación.
*/

pest()->extend(TestCase::class)->in('Feature');
