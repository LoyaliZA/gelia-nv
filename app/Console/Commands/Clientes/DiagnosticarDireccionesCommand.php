<?php

namespace App\Console\Commands\Clientes;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Console\Command;

class DiagnosticarDireccionesCommand extends Command
{
    protected $signature = 'direcciones:diagnosticar';

    protected $description = 'Diagnostica cobertura de direcciones normalizadas vs campos de contacto legado';

    public function handle(): int
    {
        $totalClientes = Cliente::query()->count();
        $conContacto = Cliente::query()
            ->where(function ($q) {
                $q->whereNotNull('direccion_contacto')
                    ->orWhereNotNull('colonia_contacto')
                    ->orWhereNotNull('cp_contacto');
            })
            ->count();

        $conDireccionNormalizada = ClienteDireccion::query()
            ->where('esta_activa', true)
            ->distinct('cliente_id')
            ->count('cliente_id');

        $pendientesVerificacion = ClienteDireccion::query()
            ->where('esta_activa', true)
            ->where('estado_verificacion', ClienteDireccion::ESTADO_PENDING)
            ->count();

        $this->table(['Métrica', 'Valor'], [
            ['Clientes totales', $totalClientes],
            ['Con datos de contacto legado', $conContacto],
            ['Con dirección normalizada activa', $conDireccionNormalizada],
            ['Direcciones pendientes de verificación', $pendientesVerificacion],
            ['Sin normalizar (estimado)', max(0, $conContacto - $conDireccionNormalizada)],
        ]);

        return self::SUCCESS;
    }
}
