<?php

namespace App\Services\Facturas;

use App\Models\SolicitudFactura;
use App\Models\User;
use App\Notifications\AlertaFactura;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class NotificarEncargadosFacturaService
{
    public function nueva(SolicitudFactura $solicitud): void
    {
        $solicitud->loadMissing('vendedor');
        $vendedorId = (int) $solicitud->vendedor_id;
        $departamentoId = $solicitud->departamento_id;

        $encargadosPorDepto = $departamentoId
            ? User::permission(['facturas.responder', 'facturas.verificar'])
                ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $departamentoId))
                ->get()
            : collect();

        $rolesAdmin = collect(['Super Admin', 'Administrador'])
            ->filter(fn (string $nombre) => Role::query()
                ->where('name', $nombre)
                ->where('guard_name', 'web')
                ->exists())
            ->values()
            ->all();

        $adminsGlobales = $rolesAdmin !== []
            ? User::role($rolesAdmin)->get()
            : collect();

        $encargados = $encargadosPorDepto->merge($adminsGlobales)
            ->unique('id')
            ->reject(fn ($u) => (int) $u->id === $vendedorId);

        if ($encargados->isEmpty()) {
            return;
        }

        $nombre = $solicitud->vendedor->name ?? 'un colaborador';

        Notification::send($encargados, new AlertaFactura(
            $solicitud,
            'nueva',
            "Nueva solicitud de factura de: {$nombre}"
        ));
    }
}
