<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\MatrizRequisitosPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use Illuminate\Support\Facades\Cache;

class CalcularRequisitosPreparacionService
{
    public function __construct(
        private PreparacionTiendaConfig $config,
    ) {}

    /**
     * @return array{
     *   evidencia_general_obligatoria: bool,
     *   evidencia_por_producto: bool,
     *   peso_real_obligatorio: bool,
     *   peso_volumetrico_obligatorio: bool,
     *   caja_obligatoria: bool,
     *   observaciones_fisicas_obligatorias: bool,
     *   traslado_cedis: bool,
     *   estados_fisicos_permitidos: list<string>,
     *   resguardo: bool,
     *   fecha_limite: ?string,
     *   faltantes: list<string>
     * }
     */
    public function efectivos(PedidoBmaTareaPreparacion $tarea): array
    {
        $tarea->loadMissing(['modalidad', 'documentos', 'productos', 'paqueteria', 'pedido.vendedor.departamentos', 'pedido.vendedor.area.departamento']);
        $base = $tarea->modalidad?->requisitos_json ?? [];
        $matriz = $this->resolverMatriz($tarea);
        $transporte = $this->reglasTransporte($tarea);

        $req = array_merge($base, $matriz, $transporte);

        return [
            'evidencia_general_obligatoria' => (bool) ($req['evidencia_general_obligatoria'] ?? true),
            'evidencia_por_producto' => (bool) ($req['evidencia_por_producto'] ?? false),
            'peso_real_obligatorio' => (bool) ($req['peso_real_obligatorio'] ?? false),
            'peso_volumetrico_obligatorio' => (bool) ($req['peso_volumetrico_obligatorio'] ?? false),
            'caja_obligatoria' => (bool) ($req['caja_obligatoria'] ?? false),
            'observaciones_fisicas_obligatorias' => (bool) ($req['observaciones_fisicas_obligatorias'] ?? false),
            'traslado_cedis' => (bool) ($req['traslado_cedis'] ?? $tarea->requiere_traslado_cedis),
            'caratula' => (bool) ($req['caratula'] ?? $tarea->modalidad?->esEnvioMunicipio()),
            'requiere_identificacion' => (bool) ($req['requiere_identificacion'] ?? false),
            'requiere_remision' => (bool) ($req['requiere_remision'] ?? false),
            'permite_por_cobrar' => (bool) ($req['permite_por_cobrar'] ?? false),
            'campos_destino_obligatorios' => array_values($req['campos_destino_obligatorios'] ?? ['municipio', 'destinatario', 'telefono']),
            'estados_fisicos_permitidos' => array_values($req['estados_fisicos_permitidos'] ?? ['bueno', 'regular', 'malo', 'danado', 'sin_existencia']),
            'resguardo' => (bool) ($req['resguardo'] ?? $tarea->modalidad?->esTransferencia()),
            'fecha_limite' => $tarea->fecha_limite?->toIso8601String(),
            'faltantes' => [],
        ];
    }

    /**
     * @param  list<array{id?: int, cantidad_encontrada?: int|null, estado_fisico?: string|null}>  $productosInput
     * @param  array{peso_real_kg?: mixed, peso_volumetrico_kg?: mixed, catalogo_tipo_caja_id?: mixed, observaciones_fisicas?: mixed}  $datosExtra
     * @return list<string>
     */
    public function validarRespuesta(PedidoBmaTareaPreparacion $tarea, array $productosInput, array $datosExtra = []): array
    {
        $tarea->loadMissing(['productos', 'documentos']);
        $req = $this->efectivos($tarea);
        $faltantes = [];

        foreach ($tarea->productos as $producto) {
            $input = collect($productosInput)->firstWhere('id', $producto->id) ?? [];
            $cantidad = $input['cantidad_encontrada'] ?? null;
            $estado = $input['estado_fisico'] ?? null;

            if ($cantidad === null || $cantidad === '') {
                $faltantes[] = "Cantidad encontrada de «{$producto->descripcion_snapshot}»";
            }
            if ($estado === null || $estado === '') {
                $faltantes[] = "Estado físico de «{$producto->descripcion_snapshot}»";
            } elseif (! in_array($estado, $req['estados_fisicos_permitidos'], true)) {
                $faltantes[] = "Estado físico no permitido para «{$producto->descripcion_snapshot}»";
            }
        }

        if ($req['evidencia_general_obligatoria']) {
            $evidencias = $tarea->documentos
                ->where('inmutable', false)
                ->where('tipo_evidencia', 'evidencia_general')
                ->count();
            if ($evidencias < 1) {
                $faltantes[] = 'Al menos una evidencia general';
            }
        }

        if ($req['peso_real_obligatorio']) {
            $peso = $datosExtra['peso_real_kg'] ?? $tarea->peso_real_kg;
            if ($peso === null || $peso === '' || (float) $peso <= 0) {
                $faltantes[] = 'Peso real (kg)';
            }
        }

        if ($req['peso_volumetrico_obligatorio']) {
            $pesoVol = $datosExtra['peso_volumetrico_kg'] ?? $tarea->peso_volumetrico_kg;
            if ($pesoVol === null || $pesoVol === '' || (float) $pesoVol <= 0) {
                $faltantes[] = 'Peso volumétrico (kg)';
            }
        }

        if ($req['caja_obligatoria']) {
            $caja = $datosExtra['catalogo_tipo_caja_id'] ?? $tarea->catalogo_tipo_caja_id;
            if (! $caja) {
                $faltantes[] = 'Tipo de caja';
            }
        }

        if ($req['observaciones_fisicas_obligatorias']) {
            $obs = trim((string) ($datosExtra['observaciones_fisicas'] ?? $tarea->observaciones_fisicas ?? ''));
            if ($obs === '') {
                $faltantes[] = 'Observaciones físicas';
            }
        }

        if (! empty($req['requiere_identificacion'])) {
            $tieneId = $tarea->documentos
                ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_IDENTIFICACION)
                ->isNotEmpty();
            if (! $tieneId) {
                $faltantes[] = 'Identificación del destinatario';
            }
        }

