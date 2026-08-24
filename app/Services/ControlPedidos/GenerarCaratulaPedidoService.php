<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerarCaratulaPedidoService
{
    public function __construct(
        private CalcularRequisitosPreparacionService $requisitosService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?int $versionEsperada = null,
        ?string $motivoRegeneracion = null,
    ): PedidoBmaCaratula {
        if (! $usuario->can('control_pedidos.tienda.generar_caratula')
            && ! ($motivoRegeneracion && $usuario->can('control_pedidos.tienda.regenerar_caratula'))) {
            throw new \RuntimeException('No tiene permiso para generar carátula.');
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada, $motivoRegeneracion) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $tarea->loadMissing(['modalidad', 'paqueteria', 'pedido.estatus', 'documentos', 'pedido.cliente']);

            if ($versionEsperada !== null && (int) $tarea->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó esta tarea. Actualice e intente de nuevo.',
                ]);
            }

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA) {
                throw ValidationException::withMessages([
                    'estado' => 'La carátula solo se genera cuando la tarea está lista para carátula.',
                ]);
            }

            if (! $tarea->modalidad?->esEnvioMunicipio()) {
                throw ValidationException::withMessages([
                    'modalidad' => 'Esta tarea no es de envío a municipio.',
                ]);
            }

            $req = $this->requisitosService->efectivos($tarea);
            $faltantes = $this->requisitosService->validarDocumentosMunicipio($tarea, $req);
            if ($faltantes !== []) {
                throw ValidationException::withMessages(['requisitos' => $faltantes]);
            }

            $vigente = $tarea->caratulas()
                ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
                ->orderByDesc('version')
                ->first();

            if ($vigente?->estado === PedidoBmaCaratula::ESTADO_COLOCADA) {
                throw ValidationException::withMessages([
                    'caratula' => 'No se puede sobrescribir una carátula ya colocada. Use regenerar con motivo.',
                ]);
            }

            if ($vigente && ! $motivoRegeneracion) {
                throw ValidationException::withMessages([
                    'motivo_regeneracion' => 'Para regenerar indique el motivo.',
                ]);
            }

            if ($vigente) {
                $vigente->update(['estado' => PedidoBmaCaratula::ESTADO_INVALIDADA]);
            }

            $siguienteVersion = (int) ($tarea->caratulas()->max('version') ?? 0) + 1;
            $paqueteria = $tarea->paqueteria;
            $plantilla = $paqueteria?->plantilla_caratula ?: 'control_pedidos.caratula';

            $identificacion = $tarea->documentos
                ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_IDENTIFICACION)
                ->sortByDesc('id')
                ->first();
            $remision = $tarea->documentos
                ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_REMISION)
                ->sortByDesc('id')
                ->first();

            $snapshot = [
                'destinatario_nombre' => (string) $tarea->destinatario_nombre,
                'destinatario_telefono' => (string) $tarea->destinatario_telefono,
                'municipio_destino' => (string) $tarea->municipio_destino,
                'direccion_referencia' => $tarea->direccion_referencia,
                'transporte' => $paqueteria?->nombre ?? '—',
                'modalidad_cobro' => (string) $tarea->modalidad_cobro,
                'folio' => $tarea->pedido?->folio_remision ?: $tarea->pedido?->folio ?: (string) $tarea->pedido_bma_id,
                'version' => $siguienteVersion,
                'fecha' => now()->format('d/m/Y H:i'),
            ];

            $pdf = Pdf::loadView($plantilla, ['caratula' => $snapshot])
                ->setPaper('letter', 'portrait');
            $contenido = $pdf->output();
            $hash = hash('sha256', $contenido);
            $nombre = 'caratula_'.Str::random(24).'.pdf';
            $ruta = "pedidos_bma/caratulas/{$tarea->id}/{$nombre}";
            Storage::disk('local')->put($ruta, $contenido);

            $caratula = PedidoBmaCaratula::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'pedido_bma_id' => $tarea->pedido_bma_id,
                'version' => $siguienteVersion,
                'destinatario_nombre' => $snapshot['destinatario_nombre'],
                'destinatario_telefono' => $snapshot['destinatario_telefono'],
                'municipio_destino' => $snapshot['municipio_destino'],
                'direccion_referencia' => $snapshot['direccion_referencia'],
                'catalogo_paqueteria_id' => $tarea->catalogo_paqueteria_id,
                'modalidad_cobro' => $tarea->modalidad_cobro,
                'documento_identificacion_id' => $identificacion?->id,
                'documento_remision_id' => $remision?->id,
                'ruta_pdf' => $ruta,
                'hash_sha256' => $hash,
                'estado' => PedidoBmaCaratula::ESTADO_GENERADA,
                'generada_por_id' => $usuario->id,
                'generada_at' => now(),
                'motivo_regeneracion' => $motivoRegeneracion,
            ]);

            $tarea->update(['version' => $tarea->version + 1]);

            $pedido = $tarea->pedido;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                $motivoRegeneracion
                    ? "Carátula v{$siguienteVersion} regenerada: {$motivoRegeneracion}"
                    : "Carátula v{$siguienteVersion} generada.",
                $motivoRegeneracion
                    ? AccionesHistorialPedidoBma::CARATULA_REGENERADA
                    : AccionesHistorialPedidoBma::CARATULA_GENERADA
            );

            return $caratula->fresh(['paqueteria']);
        });
    }
}
