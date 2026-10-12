<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\Storage;

class DocumentoPreparacionController extends Controller
{
    public function __invoke(PedidoBma $pedidoBma, PedidoBmaTareaPreparacion $tarea, PedidoBmaTareaDocumento $documento)
    {
        abort_unless(VisibilidadPedidoBma::puedeConsultar(request()->user(), $pedidoBma), 403);
        abort_unless((int) $tarea->pedido_bma_id === (int) $pedidoBma->id && (int) $documento->pedido_bma_tarea_preparacion_id === (int) $tarea->id, 404);
        abort_unless(in_array($documento->tipo_evidencia, [PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_INCIDENCIA, PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE], true), 404);
        $disk = Storage::disk('local')->exists($documento->ruta_interna) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($documento->ruta_interna), 404);

        return Storage::disk($disk)->response($documento->ruta_interna, $documento->nombre_original, ['Content-Type' => $documento->mime_type ?: 'application/octet-stream']);
    }
}
