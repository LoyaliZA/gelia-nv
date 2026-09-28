<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacidadAppPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacidad_app_responde_sin_autenticacion(): void
    {
        $this->get('/privacidad-app')
            ->assertOk()
            ->assertSee('aplicación móvil Gelia-nv', false);
    }
}
