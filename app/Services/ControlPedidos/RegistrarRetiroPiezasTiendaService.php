<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ActualizacionConsulta;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Documenta el retiro pedido por el cliente y avisa a quien revisó. No aparta, no cobra y no confirma la entrega. */
final class RegistrarRetiroPiezasTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    /**
     * @param  list<array{sku: string, descripcion: string, cantidad: int}>  $piezas
     * @param  list<UploadedFile>  $evidencias
     * @param  list<UploadedFile>  $documentos
     */
    public function ejecutar(
        PedidoBma $pedido,
        User $usuario,
        array $piezas,
        string $motivo,
        array $evidencias = [],
        string $entrada = 'catalogo',
        array $documentos = [],
        ?string $motivoOpcion = null,
    ): PedidoBma {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró el pedido solicita el retiro.',
            ]);
        }

        $motivo = trim($motivo);
        $entrada = $entrada === 'documento' ? 'documento' : 'catalogo';
        $lineas = $entrada === 'documento' ? [] : $this->lineas($piezas);
        $archivosPiezas = $this->archivos($documentos);
        $archivosPeticion = $this->archivos($evidencias);
        if ($motivo === '' || ($entrada === 'catalogo' && $lineas === []) || ($entrada === 'documento' && $archivosPiezas === [])) {
            throw ValidationException::withMessages([
                'retiro' => $entrada === 'documento'
                    ? 'Adjunte el PDF o las capturas de las piezas a retirar e indique el motivo.'
                    : 'Indique las piezas y el motivo del retiro.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $lineas, $motivo, $entrada, $archivosPiezas, $archivosPeticion, $motivoOpcion) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if ($pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages([
                    'retiro' => 'El pedido ya está pagado o remisionado. Use su gestión documentada; no se retiran piezas de forma informal.',
                ]);
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if (! $this->hayRespuesta($pedido, $json)) {
                throw ValidationException::withMessages([
                    'retiro' => 'El retiro se solicita cuando ya hay una respuesta de las piezas.',
                ]);
            }

            $previos = is_array($json['retiro_piezas'] ?? null) ? $json['retiro_piezas'] : [];
            $version = count($previos) + 1;
            $orden = (int) $pedido->documentos()->max('orden');
            $docsPiezas = $this->guardarArchivos($pedido, $archivosPiezas, $orden, PedidoBmaDocumento::RELACION_RETIRO_PIEZAS, 'Piezas a retirar', $version);
            $orden += count($docsPiezas);
            $docsPeticion = $this->guardarArchivos($pedido, $archivosPeticion, $orden, PedidoBmaDocumento::RELACION_RETIRO_PETICION, 'Evidencia petición Cliente', $version);

            $solicitadas = $lineas;
            $retiradas = $entrada === 'catalogo' ? $this->retirarFaltantes($pedido, $usuario, $lineas, $motivo) : [];
            $soloFaltantes = $retiradas !== [] && $lineas === [];
            $previos[] = [
                'version' => $version,
                'estado' => $soloFaltantes ? 'aplicada' : 'pendiente',
                ...($soloFaltantes ? ['atendido_at' => now()->toIso8601String(), 'atendido_por_id' => $usuario->id, 'atendido_por' => $usuario->name, 'revisiones_retiradas' => $retiradas, 'resultado' => 'Piezas sin existencias retiradas del pedido.'] : []),
                'entrada' => $entrada,
                'motivo' => $motivo,
                'motivo_opcion' => $motivoOpcion,
                'solicitado_at' => now()->toIso8601String(),
                'solicitado_por_id' => $usuario->id,
                'solicitado_por' => $usuario->name,
                'piezas' => $lineas,
                'piezas_solicitadas' => $solicitadas,
                'faltantes_retirados' => $retiradas,
                'documentos_piezas' => $docsPiezas,
                'documentos_peticion' => $docsPeticion,
            ];
            $json['retiro_piezas'] = array_values($previos);
            if (is_array($json['revision_disponibilidad'] ?? null)) {
                $pedido->solicitud_preparacion_json = $json;
                $json['revision_disponibilidad']['aceptacion_vigente'] = $retiradas !== [] && ! $pedido->tieneAjusteTiendaPendiente();
            }
            ActualizacionConsulta::solicitar($json);
            $pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => null, 'consulta_cerrada_por_id' => null,
                'estatus_envio' => ($json['area_revision'] ?? '') === 'control_pedidos' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE : $pedido->estatus_envio]);

            $area = (string) ($json['area_revision'] ?? '');
            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    $soloFaltantes ? 'Piezas sin existencias retiradas del pedido por Ventas.' : 'Retiro de piezas solicitado. Quien revisó debe retirar las piezas apartadas.',
                    AccionesHistorialPedidoBma::COMPLEMENTO
                );
            }

            $tareaId = $pedido->tareaPreparacionVigente()->value('id')
                ?: $pedido->tareaPreparacionRespondida()->value('id');
            $permiso = $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
                ? 'control_pedidos.cedis'
                : 'control_pedidos.tienda.ver';
            $url = $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
                ? '/control-pedidos/cedis'
                : '/control-pedidos/tienda'.($tareaId ? '?tarea='.$tareaId : '');
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_consulta_actualizada',
                $soloFaltantes ? 'Ventas retiró las piezas sin existencias del pedido.' : 'Se solicitó el retiro de piezas. Continúe solo con las que siguen en el pedido.',
                [$permiso],
                $usuario->id,
                true,
                ['url' => $url]
            );

            return $pedido->fresh(['documentos', 'estatus']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $piezas
     * @return list<int>
     */
    private function retirarFaltantes(PedidoBma $pedido, User $actor, array &$piezas, string $motivo): array
    {
        if (($pedido->solicitud_preparacion_json['modalidad_entrega'] ?? '') !== 'tienda') {
            return [];
        }
        $encontradas = collect();
        $pendientes = [];
        foreach ($piezas as $pieza) {
            if (! empty($pieza['revision_id'])) {
                $revision = $pedido->revisionesProducto()->find($pieza['revision_id']);
                if ($revision && $revision->estado_fisico !== 'sin_existencia') {
                    $pendientes[] = $pieza;

                    continue;
                }
            }
            $query = $pedido->revisionesProducto()->where('estado_fisico', 'sin_existencia')->whereNull('resolucion');
            if (! empty($pieza['revision_id'])) {
                $query->whereKey($pieza['revision_id']);
            } else {
                $query->whereRaw('lower(sku) = ?', [mb_strtolower($pieza['sku'])]);
            }
            $filas = $query->lockForUpdate()->get();
            if ($filas->isEmpty()) {
                if (! empty($pieza['revision_id'])) {
                    throw ValidationException::withMessages(['piezas' => 'La pieza sin existencias ya no está pendiente en este pedido.']);
                }

                $pendientes[] = $pieza;

                continue;
            }
            if ((int) $filas->sum('cantidad_solicitada') !== $pieza['cantidad']) {
                throw ValidationException::withMessages(['piezas' => 'Indique la cantidad completa reportada sin existencias para retirarla.']);
            }
            foreach ($filas as $fila) {
                if ($encontradas->has($fila->id)) {
                    throw ValidationException::withMessages(['piezas' => 'La pieza está seleccionada más de una vez.']);
                }
                $encontradas->put($fila->id, $fila);
            }
        }
        foreach ($encontradas as $fila) {
            $fila->update(['resolucion' => 'retirar', 'resolucion_nota' => $motivo, 'resolucion_por_id' => $actor->id, 'resolucion_at' => now()]);
        }

        $piezas = $pendientes;

        return $encontradas->keys()->all();
    }

    private function hayRespuesta(PedidoBma $pedido, array $json): bool
    {
        $estado = $json['revision_disponibilidad']['estado'] ?? null;
        if (in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
            return true;
        }
        if ($pedido->pesaje_respondido_at !== null) {
            return true;
        }

        return $pedido->tareaPreparacionRespondida()->exists()
            || $pedido->tareasPreparacion()->where('estado', PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA)->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $piezas
     * @return list<array{sku: string, descripcion: string, cantidad: int}>
     */
    private function lineas(array $piezas): array
    {
        $salida = [];
        foreach ($piezas as $pieza) {
            if (! is_array($pieza)) {
                continue;
            }
            $sku = trim((string) ($pieza['sku'] ?? ''));
            $descripcion = trim((string) ($pieza['descripcion'] ?? ''));
            $cantidad = (int) ($pieza['cantidad'] ?? 0);
            if (($sku === '' && empty($pieza['revision_id'])) || $descripcion === '' || $cantidad < 1) {
                continue;
            }
            $salida[] = [
                'sku' => $sku,
                ...(! empty($pieza['revision_id']) ? ['revision_id' => (int) $pieza['revision_id']] : []),
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<mixed>  $archivos
     * @return list<UploadedFile>
     */
    private function archivos(array $archivos): array
    {
        return array_values(array_filter($archivos, fn ($archivo) => $archivo instanceof UploadedFile));
    }

    /**
     * @param  list<UploadedFile>  $archivos
     * @return list<array{id: int, nombre: string, mime_type: ?string, grupo: string}>
     */
    private function guardarArchivos(PedidoBma $pedido, array $archivos, int $orden, string $relacion, string $comentario, int $version): array
    {
        $guardados = [];
        foreach ($archivos as $archivo) {
            $orden++;
            $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/retiro', 'public');
            $documento = $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => $orden,
                'comentario' => $comentario,
                'relacion_tipo' => $relacion,
                'relacion_id' => $version,
                'activo' => true,
            ]);
            $guardados[] = [
                'id' => $documento->id,
                'nombre' => $documento->nombre_original,
                'mime_type' => $documento->mime_type,
                'grupo' => $relacion,
            ];
        }

        return $guardados;
    }
}
