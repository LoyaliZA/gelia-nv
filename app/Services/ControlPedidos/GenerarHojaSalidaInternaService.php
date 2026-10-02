<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerarHojaSalidaInternaService
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, PedidoBmaCaratula $caratula): PedidoBmaTareaDocumento
    {
        return DB::transaction(function () use ($tarea, $usuario, $caratula) {
            $tarea->loadMissing(['paqueteria', 'pedido']);
            $existente = $tarea->documentos()
                ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_HOJA_SALIDA_INTERNA)
                ->where('inmutable', true)
                ->first();
            if ($existente) {
                return $existente;
            }

            $folioVisible = $tarea->pedido
                ? PedidoBmaReferencia::visible($tarea->pedido)
                : ['folio' => (string) $tarea->pedido_bma_id, 'etiqueta' => 'Folio'];

            $snapshot = [
                'destinatario_nombre' => (string) $tarea->destinatario_nombre,
                'destinatario_telefono' => (string) $tarea->destinatario_telefono,
                'municipio_destino' => (string) $tarea->municipio_destino,
                'direccion_referencia' => $tarea->direccion_referencia,
                'transporte' => $tarea->paqueteria?->nombre ?? '—',
                'modalidad_cobro' => (string) $tarea->modalidad_cobro,
                'folio' => $folioVisible['folio'] !== '' ? $folioVisible['folio'] : (string) $tarea->pedido_bma_id,
                'folio_etiqueta' => $folioVisible['etiqueta'],
                'folio_interno' => $tarea->pedido?->folio,
                'caratula_version' => $caratula->version,
                'fecha' => now()->format('d/m/Y H:i'),
            ];

            $pdf = Pdf::loadView('control_pedidos.hoja_salida_interna', ['hoja' => $snapshot])
                ->setPaper('letter', 'portrait');
            $contenido = $pdf->output();
            $nombre = 'hoja_salida_interna_'.Str::random(20).'.pdf';
            $ruta = "pedidos_bma/tareas_preparacion/{$tarea->id}/{$nombre}";
            Storage::disk('local')->put($ruta, $contenido);

            return $tarea->documentos()->create([
                'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_HOJA_SALIDA_INTERNA,
                'ruta_interna' => $ruta,
                'nombre_original' => 'Hoja interna de salida (no es remisión).pdf',
                'mime_type' => 'application/pdf',
                'tamano_bytes' => strlen($contenido),
                'hash_sha256' => hash('sha256', $contenido),
                'subido_por_id' => $usuario->id,
                'subido_at' => now(),
                'inmutable' => true,
            ]);
        });
    }
}
