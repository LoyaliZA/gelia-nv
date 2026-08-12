<?php

namespace Tests\Unit\Solicitudes;

use App\Services\Solicitudes\ResolverDestinatariosAlertaSolicitudService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResolverDestinatariosAlertaSolicitudServiceTest extends TestCase
{
    #[Test]
    public function sin_permisos_no_devuelve_destinatarios(): void
    {
        $resolver = new ResolverDestinatariosAlertaSolicitudService();

        $this->assertTrue(
            $resolver->porDepartamento(null, [])->isEmpty()
        );
        $this->assertTrue(
            $resolver->porDepartamento(1, [])->isEmpty()
        );
    }

    #[Test]
    public function roles_de_supervision_estan_definidos(): void
    {
        $this->assertSame(
            ['Super Admin', 'Administrador'],
            ResolverDestinatariosAlertaSolicitudService::ROLES_SUPERVISION
        );
    }
}
