<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use App\Support\PuntoVenta\Resguardos\RutaAlmacenamientoResguardoPdv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Bandeja de resguardo de la sucursal. La marca inicial no es recepción ni entrega. */
class EventosCustodiaSucursalNacional
{
    public function abrir(PedidoBma $pedido, int $sucursalId, int $usuarioId): ResguardoPdv
    {
        return DB::transaction(function () use ($pedido, $sucursalId, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertNacional($pedido);
            $existente = ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $sucursalId)
                ->first();
            if ($existente) {
                return $existente;
            }

            $resguardo = ResguardoPdv::query()->create([
                'pedido_bma_id' => $pedido->id,
                'cliente_id' => $pedido->cliente_id,
                'sucursal_id' => $sucursalId,
                'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                'cantidad_bultos_esperada' => 1,
                'entrega_bloqueada' => false,
                'snapshot_folio' => $pedido->folio,
                'snapshot_json' => ['folio' => $pedido->folio, 'origen' => 'nacional'],
                'version' => 1,
            ]);
            $this->evento($resguardo, ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA, null, ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $usuarioId);

            return $resguardo;
        });
    }

    /** @param  array<string, mixed>  $contexto */
    public function recibir(PedidoBma $pedido, int $usuarioId, array $contexto = []): ResguardoPdv
    {
        return $this->avanzar(
            $pedido,
            $usuarioId,
            $contexto,
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_RECIBIDO,
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'recibido',
            EstatusSucursalPedidoBma::RECIBIDO,
            'La recepción de sucursal espera el aviso en la bandeja.',
            ['recepcion_fisica_at' => now()],
        );
    }

    /** @param  array<string, mixed>  $contexto */
    public function custodiar(PedidoBma $pedido, int $usuarioId, int $almacenId, string $ubicacion, array $contexto = []): ResguardoPdv
    {
        $resguardo = $this->resguardo($pedido);
        if ($resguardo->estado === ResguardoPdv::ESTADO_EN_CUSTODIA) {
            return $resguardo;
        }
        $almacen = Almacen::query()->find($almacenId);
        if (! $almacen || ! AlmacenPdv::esAlmacen($almacen) || (int) $almacen->sucursal_id !== (int) $resguardo->sucursal_id) {
            throw new RuntimeException('La custodia se registra en el almacén de la sucursal.');
        }
        if (trim($ubicacion) === '') {
            throw new RuntimeException('Indique la ubicación de la custodia.');
        }

        return $this->avanzar(
            $pedido,
            $usuarioId,
            $contexto,
            ResguardoPdv::ESTADO_RECIBIDO,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA,
            'en_custodia',
            EstatusSucursalPedidoBma::EN_CUSTODIA,
            'La custodia espera la recepción en sucursal.',
            [
                'almacen_id' => $almacen->id,
                'custodia_confirmada_at' => now(),
                'snapshot_json' => array_merge($resguardo->snapshot_json ?? [], ['ubicacion' => trim($ubicacion)]),
            ],
        );
    }

    /** @param  array<string, mixed>  $contexto */
    public function entregar(PedidoBma $pedido, int $usuarioId, array $contexto = []): ResguardoPdv
    {
        $archivo = $contexto['evidencia'] ?? null;
        if (! $archivo instanceof UploadedFile) {
            throw new RuntimeException('Adjunte la fotografía de la entrega.');
        }

        $resguardo = $this->avanzar(
            $pedido,
            $usuarioId,
            $contexto,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            ResguardoPdv::ESTADO_ENTREGADO,
            ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
            'entregado',
            null,
            'La entrega espera la custodia en sucursal.',
            ['entrega_completada_at' => now()],
        );
        $evento = $resguardo->eventos()->where('tipo_evento', ResguardoPdvEvento::TIPO_ENTREGA_TITULAR)->latest('id')->first();
        $ruta = $archivo->store(RutaAlmacenamientoResguardoPdv::prefijo($resguardo, 'entrega'), 'local');
        $resguardo->evidencias()->create([
            'evento_id' => $evento?->id,
            'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath() ?: $archivo->getPathname()),
            'actor_id' => $usuarioId,
            'capturado_at' => now(),
            'inmutable' => true,
            'metadata_json' => ['origen' => 'nacional', 'uso' => 'entrega'],
        ]);

        return $resguardo->fresh();
    }

    private function assertNacional(PedidoBma $pedido): void
    {
        if (! CapturaNacional::es($pedido)) {
            throw new RuntimeException('Este pedido no usa la captura nacional.');
        }
    }

    private function resguardo(PedidoBma $pedido): ResguardoPdv
    {
        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->first();
        if (! $resguardo) {
            throw new RuntimeException('La sucursal no tiene este pedido en su bandeja.');
        }

        return $resguardo;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<string, mixed>  $extra
     */
    private function avanzar(
        PedidoBma $pedido,
        int $usuarioId,
        array $contexto,
        string $desde,
        string $hacia,
        string $tipo,
        string $marca,
        ?string $estatus,
        string $bloqueo,
        array $extra,
    ): ResguardoPdv {
        return DB::transaction(function () use ($pedido, $usuarioId, $contexto, $desde, $hacia, $tipo, $marca, $estatus, $bloqueo, $extra) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertNacional($pedido);
            $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->lockForUpdate()->first();
            if (! $resguardo) {
                throw new RuntimeException('La sucursal no tiene este pedido en su bandeja.');
            }
            if ($resguardo->estado === $hacia) {
                return $resguardo;
            }
            if (isset($contexto['revision_esperada'], $contexto['base_captura'])) {
                RevisionCapturaPedido::comprobar($pedido, $contexto);
            }
            if ($resguardo->estado !== $desde) {
                throw new RuntimeException($bloqueo);
            }

            $resguardo->update(array_merge(['estado' => $hacia], $extra));
            $this->evento($resguardo, $tipo, $desde, $hacia, $usuarioId, isset($contexto['idempotency_key']) ? (string) $contexto['idempotency_key'] : null);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['modalidad_entrega'] = 'nacional';
            $json['cambio_a_tienda']['estado'] = $marca;
            unset($json['conversion_modalidad']);
            $pedido->update([
                'estatus_sucursal' => $estatus,
                'solicitud_preparacion_json' => $json,
            ]);

            return $resguardo->fresh();
        });
    }

    private function evento(ResguardoPdv $resguardo, string $tipo, ?string $antes, string $despues, int $usuarioId, ?string $clave = null): void
    {
        $resguardo->eventos()->create([
            'tipo_evento' => $tipo,
            'estado_anterior' => $antes,
            'estado_nuevo' => $despues,
            'actor_id' => $usuarioId,
            'ocurrido_at' => now(),
            'idempotency_key' => ($clave !== null && $clave !== '') ? $clave : (string) Str::uuid(),
        ]);
    }
}
