<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SincronizarCajasPedidoBmaService
{
    public function __construct(
        private EnviosPedidoBmaConfig $config,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{cajas: list<PedidoBmaCaja>, diffs: list<string>}
     */
    public function ejecutar(
        PedidoBma $pedido,
        array $lineas,
        int $usuarioId,
        ?string $motivoRetiro = null,
    ): array {
        $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

        $existentes = PedidoBmaCaja::query()
            ->where('pedido_bma_id', $pedido->id)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (PedidoBmaCaja $c) => (string) $c->uuid_operativo);

        $uuidsVistos = [];
        $orden = 0;
        $diffs = [];
        /** @var list<PedidoBmaCaja> $resultado */
        $resultado = [];

        foreach ($lineas as $linea) {
            $uuid = trim((string) ($linea['uuid_operativo'] ?? $linea['client_uuid'] ?? ''));
            if ($uuid === '') {
                $uuid = (string) Str::uuid();
            }
            if (isset($uuidsVistos[$uuid])) {
                throw new InvalidArgumentException('Hay envíos duplicados. Recargue e intente de nuevo.');
            }
            $uuidsVistos[$uuid] = true;

            $caja = $existentes->get($uuid);
            if ($caja && $caja->estaRetirada()) {
                $caja->fill([
                    'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
                    'retirada_at' => null,
                    'retirada_por_id' => null,
                    'motivo_retiro' => null,
                ]);
                $diffs[] = "Reactivó envío {$uuid}.";
            }

            if ($caja && $caja->estaRecolectada() && ! $this->config->editarTrasRecoleccion()) {
                $caja->orden = $orden;
                $caja->save();
                $resultado[] = $caja;
                $orden++;
                continue;
            }

            $attrs = [
                'pedido_bma_id' => $pedido->id,
                'uuid_operativo' => $uuid,
                'catalogo_tipo_caja_id' => $linea['catalogo_tipo_caja_id'],
                'cantidad' => 1,
                'orden' => $orden,
                'largo' => $linea['largo'],
                'ancho' => $linea['ancho'],
                'alto' => $linea['alto'],
                'peso_real_kg' => $linea['peso_real_kg'],
                'peso_volumetrico_kg' => $linea['peso_volumetrico_kg'],
                'peso_cobrado_kg' => $linea['peso_cobrado_kg'] ?? PedidoBma::calcularPesoCobradoGuia(
                    (float) $linea['peso_real_kg'],
                    (float) $linea['peso_volumetrico_kg']
                ),
                'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
                'moneda' => $linea['moneda'] ?? $this->config->moneda(),
            ];

            if ($caja) {
                $diff = $this->diffPesos($caja, $attrs);
                $caja->fill($attrs);
                $caja->save();
                if ($diff !== []) {
                    $diffs[] = 'Envío '.($orden + 1).': '.implode(', ', $diff);
                }
                $resultado[] = $caja;
            } else {
                $resultado[] = PedidoBmaCaja::query()->create($attrs);
                $diffs[] = 'Alta envío '.($orden + 1).' ('.$uuid.').';
            }

            $orden++;
        }

        $omitidas = $existentes->filter(
            fn (PedidoBmaCaja $c) => $c->estaActiva() && ! isset($uuidsVistos[(string) $c->uuid_operativo])
        );

        if ($omitidas->isNotEmpty()) {
            $motivo = trim((string) $motivoRetiro);
            if ($motivo === '') {
                throw new InvalidArgumentException(
                    'Indique el motivo para retirar envíos que ya estaban guardados.'
                );
            }
            foreach ($omitidas as $caja) {
                if ($caja->estaRecolectada() && ! $this->config->editarTrasRecoleccion()) {
                    throw new RuntimeException(
                        'No se puede retirar un envío ya recolectado sin reapertura autorizada.'
                    );
                }
                $caja->update([
                    'estado_operativo' => PedidoBmaCaja::ESTADO_RETIRADA,
                    'retirada_at' => now(),
                    'retirada_por_id' => $usuarioId,
                    'motivo_retiro' => $motivo,
                ]);
                $diffs[] = 'Retiró envío '.$caja->uuid_operativo.': '.$motivo;
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    'Envío retirado (el comprobante se conserva). Motivo: '.$motivo,
                    AccionesHistorialPedidoBma::RETIRO_CAJA
                );
            }
        }

        return ['cajas' => $resultado, 'diffs' => $diffs];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private function diffPesos(PedidoBmaCaja $caja, array $attrs): array
    {
        $out = [];
        foreach (['peso_real_kg', 'peso_volumetrico_kg', 'peso_cobrado_kg', 'largo', 'ancho', 'alto'] as $campo) {
            $antes = (float) ($caja->{$campo} ?? 0);
            $despues = (float) ($attrs[$campo] ?? 0);
            if (abs($antes - $despues) > 0.0001) {
                $out[] = "{$campo} {$antes} → {$despues}";
            }
        }

        return $out;
    }
}
