<?php

namespace App\Services\Facturas;

use App\Models\SolicitudFactura;
use App\Models\User;
use App\Notifications\AlertaFactura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class NotificarEncargadosFacturaService
{
    public function nueva(SolicitudFactura $solicitud): void
    {
        $this->enviar(
            $solicitud,
            'nueva',
            'Nueva solicitud de factura de: '.($solicitud->vendedor->name ?? 'un colaborador')
        );
    }

    public function formularioCorregido(SolicitudFactura $solicitud): void
    {
        $this->enviar(
            $solicitud,
            'formulario_corregido',
            'El cliente corrigió datos fiscales de: '.($solicitud->vendedor->name ?? 'un colaborador')
        );
    }

    private function enviar(SolicitudFactura $solicitud, string $tipo, string $mensaje): void
    {
        $encargados = $this->destinatarios($solicitud);
        if ($encargados->isEmpty()) {
            return;
        }

        Notification::send($encargados, new AlertaFactura($solicitud, $tipo, $mensaje));
    }

    private function destinatarios(SolicitudFactura $solicitud): Collection
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

        return $encargadosPorDepto->merge($adminsGlobales)
            ->unique('id')
            ->reject(fn ($u) => (int) $u->id === $vendedorId)
            ->values();
    }
}
