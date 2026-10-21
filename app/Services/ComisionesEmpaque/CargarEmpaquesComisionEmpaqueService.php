<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CargarEmpaquesComisionEmpaqueService
{
    public function __construct(
        private GestionarUnidadComisionEmpaqueService $gestionar,
        private AlcanceAreaComisionEmpaque $alcance,
    ) {}

    /**
     * @return array{errores: list<array{fila: int, mensaje: string}>, filas: list<array<string, mixed>>, resumen: array{total: int, pendientes: int, pagadas: int, repetidas: int}}
     */
    public function interpretar(string $csv, User $responsable): array
    {
        $errores = [];
        $filas = [];
        $claves = [];
        $registros = $this->filasCsv($csv);
        if ($registros === []) {
            return [
                'errores' => [['fila' => 1, 'mensaje' => 'El archivo no tiene filas.']],
                'filas' => [],
                'resumen' => ['total' => 0, 'pendientes' => 0, 'pagadas' => 0, 'repetidas' => 0],
            ];
        }

        foreach ($registros as $registro) {
            $numero = $registro['numero'];
            $valores = $registro['valores'];
            $fecha = $this->fecha($valores['fecha'] ?? '');
            $cliente = trim((string) ($valores['cliente'] ?? ''));
            $cajas = filter_var($valores['cajas'] ?? '', FILTER_VALIDATE_INT);
            $remision = trim((string) ($valores['remision'] ?? ''));
            $paqueteria = trim((string) ($valores['paqueteria'] ?? ''));
            $logistica = $this->logistica($valores['logistica'] ?? '');
            $empacador = trim((string) ($valores['empaco'] ?? ''));
            $liquidacion = $this->liquidacion($valores['liquidacion'] ?? '');
            $area = $this->area($valores['area'] ?? '');
            $fallos = [];
            if (! $fecha) {
                $fallos[] = 'la fecha de empaque no es válida';
            }
            if ($cliente === '') {
                $fallos[] = 'falta el cliente';
            }
            if ($cajas === false || $cajas < 1) {
                $fallos[] = 'las cajas deben ser un entero mayor a cero';
            }
            if ($remision === '') {
                $fallos[] = 'falta la remisión';
            }
            if ($paqueteria === '') {
                $fallos[] = 'falta la paquetería';
            }
            if ($logistica === null) {
                $fallos[] = 'la logística debe ser con o sin';
            }
            if ($empacador === '') {
                $fallos[] = 'falta quién empacó';
            }
            if ($liquidacion === null) {
                $fallos[] = 'la liquidación debe ser pendiente o pagado fuera';
            }
            if (! $area) {
                $fallos[] = 'el área debe ser CEDIS o Tienda';
            } elseif (! $this->alcance->permite($responsable, (int) $area->id)) {
                $fallos[] = 'no tiene acceso a esa área';
            }
            if ($fallos !== []) {
                $errores[] = ['fila' => $numero, 'mensaje' => 'Fila '.$numero.': '.implode('; ', $fallos).'.'];

                continue;
            }

            $dia = $fecha->toDateString();
            $clave = $this->gestionar->claveDeCarga((int) $area->id, $remision, $dia);
            if (isset($claves[$clave])) {
                $errores[] = ['fila' => $numero, 'mensaje' => 'Fila '.$numero.': repite el área, la remisión y el día de la fila '.$claves[$clave].'.'];

                continue;
            }
            $claves[$clave] = $numero;
            if ($liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA
                && UnidadComisionEmpaque::query()
                    ->where('area_id', $area->id)
                    ->where('folio_remision', $remision)
                    ->whereDate('empacado_at', $dia)
                    ->where('situacion', '!=', UnidadComisionEmpaque::RECHAZADA)
                    ->where('clave_idempotencia', '!=', $clave)
                    ->exists()) {
                $errores[] = ['fila' => $numero, 'mensaje' => 'Fila '.$numero.': ya hay un empaque de esta área, remisión y día.'];

                continue;
            }
            if ($this->gestionar->cubiertaPorConstancia((int) $area->id, $remision, $dia)
                && ! UnidadComisionEmpaque::query()->where('clave_idempotencia', $clave)->exists()) {
                $errores[] = ['fila' => $numero, 'mensaje' => 'Fila '.$numero.': esa remisión ya está como constancia pagada fuera.'];

                continue;
            }
            $existente = UnidadComisionEmpaque::query()->where('clave_idempotencia', $clave)->first();
            $repetida = false;
            if ($existente) {
                $igual = (int) $existente->cantidad_origen === $cajas
                    && (string) $existente->cliente_nombre === $cliente
                    && (string) $existente->empacador_nombre === $empacador
                    && (string) $existente->liquidacion === $liquidacion
                    && mb_strtolower((string) ($existente->logistica['paqueteria'] ?? '')) === mb_strtolower($paqueteria)
                    && (string) ($existente->logistica['logistica'] ?? '') === $logistica;
                if (! $igual) {
                    $errores[] = ['fila' => $numero, 'mensaje' => 'Fila '.$numero.': esa remisión ya estaba registrada ese día con otros datos.'];

                    continue;
                }
                $repetida = true;
            }

            $filas[] = [
                'numero' => $numero,
                'repetida' => $repetida,
                'datos' => [
                    'clave_idempotencia' => $clave,
                    'area_id' => $area->id,
                    'empacado_at' => $dia.' 12:00:00',
                    'cantidad' => $cajas,
                    'cliente_nombre' => $cliente,
                    'folio_remision' => $remision,
                    'paqueteria' => $paqueteria,
                    'logistica' => $logistica,
                    'empacador_nombre' => $empacador,
                    'liquidacion' => $liquidacion,
                    'origen_registro' => 'carga',
                ],
            ];
        }

        return [
            'errores' => $errores,
            'filas' => $filas,
            'resumen' => [
                'total' => count($filas),
                'pendientes' => count(array_filter($filas, fn (array $fila) => $fila['datos']['liquidacion'] === UnidadComisionEmpaque::LIQUIDACION_PENDIENTE && ! $fila['repetida'])),
                'pagadas' => count(array_filter($filas, fn (array $fila) => $fila['datos']['liquidacion'] === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA && ! $fila['repetida'])),
                'repetidas' => count(array_filter($filas, fn (array $fila) => $fila['repetida'])),
            ],
        ];
    }

    /** @return array{nuevas: int, repetidas: int} */
    public function guardar(string $csv, User $responsable): array
    {
        $revision = $this->interpretar($csv, $responsable);
        if ($revision['errores'] !== []) {
            throw ValidationException::withMessages([
                'texto' => 'La carga tiene filas inválidas. No se guardó ninguna.',
            ]);
        }

        $nuevas = 0;
        DB::transaction(function () use ($revision, $responsable, &$nuevas) {
            foreach ($revision['filas'] as $fila) {
                $antes = UnidadComisionEmpaque::query()->where('clave_idempotencia', $fila['datos']['clave_idempotencia'])->exists();
                $this->gestionar->registrarFila($fila['datos'], $responsable);
                if (! $antes) {
                    $nuevas++;
                }
            }
        });

        return [
            'nuevas' => $nuevas,
            'repetidas' => count($revision['filas']) - $nuevas,
        ];
    }

    /** @return list<array{numero: int, valores: array<string, string>}> */
    private function filasCsv(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lineas = preg_split("/\r\n|\n|\r/", $csv) ?: [];
        $lineas = array_values(array_filter($lineas, fn (string $linea) => trim($linea) !== ''));
        if ($lineas === []) {
            return [];
        }
        $delimitador = substr_count($lineas[0], ';') > substr_count($lineas[0], ',') ? ';' : ',';
        $primera = str_getcsv($lineas[0], $delimitador);
        $mapa = $this->mapa($primera);
        $inicio = $mapa === null ? 0 : 1;
        if ($mapa === null) {
            $mapa = ['fecha', 'cliente', 'cajas', 'remision', 'paqueteria', 'logistica', 'empaco', 'area', 'liquidacion'];
        }
        $filas = [];
        foreach (array_slice($lineas, $inicio) as $indice => $linea) {
            $columnas = str_getcsv($linea, $delimitador);
            $valores = [];
            foreach ($mapa as $posicion => $campo) {
                $valores[$campo] = trim((string) ($columnas[$posicion] ?? ''));
            }
            $filas[] = ['numero' => $inicio + $indice + 1, 'valores' => $valores];
        }

        return $filas;
    }

    /** @param  list<string|null>  $columnas
     * @return array<int, string>|null
     */
    private function mapa(array $columnas): ?array
    {
        $alias = [
            'fecha' => ['fecha', 'fecha de empaque', 'empacado'],
            'cliente' => ['cliente'],
            'cajas' => ['cajas', 'numero de cajas', 'cantidad'],
            'remision' => ['remision', 'folio de remision', 'folio remision'],
            'paqueteria' => ['paqueteria', 'envio'],
            'logistica' => ['logistica'],
            'empaco' => ['empaco', 'quien empaco', 'persona', 'persona que empaco'],
            'area' => ['area'],
            'liquidacion' => ['liquidacion', 'pago'],
        ];
        $mapa = [];
        foreach ($columnas as $posicion => $columna) {
            $nombre = $this->normalizar((string) $columna);
            foreach ($alias as $campo => $opciones) {
                if (in_array($nombre, $opciones, true)) {
                    $mapa[$posicion] = $campo;
                }
            }
        }

        $presentes = array_values($mapa);

        return count(array_intersect($presentes, ['fecha', 'cliente', 'cajas', 'remision', 'area'])) >= 4 ? $mapa : null;
    }

    private function fecha(string $valor): ?Carbon
    {
        $valor = trim($valor);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat('!'.$formato, $valor);
            } catch (\Throwable) {
                continue;
            }
            if ($fecha instanceof Carbon && $fecha->format($formato) === $valor) {
                return $fecha;
            }
        }

        return null;
    }

    private function area(string $valor): ?AreaComisionEmpaque
    {
        $codigo = $this->normalizar($valor);
        if ($codigo === '') {
            return null;
        }

        return AreaComisionEmpaque::query()
            ->where('activo', true)
            ->where(function ($consulta) use ($codigo) {
                $consulta->whereRaw('LOWER(codigo) = ?', [$codigo])
                    ->orWhereRaw('LOWER(nombre) = ?', [$codigo]);
            })
            ->first();
    }

    private function logistica(string $valor): ?string
    {
        $texto = $this->normalizar($valor);
        if (in_array($texto, ['con', 'con logistica', 'si'], true)) {
            return 'con';
        }
        if (in_array($texto, ['sin', 'sin logistica', 'no'], true)) {
            return 'sin';
        }

        return null;
    }

    private function liquidacion(string $valor): ?string
    {
        $texto = $this->normalizar($valor);
        if (in_array($texto, ['pendiente', 'por pagar', 'no'], true)) {
            return UnidadComisionEmpaque::LIQUIDACION_PENDIENTE;
        }
        if (in_array($texto, ['pagado fuera', 'pagado_fuera', 'pagado', 'constancia', 'si'], true)) {
            return UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA;
        }

        return null;
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));

        return str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $valor);
    }
}
