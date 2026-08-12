<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResponderPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineasCaja
     * @param  array{
     *   estado_fisico_general?: string,
     *   comentario_fisico_general?: string|null,
     *   evidencias_generales?: list<UploadedFile>,
     *   evidencias_envios?: array<int, list<UploadedFile>>,
     *   revisiones?: list<array<string, mixed>>
     * }  $revisionFisica
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $lineasCaja, array $revisionFisica = []): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeResponderPesaje()) {
            throw new \RuntimeException('Este pedido no está pendiente de pesaje.');
        }

        $lineas = $this->normalizarLineas($lineasCaja);
        if ($lineas === []) {
            throw new \InvalidArgumentException('Debe indicar al menos un envío con tipo de caja y pesos.');
        }

        $comentarioGeneral = trim((string) ($revisionFisica['comentario_fisico_general'] ?? ''));
        /** @var list<UploadedFile> $evidenciasGenerales */
        $evidenciasGenerales = array_values(array_filter(
            $revisionFisica['evidencias_generales'] ?? [],
            fn ($f) => $f instanceof UploadedFile
        ));

        $revisiones = $this->normalizarRevisiones($revisionFisica['revisiones'] ?? []);
        $evidenciasEnvios = $this->normalizarEvidenciasEnvios(
            $revisionFisica['evidencias_envios'] ?? [],
            count($lineas)
        );

        // Estado general se deriva de productos (default Bueno); ya no se captura en UI.
        $estadoGeneral = $this->derivarEstadoGeneral(
            $revisiones,
            (string) ($revisionFisica['estado_fisico_general'] ?? PedidoBmaRevisionProducto::ESTADO_BUENO)
        );

        // Foto del lote por cada caja (siempre).
        foreach ($lineas as $i => $_linea) {
            if (($evidenciasEnvios[$i] ?? []) === []) {
                throw new \InvalidArgumentException('Adjunte al menos una foto del contenido del envío '.($i + 1).'.');
            }
        }

        $tipos = CatalogoTipoCajaPedido::query()
            ->whereIn('id', array_column($lineas, 'catalogo_tipo_caja_id'))
            ->get()
            ->keyBy('id');

        if ($tipos->count() !== count(array_unique(array_column($lineas, 'catalogo_tipo_caja_id')))) {
            throw new \InvalidArgumentException('Una o más cajas del catálogo no existen.');
        }

        return DB::transaction(function () use (
            $pedido, $usuarioId, $lineas, $tipos,
            $estadoGeneral, $comentarioGeneral, $evidenciasGenerales, $evidenciasEnvios, $revisiones
        ) {
            PedidoBmaCaja::where('pedido_bma_id', $pedido->id)->delete();
            PedidoBmaRevisionProducto::where('pedido_bma_id', $pedido->id)->delete();

            $pesoRealTotal = 0.0;
            $pesoVolumetricoTotal = 0.0;
            $pesoCobradoTotal = 0.0;
            $cajaPrincipalId = null;
            $maxVol = -1.0;
            $orden = 0;
            /** @var list<PedidoBmaCaja> $cajasCreadas */
            $cajasCreadas = [];

            foreach ($lineas as $linea) {
                $tipo = $tipos->get($linea['catalogo_tipo_caja_id']);
                $pesoReal = $linea['peso_real_kg'];
                $pesoVol = $linea['peso_volumetrico_kg'];
                $pesoCobrado = PedidoBma::calcularPesoCobradoGuia($pesoReal, $pesoVol);

                $cajasCreadas[] = PedidoBmaCaja::create([
                    'pedido_bma_id' => $pedido->id,
                    'catalogo_tipo_caja_id' => $tipo->id,
                    'cantidad' => 1,
                    'orden' => $orden,
                    'largo' => $linea['largo'],
                    'ancho' => $linea['ancho'],
                    'alto' => $linea['alto'],
                    'peso_real_kg' => $pesoReal,
                    'peso_volumetrico_kg' => $pesoVol,
                    'peso_cobrado_kg' => $pesoCobrado,
                    'catalogo_tipo_guia_id' => null,
                ]);

                $pesoRealTotal += $pesoReal;
                $pesoVolumetricoTotal += $pesoVol;
                $pesoCobradoTotal += (float) $pesoCobrado;

                if ($pesoVol > $maxVol) {
                    $maxVol = $pesoVol;
                    $cajaPrincipalId = $tipo->id;
                }

                $orden++;
            }

            if ($cajaPrincipalId === null) {
                $cajaPrincipalId = $lineas[0]['catalogo_tipo_caja_id'];
            }

            $tieneObs = PedidoBmaRevisionProducto::esObservacionParaVentas($estadoGeneral)
                || collect($revisiones)->contains(
                    fn (array $r) => PedidoBmaRevisionProducto::esObservacionParaVentas($r['estado_fisico'])
                );

            $estatus = $pedido->estatus;
            $numeroCajas = count($lineas);

            $pedido->update([
                'peso_real_kg' => round($pesoRealTotal, 4),
                'peso_volumetrico_kg' => round($pesoVolumetricoTotal, 4),
                'peso_cobrado_guia_kg' => round($pesoCobradoTotal, 4),
                'numero_cajas' => $numeroCajas,
                'catalogo_tipo_caja_id' => $cajaPrincipalId,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
                'pesaje_respondido_at' => now(),
                'pesaje_respondido_por_id' => $usuarioId,
                'estado_fisico_general' => $estadoGeneral,
                'comentario_fisico_general' => $comentarioGeneral !== '' ? $comentarioGeneral : null,
                'tiene_observaciones_fisicas' => $tieneObs,
            ]);

            $ordenDoc = (int) $pedido->documentos()->max('orden') + 1;
            foreach ($evidenciasGenerales as $file) {
                $this->guardarEvidencia(
                    $pedido,
                    $file,
                    PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    $ordenDoc++,
                    PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    null,
                    $comentarioGeneral !== '' ? $comentarioGeneral : 'Foto del lote'
                );
            }

            foreach ($cajasCreadas as $idxCaja => $caja) {
                foreach ($evidenciasEnvios[$idxCaja] ?? [] as $file) {
                    $this->guardarEvidencia(
                        $pedido,
                        $file,
                        PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        $ordenDoc++,
                        PedidoBmaDocumento::RELACION_ENVIO_CAJA,
                        $caja->id,
                        'Contenido envío '.($idxCaja + 1)
                    );
                }
            }

            foreach ($revisiones as $i => $rev) {
                $row = PedidoBmaRevisionProducto::create([
                    'pedido_bma_id' => $pedido->id,
                    'orden' => $i,
                    'descripcion_producto' => $rev['descripcion_producto'],
                    'estado_fisico' => $rev['estado_fisico'],
                    'comentario' => $rev['comentario'],
                    'unica_pieza' => $rev['unica_pieza'],
                    'mejor_ejemplar' => $rev['mejor_ejemplar'],
                ]);

                foreach ($rev['evidencias'] as $file) {
                    $this->guardarEvidencia(
                        $pedido,
                        $file,
                        PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        $ordenDoc++,
                        PedidoBmaDocumento::RELACION_REVISION_PRODUCTO,
                        $row->id,
                        $rev['comentario'] ?: $rev['descripcion_producto']
                    );
                }
            }

            $detalleHist = sprintf(
                'Pesaje CEDIS respondido: %.4f kg cobrados, %d envío(s). Estado físico: %s.%s',
                $pesoCobradoTotal,
                $numeroCajas,
                PedidoBmaRevisionProducto::LABELS[$estadoGeneral] ?? $estadoGeneral,
                $tieneObs ? ' Con observaciones — Ventas debe revisar.' : ''
            );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                $detalleHist,
                AccionesHistorialPedidoBma::RESPUESTA_PESAJE
            );

            $tituloNotif = $tieneObs
                ? 'CEDIS respondió el pesaje con observaciones físicas'
                : 'CEDIS respondió el pesaje de tu pedido';

            $folioQ = $pedido->folio_remision ?: $pedido->folio ?: '';
            $urlNotif = $tieneObs
                ? '/control-pedidos?tab=OBS_CEDIS'.($folioQ !== '' ? '&q='.rawurlencode($folioQ) : '')
                : '/control-pedidos'.($folioQ !== '' ? '?q='.rawurlencode($folioQ) : '');

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_pesaje_listo',
                $tituloNotif,
                [],
                $usuarioId,
                true,
                [
                    'url' => $urlNotif,
                    'con_observaciones_fisicas' => $tieneObs,
                ]
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'tipoGuia',
                'pesajeRespondidoPor', 'revisionesProducto',
            ]);
        });
    }

    private function guardarEvidencia(
        PedidoBma $pedido,
        UploadedFile $file,
        string $tipo,
        int $orden,
        ?string $relacionTipo,
        ?int $relacionId,
        ?string $comentario
    ): void {
        $ruta = $file->store('pedidos_bma/'.$pedido->id, 'public');
        $pedido->documentos()->create([
            'tipo' => $tipo,
            'ruta_archivo' => $ruta,
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'orden' => $orden,
            'comentario' => $comentario,
            'relacion_tipo' => $relacionTipo,
            'relacion_id' => $relacionId,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array{
     *   catalogo_tipo_caja_id:int,
     *   largo:float,
     *   ancho:float,
     *   alto:float,
     *   peso_real_kg:float,
     *   peso_volumetrico_kg:float
     * }>
     */
    private function normalizarLineas(array $lineasCaja): array
    {
        $out = [];
        foreach ($lineasCaja as $linea) {
            $tipoId = (int) ($linea['catalogo_tipo_caja_id'] ?? 0);
            $pesoReal = isset($linea['peso_real_kg']) ? (float) $linea['peso_real_kg'] : null;
            $pesoVol = isset($linea['peso_volumetrico_kg']) ? (float) $linea['peso_volumetrico_kg'] : null;
            $largo = isset($linea['largo']) ? (float) $linea['largo'] : null;
            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;

            if ($tipoId <= 0 || $pesoReal === null || $pesoVol === null
                || $largo === null || $ancho === null || $alto === null) {
                continue;
            }
            if ($pesoReal < 0 || $pesoVol < 0 || $largo < 0 || $ancho < 0 || $alto < 0) {
                continue;
            }

            $out[] = [
                'catalogo_tipo_caja_id' => $tipoId,
                'largo' => round($largo, 2),
                'ancho' => round($ancho, 2),
                'alto' => round($alto, 2),
                'peso_real_kg' => round($pesoReal, 4),
                'peso_volumetrico_kg' => round($pesoVol, 4),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return array<int, list<UploadedFile>>
     */
    private function normalizarEvidenciasEnvios(mixed $raw, int $numEnvios): array
    {
        $out = [];
        for ($i = 0; $i < $numEnvios; $i++) {
            $lista = is_array($raw) ? ($raw[$i] ?? []) : [];
            if (! is_array($lista)) {
                $lista = $lista ? [$lista] : [];
            }
            $out[$i] = array_values(array_filter(
                $lista,
                fn ($f) => $f instanceof UploadedFile
            ));
        }

        return $out;
    }

    /**
     * @param  list<array{estado_fisico:string}>  $revisiones
     */
    private function derivarEstadoGeneral(array $revisiones, string $fallback): string
    {
        $severidad = [
            PedidoBmaRevisionProducto::ESTADO_BUENO => 0,
            PedidoBmaRevisionProducto::ESTADO_REGULAR => 1,
            PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA => 2,
            PedidoBmaRevisionProducto::ESTADO_MALO => 3,
            PedidoBmaRevisionProducto::ESTADO_DANADO => 4,
        ];

        $peor = in_array($fallback, PedidoBmaRevisionProducto::ESTADOS, true)
            ? $fallback
            : PedidoBmaRevisionProducto::ESTADO_BUENO;

        foreach ($revisiones as $rev) {
            $estado = (string) ($rev['estado_fisico'] ?? '');
            if (($severidad[$estado] ?? -1) > ($severidad[$peor] ?? -1)) {
                $peor = $estado;
            }
        }

        return $peor;
    }

    /**
     * @param  list<array<string, mixed>>  $revisiones
     * @return list<array{
     *   descripcion_producto:string,
     *   estado_fisico:string,
     *   comentario:?string,
     *   unica_pieza:bool,
     *   mejor_ejemplar:bool,
     *   evidencias:list<UploadedFile>
     * }>
     */
    private function normalizarRevisiones(array $revisiones): array
    {
        $out = [];
        foreach ($revisiones as $rev) {
            $desc = trim((string) ($rev['descripcion_producto'] ?? ''));
            $estado = (string) ($rev['estado_fisico'] ?? '');
            if ($desc === '' || ! in_array($estado, PedidoBmaRevisionProducto::ESTADOS, true)) {
                continue;
            }
            $comentario = trim((string) ($rev['comentario'] ?? ''));
            $evidencias = array_values(array_filter(
                $rev['evidencias'] ?? [],
                fn ($f) => $f instanceof UploadedFile
            ));

            if (PedidoBmaRevisionProducto::requiereComentario($estado) && $comentario === '') {
                $motivo = $estado === PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                    ? 'sin existencias'
                    : 'malo/dañado';
                throw new \InvalidArgumentException(
                    "El producto «{$desc}» en estado {$motivo} requiere comentario."
                );
            }
            if (PedidoBmaRevisionProducto::requiereEvidencia($estado) && $evidencias === []) {
                throw new \InvalidArgumentException(
                    "El producto «{$desc}» en estado malo/dañado requiere evidencia."
                );
            }

            $out[] = [
                'descripcion_producto' => mb_substr($desc, 0, 255),
                'estado_fisico' => $estado,
                'comentario' => $comentario !== '' ? $comentario : null,
                'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'evidencias' => $evidencias,
            ];
        }

        return $out;
    }
}
