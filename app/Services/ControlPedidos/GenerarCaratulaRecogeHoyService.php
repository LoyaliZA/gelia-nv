<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerarCaratulaRecogeHoyService
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, bool $regenerar = false): PedidoBmaTareaDocumento
    {
        $tarea->loadMissing(['modalidad', 'productos', 'pedido.cliente', 'pedido.estatus']);
        if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
            throw ValidationException::withMessages([
                'modalidad' => 'La carátula de recogida solo aplica a Recoge en tienda.',
            ]);
        }

        $existente = $tarea->documentos()
            ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE)
            ->latest('id')
            ->first();
        if ($existente && ! $regenerar) {
            return $existente;
        }

        $folio = $tarea->pedido
            ? PedidoBmaReferencia::visible($tarea->pedido)
            : ['folio' => (string) $tarea->pedido_bma_id, 'etiqueta' => 'Folio'];
        $cliente = $tarea->pedido?->cliente?->nombre_comercial
            ?: $tarea->pedido?->cliente?->nombre
            ?: $tarea->pedido?->contacto_nombre_snapshot
            ?: '—';
        $origen = match ($tarea->pedido?->origen_solicitud) {
            'BELLAROMA' => 'Bellaroma',
            'AROMAS' => 'Aromas',
            'CALL_CENTER' => 'Call Center',
            default => '—',
        };

        $snapshot = [
            'folio' => $folio['folio'] !== '' ? $folio['folio'] : (string) $tarea->pedido_bma_id,
            'folio_etiqueta' => $folio['etiqueta'],
            'cliente' => $cliente,
            'origen' => $origen,
            'fecha' => now()->timezone('America/Mexico_City')->format('d/m/Y H:i'),
            'piezas' => $tarea->productos->map(fn ($p) => [
                'sku' => $p->sku,
                'descripcion' => $p->descripcion_snapshot,
                'cantidad' => $p->cantidad_encontrada ?? $p->cantidad_solicitada,
                'estado' => PedidoBmaRevisionProducto::LABELS[$p->estado_fisico] ?? ($p->estado_fisico ?: '—'),
            ])->all(),
        ];

        $pdf = Pdf::loadView('control_pedidos.caratula_recoge_tienda', ['caratula' => $snapshot])
            ->setPaper('letter', 'portrait');
        $contenido = $pdf->output();
        $nombre = 'caratula-recoge-'.Str::lower(Str::random(8)).'.pdf';
        $ruta = "pedidos_bma/caratulas_recoge/{$tarea->id}/{$nombre}";
        Storage::disk('local')->put($ruta, $contenido);

        return PedidoBmaTareaDocumento::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE,
            'ruta_interna' => $ruta,
            'nombre_original' => 'Carátula recoge en tienda.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => strlen($contenido),
            'hash_sha256' => hash('sha256', $contenido),
            'subido_por_id' => $usuario->id,
            'subido_at' => now(),
            'inmutable' => true,
        ]);
    }
}
