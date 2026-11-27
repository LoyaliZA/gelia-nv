<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Registra la decisión de la vendedora (activar Multienvío o seguir con envío estándar sin seguro).
 * No crea pedidos: solo persiste el grupo y su estado.
 */
class RegistrarDecisionMultienvio
{
    public function __construct(private readonly EvaluarMultienvioNacional $evaluador) {}

    /** @param  'activar'|'sin_seguro'  $decision */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $decision, ?string $revisionEsperada = null): MultienvioNacional
    {
        $destino = match ($decision) {
            'activar' => MultienvioNacional::ESTADO_CONFIGURANDO,
            'sin_seguro' => MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO,
            default => throw new InvalidArgumentException('Decisión de Multienvío no válida: '.$decision),
        };

        $usuario = User::query()->findOrFail($usuarioId);
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw new AuthorizationException('No tienes autorización para decidir sobre este pedido.');
        }

        return DB::transaction(function () use ($pedido, $usuario, $usuarioId, $destino, $revisionEsperada) {
            $bloqueado = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $bloqueado)) {
                throw new AuthorizationException('No tienes autorización para decidir sobre este pedido.');
            }

            $grupo = MultienvioNacional::query()->where('pedido_origen_id', $bloqueado->id)->lockForUpdate()->first();

            // Estados terminales: se devuelve el grupo sin cambios (idempotencia).
            if ($grupo !== null && in_array($grupo->estado, [
                MultienvioNacional::ESTADO_ACTIVO,
                MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE,
            ], true)) {
                return $grupo;
            }

            if ($grupo !== null && $grupo->estado === $destino) {
                // En configuración, volver a activar tras un cambio de cajas refresca la revisión (si no, Confirmar
                // quedaría bloqueado por revisión obsoleta sin salida).
                return $destino === MultienvioNacional::ESTADO_CONFIGURANDO
                    ? $this->refrescarConfiguracion($grupo, $bloqueado, $usuarioId, $revisionEsperada)
                    : $grupo;
            }

            $evaluacion = $this->evaluador->ejecutar($bloqueado);

            if ($revisionEsperada !== null && $revisionEsperada !== $evaluacion['revision']) {
                throw MultienvioNacionalException::revisionObsoleta();
            }
            if (! $evaluacion['elegible']) {
                throw MultienvioNacionalException::noElegible((string) $evaluacion['motivo']);
            }

            if ($grupo === null) {
                return MultienvioNacional::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'pedido_origen_id' => $bloqueado->id,
                    'revision_consulta' => $evaluacion['revision'],
                    'estado' => $destino,
                    'decidido_por_id' => $usuarioId,
                    'decidido_at' => now(),
                    'snapshot_origen_json' => $this->snapshot($bloqueado),
                ]);
            }

            if ($this->puedeCambiar($grupo->estado, $destino)) {
                $grupo->update([
                    'estado' => $destino,
                    'revision_consulta' => $evaluacion['revision'],
                    'decidido_por_id' => $usuarioId,
                    'decidido_at' => now(),
                    'snapshot_origen_json' => $grupo->snapshot_origen_json ?: $this->snapshot($bloqueado),
                ]);
            }

            return $grupo->refresh();
        });
    }

    private function refrescarConfiguracion(MultienvioNacional $grupo, PedidoBma $origen, int $usuarioId, ?string $revisionEsperada): MultienvioNacional
    {
        $evaluacion = $this->evaluador->ejecutar($origen);
        if ($evaluacion['revision'] === $grupo->revision_consulta) {
            return $grupo;
        }

        if ($revisionEsperada !== null && $revisionEsperada !== $evaluacion['revision']) {
            throw MultienvioNacionalException::revisionObsoleta();
        }
        if (! $evaluacion['elegible']) {
            throw MultienvioNacionalException::noElegible((string) $evaluacion['motivo']);
        }

        // El borrador conserva solo las cajas que siguen activas; el resto de entradas no se toca.
        $activas = $origen->cajas()->activas()->pluck('uuid_operativo')->all();
        $borrador = array_values(array_filter(
            $grupo->borrador_json ?? [],
            fn ($envio) => in_array((string) ($envio['uuid_operativo'] ?? ''), $activas, true)
        ));

        $grupo->update([
            'revision_consulta' => $evaluacion['revision'],
            'decidido_por_id' => $usuarioId,
            'decidido_at' => now(),
            'borrador_json' => $borrador,
        ]);

        return $grupo->refresh();
    }

    private function puedeCambiar(string $actual, string $destino): bool
    {
        return match ($actual) {
            MultienvioNacional::ESTADO_PENDIENTE_DECISION => true,
            MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO => $destino === MultienvioNacional::ESTADO_CONFIGURANDO,
            MultienvioNacional::ESTADO_CONFIGURANDO => $destino === MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO,
            default => false,
        };
    }

    /** @return array<string, mixed> */
    private function snapshot(PedidoBma $pedido): array
    {
        return [
            'folio' => $pedido->folio,
            'folio_remision' => $pedido->folio_remision,
            'total_mercancia' => $pedido->total_mercancia,
            'documentos_ids' => $pedido->documentos()->vigente()->pluck('id')->all(),
        ];
    }
}
