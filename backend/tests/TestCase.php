<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Request::create de Symfony agrega Accept-Language: en-us,en;q=0.5 a toda
        // petición de prueba. Se vacía para que una prueba sin la cabecera represente
        // un cliente sin preferencia de idioma (SetLocale responde en español); las
        // pruebas de idioma la envían explícitamente.
        $this->withHeader('Accept-Language', '');
    }
}