        if (! empty($req['requiere_remision'])) {
            $tieneRem = $tarea->documentos
                ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_REMISION)
                ->isNotEmpty();
            if (! $tieneRem) {
                $faltantes[] = 'Remisión';
            }
        }

        return $faltantes;
    }

    /**
     * @param  array<string, mixed>  $req
     * @return list<string>
     */
    public function validarDocumentosMunicipio(PedidoBmaTareaPreparacion $tarea, array $req): array
    {
        $tarea->loadMissing(['documentos']);
        $faltantes = [];

        if (! empty($req['requiere_identificacion'])) {
            if ($tarea->documentos->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_IDENTIFICACION)->isEmpty()) {
                $faltantes[] = 'Identificación del destinatario';
            }
        }
        if (! empty($req['requiere_remision'])) {
            if ($tarea->documentos->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_REMISION)->isEmpty()) {
                $faltantes[] = 'Remisión';
            }
        }
        if (! empty($req['evidencia_general_obligatoria'])) {
            if ($tarea->documentos->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL)->isEmpty()) {
                $faltantes[] = 'Evidencia general';
            }
        }

        return $faltantes;
    }

    public function calcularFechaLimite(CatalogoModalidadPreparacionPedido $modalidad): ?\Carbon\Carbon
    {
        if (! $modalidad->esTransferencia()) {
            return null;
        }

        return now($this->config->zonaHoraria())
            ->addDays($this->config->diasResguardo())
            ->endOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasTransporte(PedidoBmaTareaPreparacion $tarea): array
    {
        $paq = $tarea->paqueteria;
        if (! $paq || ! $tarea->modalidad?->esEnvioMunicipio()) {
            return [];
        }

        $reglas = $paq->reglasMunicipio();

        return [
            'requiere_identificacion' => $reglas['requiere_identificacion'],
            'requiere_remision' => $reglas['requiere_remision'],
            'permite_por_cobrar' => $reglas['permite_por_cobrar'],
            'peso_real_obligatorio' => $reglas['requiere_peso'] || (bool) (($tarea->modalidad->requisitos_json['peso_real_obligatorio'] ?? false)),
            'caja_obligatoria' => $reglas['requiere_caja'] || (bool) (($tarea->modalidad->requisitos_json['caja_obligatoria'] ?? false)),
            'evidencia_general_obligatoria' => $reglas['requiere_evidencia_conjunto']
                || (bool) (($tarea->modalidad->requisitos_json['evidencia_general_obligatoria'] ?? true)),
            'campos_destino_obligatorios' => $reglas['campos_destino_obligatorios'],
            'caratula' => $reglas['requiere_caratula'] || true,
            'plantilla_caratula' => $reglas['plantilla_caratula'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverMatriz(PedidoBmaTareaPreparacion $tarea): array
    {
        $codigoModalidad = $tarea->modalidad?->codigo;
        if (! $codigoModalidad) {
            return [];
        }

        $filas = Cache::remember('cp.matriz_requisitos_prep.v1', 60, function () {
            return MatrizRequisitosPreparacion::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get();
        });

        $deptoCodigo = $this->departamentoCodigoPedido($tarea);
        $tipoIntegracion = $tarea->pedido?->pedido_principal_id ? 'complemento' : 'pedido_principal';
        $almacenId = (int) $tarea->almacen_id;

        $candidatas = $filas->filter(fn ($f) => $f->codigo_modalidad === $codigoModalidad);
        if ($candidatas->isEmpty()) {
            return [];
        }

        $mejor = null;
        $mejorScore = -1;
        foreach ($candidatas as $fila) {
            $score = 0;
            if ($fila->departamento_codigo !== null && $fila->departamento_codigo !== '') {
                if ($deptoCodigo === null || strcasecmp((string) $fila->departamento_codigo, $deptoCodigo) !== 0) {
                    continue;
                }
                $score += 4;
            }
            if ($fila->almacen_origen_id !== null) {
                if ((int) $fila->almacen_origen_id !== $almacenId) {
                    continue;
                }
                $score += 2;
            }
            if ($fila->tipo_integracion !== null && $fila->tipo_integracion !== '') {
                if ($fila->tipo_integracion !== $tipoIntegracion) {
                    continue;
                }
                $score += 1;
            }
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $fila;
            }
        }

        return is_array($mejor?->requisitos_json) ? $mejor->requisitos_json : [];
    }

    private function departamentoCodigoPedido(PedidoBmaTareaPreparacion $tarea): ?string
    {
        $vendedor = $tarea->pedido?->vendedor;
        if (! $vendedor) {
            return null;
        }

        $depto = $vendedor->departamentos?->first()
            ?? $vendedor->area?->departamento;

        $codigo = $depto?->codigo ?: null;
        if ($codigo) {
            return (string) $codigo;
        }

        // Fallback: normalizar nombre a código estable (sin hardcodear reglas por nombre en PHP de negocio).
        $nombre = trim((string) ($depto?->nombre ?? ''));
        if ($nombre === '') {
            return null;
        }

        return strtoupper(str_replace([' ', '-'], '_', $nombre));
    }
}
