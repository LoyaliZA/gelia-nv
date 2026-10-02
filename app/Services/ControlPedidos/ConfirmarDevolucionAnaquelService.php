<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConfirmarDevolucionAnaquelService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $ubicacion,
        UploadedFile $foto,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.apartado.confirmar_devolucion')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para confirmar la devolución al anaquel.',
            ]);
        }

        $ubicacion = trim($ubicacion);
        if ($ubicacion === '') {
            throw ValidationException::withMessages([
                'ubicacion' => 'Indique la ubicación final en el anaquel.',
            ]);
        }

        if (! $foto->isValid()) {
            throw ValidationException::withMessages([
                'foto' => 'Adjunte la foto de la mercancía devuelta al anaquel.',
            ]);
        }

        $ruta = null;

        try {
            return DB::transaction(function () use ($tarea, $usuario, $ubicacion, $foto, $versionEsperada, &$ruta) {
                $this->asegurar->ejecutar($tarea);
                $cumplimiento = PedidoBmaCumplimientoFisico::query()
                    ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $clave = 'devuelta:'.$cumplimiento->id;
                if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL) {
                    return $cumplimiento;
                }

                if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                    throw ValidationException::withMessages([
                        'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                    ]);
                }

                MaquinaEstadosCumplimientoFisico::assertTransicion(
                    $cumplimiento->estado,
                    PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL
                );

                $ruta = $foto->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'public');
                $documento = $tarea->documentos()->create([
                    'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_DEVOLUCION_ANAQUEL,
                    'ruta_interna' => $ruta,
                    'nombre_original' => $foto->getClientOriginalName(),
                    'mime_type' => $foto->getMimeType(),
                    'tamano_bytes' => $foto->getSize(),
                    'hash_sha256' => hash_file('sha256', $foto->getRealPath()),
                    'subido_por_id' => $usuario->id,
                    'subido_at' => now(),
                    'inmutable' => true,
                ]);

                $cumplimiento->update([
                    'estado' => PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
                    'ubicacion' => $ubicacion,
                    'devuelta_at' => now(),
                    'devuelta_por_id' => $usuario->id,
                    'documento_devolucion_id' => $documento->id,
                    'version' => $cumplimiento->version + 1,
                ]);

                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_DEVUELTA_ANAQUEL,
                    $usuario,
                    $clave,
                    'Devolución confirmada con ubicación y foto.',
                    [
                        'ubicacion' => $ubicacion,
                        'documento_id' => $documento->id,
                        'inventario_sincronizado' => false,
                    ]
                );

                return $cumplimiento->fresh();
            });
        } catch (\Throwable $e) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }
            throw $e;
        }
    }
}
