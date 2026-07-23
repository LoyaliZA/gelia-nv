<?php

namespace App\Services\Traspasos;

use App\Models\User;
use App\Notifications\AlertaTraspaso;
use App\Models\SolicitudTraspaso;
use Illuminate\Support\Facades\Notification;

class NotificarTraspasoService
{
    public function nueva(SolicitudTraspaso $solicitud, User $vendedor): void
    {
        $departamentoId = $solicitud->departamento_id;

        $encargadosPorDepto = $departamentoId
            ? User::permission(['traspasos.responder', 'traspasos.verificar', 'traspasos.monitorear_alertas'])
                ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $departamentoId))
                ->get()
            : collect();

        $monitores = User::permission('traspasos.monitorear_alertas')->get();
        $adminsGlobales = User::role(['Super Admin', 'Administrador'])->get();

        $destinatarios = $encargadosPorDepto
            ->merge($monitores)
            ->merge($adminsGlobales)
            ->unique('id')
            ->reject(fn ($u) => $u->id === $vendedor->id);

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new AlertaTraspaso(
                $solicitud,
                'nueva',
                "Nueva solicitud de traspaso de: {$vendedor->name}"
            ));
        }
    }

    public function respuesta(SolicitudTraspaso $solicitud, string $tipo, string $mensaje, ?int $excluirUserId = null): void
    {
        $destinatarios = collect();

        if ($solicitud->vendedor && $solicitud->vendedor->id !== $excluirUserId) {
            $destinatarios->push($solicitud->vendedor);
        }

        $monitores = User::permission('traspasos.monitorear_alertas')->get();
        $destinatarios = $destinatarios
            ->merge($monitores)
            ->unique('id')
            ->reject(fn ($u) => $excluirUserId !== null && $u->id === $excluirUserId);

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new AlertaTraspaso($solicitud, $tipo, $mensaje));
        }
    }

    /** Solo tras respuesta OK (Respondida). No llamar en Incorrecta ni en creación. */
    public function listoParaCedis(SolicitudTraspaso $solicitud, ?int $excluirUserId = null): void
    {
        $cedis = User::permission('traspasos.cedis')->get();
        $destinatarios = $cedis
            ->unique('id')
            ->reject(fn ($u) => $excluirUserId !== null && $u->id === $excluirUserId);

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new AlertaTraspaso(
                $solicitud->loadMissing(['vendedor', 'estado', 'cliente']),
                'listo_cedis',
                'Traspaso listo para revisión en CEDIS. Revisa folio y evidencia.'
            ));
        }
    }

    public function detalleDanoCedis(SolicitudTraspaso $solicitud, ?int $excluirUserId = null): void
    {
        $destinatarios = collect();

        if ($solicitud->vendedor) {
            $destinatarios->push($solicitud->vendedor);
        }

        $departamentoId = $solicitud->departamento_id;
        if ($departamentoId) {
            $encargados = User::permission('traspasos.responder')
                ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $departamentoId))
                ->get();
            $destinatarios = $destinatarios->merge($encargados);
        }

        $monitores = User::permission('traspasos.monitorear_alertas')->get();
        $destinatarios = $destinatarios
            ->merge($monitores)
            ->unique('id')
            ->reject(fn ($u) => $excluirUserId !== null && $u->id === $excluirUserId);

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new AlertaTraspaso(
                $solicitud->loadMissing(['vendedor', 'estado', 'cliente']),
                'detalle_dano_cedis',
                'CEDIS reportó un detalle o daño en piezas del traspaso.'
            ));
        }
    }
}
