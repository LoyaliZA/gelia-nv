<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResguardosEnvioPedido
{
    /** Fases en las que el apartado ya salió o va en un envío propio. */
    private const FASES_NO_ANEXABLES = [
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_EN_RUTA,
        CatalogoEstatusPedido::FASE_ENVIADO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_CANCELADO,
    ];

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function listar(int $clienteId, ?int $envioId = null, string $q = ''): Collection
    {
        if ($clienteId < 1) {
            return collect();
        }

        return PedidoBma::query()
            ->with('estatus:id,fase_ciclo,nombre_visual')
            ->where('cliente_id', $clienteId)
            ->where('es_resguardo', true)
            ->whereNull('pedido_principal_id')
            ->whereNull('cancelado_at')
            ->where(function ($query) {
                $query->where(function ($conNumero) {
                    $conNumero->whereNotNull('numero_remision')->where('numero_remision', '!=', '');
                })->orWhereHas('remision');
            })
            ->whereDoesntHave('estatus', fn ($q) => $q->whereIn('fase_ciclo', self::FASES_NO_ANEXABLES))
            ->where(function ($query) use ($envioId) {
                $query->whereDoesntHave('envioQueLoAnexa');
                if ($envioId) {
                    $query->orWhereHas('envioQueLoAnexa', fn ($q) => $q->where('pedido_envio_id', $envioId));
                }
            })
            ->when($envioId, fn ($q) => $q->where('id', '!=', $envioId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('folio', 'like', "%{$q}%")
                        ->orWhere('folio_remision', 'like', "%{$q}%")
                        ->orWhere('numero_remision', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'folio', 'folio_remision', 'numero_remision', 'cliente_id', 'total_mercancia']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function aplicar(PedidoBma $envio, array $datos, int $usuarioId): PedidoBma
    {
        $toca = array_key_exists('resguardo_ids', $datos)
            || array_key_exists('notas_resguardos_externos', $datos)
            || array_key_exists('envio_solo_resguardos', $datos);

        if (! $toca || $envio->es_resguardo) {
            if ($envio->es_resguardo && ($envio->envio_solo_resguardos || $envio->resguardosAnexados()->exists())) {
                $envio->resguardosAnexados()->delete();
                $envio->forceFill([
                    'envio_solo_resguardos' => false,
                    'notas_resguardos_externos' => null,
                ])->save();
            }

            return $envio;
        }

        return DB::transaction(function () use ($envio, $datos, $usuarioId) {
            $envio = PedidoBma::query()->lockForUpdate()->findOrFail($envio->id);
            $ids = array_key_exists('resguardo_ids', $datos)
                ? $this->idsDe($datos['resguardo_ids'] ?? [])
                : $envio->resguardosAnexados()->pluck('pedido_resguardo_id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_filter($ids, fn (int $id) => $id !== (int) $envio->id));

            $actuales = $envio->resguardosAnexados()->pluck('pedido_resguardo_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $nuevos = collect($ids)->sort()->values()->all();
            $cambio = $actuales !== $nuevos;

            if ($cambio) {
                foreach ($nuevos as $id) {
                    if (! in_array($id, $actuales, true)) {
                        $this->assertAnexable(PedidoBma::query()->find($id), $envio);
                    }
                }

                $envio->resguardosAnexados()->whereNotIn('pedido_resguardo_id', $nuevos ?: [0])->delete();
                $conservados = $envio->resguardosAnexados()->pluck('pedido_resguardo_id')->map(fn ($id) => (int) $id)->all();
                foreach (array_diff($nuevos, $conservados) as $id) {
                    PedidoBmaEnvioResguardo::query()->create([
                        'pedido_envio_id' => $envio->id,
                        'pedido_resguardo_id' => $id,
                    ]);
                    $resguardo = PedidoBma::query()->find($id);
                    $estatusId = (int) ($resguardo?->catalogo_estatus_pedido_id ?? 0);
                    if ($estatusId > 0) {
                        $this->historial->ejecutar(
                            $id,
                            $usuarioId,
                            $estatusId,
                            $estatusId,
                            "Anexado al envío {$envio->folio}.",
                            AccionesHistorialPedidoBma::ANEXO_RESGUARDOS
                        );
                    }
                }

            }

            $notasAntes = trim((string) ($envio->notas_resguardos_externos ?? ''));
            $notas = array_key_exists('notas_resguardos_externos', $datos)
                ? trim((string) ($datos['notas_resguardos_externos'] ?? ''))
                : $notasAntes;
            $solo = array_key_exists('envio_solo_resguardos', $datos)
                ? filter_var($datos['envio_solo_resguardos'], FILTER_VALIDATE_BOOLEAN)
                : (bool) $envio->envio_solo_resguardos;
            if (($cambio || $notasAntes !== $notas) && $envio->tienePesajeRespondido() && $envio->empacado_at === null) {
                $this->reabrirPesaje($envio, $usuarioId);
            }

            $envio->forceFill([
                'envio_solo_resguardos' => $solo,
                'notas_resguardos_externos' => $notas !== '' ? $notas : null,
            ])->save();

            return $envio;
        });
    }

    public function liberarVinculosDe(PedidoBma $pedido): void
    {
        $pedido->resguardosAnexados()->delete();
        PedidoBmaEnvioResguardo::query()->where('pedido_resguardo_id', $pedido->id)->delete();
    }

    public function assertNoSalePorSuCuenta(PedidoBma $resguardo): void
    {
        if ($resguardo->envioQueLoAnexa()->exists()) {
            throw new \RuntimeException('Este resguardo está anexado a un envío. No se libera ni recibe guía propia.');
        }
    }

    private function assertAnexable(?PedidoBma $resguardo, PedidoBma $envio): void
    {
        if (! $resguardo) {
            throw new \InvalidArgumentException('Uno de los resguardos no existe.');
        }
        if (! $envio->cliente_id || (int) $resguardo->cliente_id !== (int) $envio->cliente_id) {
            throw new \InvalidArgumentException('El resguardo debe ser del mismo cliente que el envío.');
        }
        if (! $resguardo->es_resguardo || $resguardo->pedido_principal_id !== null) {
            throw new \InvalidArgumentException('Solo se anexa un resguardo abierto que no sea complemento.');
        }
        if ($resguardo->cancelado_at) {
            throw new \InvalidArgumentException('No se puede anexar un resguardo cancelado.');
        }

        $resguardo->loadMissing('estatus');
        if (in_array($resguardo->estatus?->fase_ciclo, self::FASES_NO_ANEXABLES, true)) {
            throw new \InvalidArgumentException('Ese resguardo ya tiene un envío propio.');
        }

        $tieneRemision = trim((string) $resguardo->numero_remision) !== '' || $resguardo->remision()->exists();
        if (! $tieneRemision) {
            throw new \InvalidArgumentException('El resguardo todavía no tiene remisión. Anote la remisión externa si no está en GELIA.');
        }

        $otro = $resguardo->envioQueLoAnexa()->first();
        if ($otro && (int) $otro->pedido_envio_id !== (int) $envio->id) {
            throw new \InvalidArgumentException('Ese resguardo ya está anexado a otro envío.');
        }
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function idsDe(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }

    private function reabrirPesaje(PedidoBma $envio, int $usuarioId): void
    {
        $envio->loadMissing('estatus');
        $fase = $envio->estatus?->fase_ciclo;
        $attrs = [
            'consulta_actualizacion_pendiente' => true,
            'consulta_cerrada_at' => null,
            'consulta_cerrada_por_id' => null,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
        ];
        $estatusNuevoId = $envio->catalogo_estatus_pedido_id;
        if (MaquinaEstadosPedidoBma::puedeTransicionar($fase, CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE)) {
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
            if ($estatusNuevo) {
                $attrs['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
                $estatusNuevoId = $estatusNuevo->id;
            }
        }

        $anterior = $envio->catalogo_estatus_pedido_id;
        $envio->update($attrs);
        $this->historial->ejecutar(
            $envio->id,
            $usuarioId,
            $anterior,
            $estatusNuevoId,
            'Cambió el conjunto de resguardos. CEDIS debe pesar de nuevo.',
            AccionesHistorialPedidoBma::ANEXO_RESGUARDOS
        );
    }
}
