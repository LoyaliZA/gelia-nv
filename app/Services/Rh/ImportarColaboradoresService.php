<?php

namespace App\Services\Rh;

use App\Models\Area;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoTurno;
use App\Models\Departamento;
use App\Models\RhConfiguracion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarColaboradoresService
{
    public function __construct(
        private CrearColaboradorService $crearColaborador,
    ) {}

    /**
     * @return array{importados: int, actualizados: int, omitidos: int, errores: list<string>, errores_detalle: list<string>}
     */
    public function ejecutar(User $registrador, UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo subido.',
            ]);
        }

        $config = RhConfiguracion::obtener();
        $diasPeriodo = max(1, (int) $config->dias_periodo_pago);

        $mapas = $this->cargarMapasCatalogos();

        $stats = [
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
            'errores_detalle' => [],
        ];

        $rows = (new FastExcel)->import($path);

        foreach ($rows as $index => $row) {
            $fila = $this->normalizarFila($row);
            if ($this->filaVacia($fila)) {
                continue;
            }

            $numeroFila = $index + 2;
            $referencia = trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido_paterno'] ?? ''));
            if ($referencia === '') {
                $referencia = '—';
            }

            try {
                $datos = $this->mapearFila($fila, $mapas, $diasPeriodo);
                $this->crearColaborador->ejecutar($registrador, $datos);
                $stats['importados']++;
            } catch (ValidationException $e) {
                $mensaje = collect($e->errors())->flatten()->first() ?: $e->getMessage();
                $this->registrarError($stats, $numeroFila, $referencia, (string) $mensaje);
            } catch (\Throwable $e) {
                $this->registrarError($stats, $numeroFila, $referencia, $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array{departamentos: array<string, Departamento>, areas: array<string, Area>, puestos: array<string, CatalogoPuesto>, turnos: array<string, CatalogoTurno>}  $mapas
     * @return array<string, mixed>
     */
    private function mapearFila(array $fila, array $mapas, int $diasPeriodo): array
    {
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }

        $claveDepto = $this->normalizarClave($fila['departamento'] ?? null);
        if ($claveDepto === null) {
            throw ValidationException::withMessages(['departamento' => 'El departamento es obligatorio.']);
        }

        $departamento = $mapas['departamentos'][$claveDepto] ?? null;
        if (! $departamento) {
            throw ValidationException::withMessages([
                'departamento' => "Departamento no encontrado: «{$fila['departamento']}». Usa un nombre de la hoja Catalogos.",
            ]);
        }

        $areaId = null;
        $claveArea = $this->normalizarClave($fila['area'] ?? null);
        if ($claveArea !== null) {
            $area = $mapas['areas'][$claveDepto . '|' . $claveArea]
                ?? $mapas['areas_por_nombre'][$claveArea]
                ?? null;

            if (! $area) {
                throw ValidationException::withMessages([
                    'area' => "Área no encontrada: «{$fila['area']}».",
                ]);
            }

            if ((int) $area->departamento_id !== (int) $departamento->id) {
                throw ValidationException::withMessages([
                    'area' => "El área «{$area->nombre}» no pertenece al departamento «{$departamento->nombre}».",
                ]);
            }

            $areaId = $area->id;
        }

        $clavePuesto = $this->normalizarClave($fila['puesto'] ?? null);
        if ($clavePuesto === null) {
            throw ValidationException::withMessages(['puesto' => 'El puesto es obligatorio.']);
        }
        $puesto = $mapas['puestos'][$clavePuesto] ?? null;
        if (! $puesto) {
            throw ValidationException::withMessages([
                'puesto' => "Puesto no encontrado: «{$fila['puesto']}». Usa un nombre de la hoja Catalogos.",
            ]);
        }

        $claveTurno = $this->normalizarClave($fila['turno'] ?? null);
        if ($claveTurno === null) {
            throw ValidationException::withMessages(['turno' => 'El turno es obligatorio.']);
        }
        $turno = $mapas['turnos'][$claveTurno] ?? null;
        if (! $turno) {
            throw ValidationException::withMessages([
                'turno' => "Turno no encontrado: «{$fila['turno']}». Usa un nombre de la hoja Catalogos.",
            ]);
        }

        $salarioDiario = $this->parseNumero($fila['salario_diario'] ?? null, 'salario_diario', true);
        $bonoProd = $this->parseNumero($fila['bono_productividad'] ?? null, 'bono_productividad', false) ?? 0;
        $bonoPunt = $this->parseNumero($fila['bono_puntualidad'] ?? null, 'bono_puntualidad', false) ?? 0;
        $horas = $this->parseNumero($fila['horas_laboradas_oficiales'] ?? null, 'horas_laboradas_oficiales', true);

        $datos = [
            'nombre' => $nombre,
            'apellido_paterno' => $this->textoOpcional($fila['apellido_paterno'] ?? null),
            'apellido_materno' => $this->textoOpcional($fila['apellido_materno'] ?? null),
            'departamento_id' => $departamento->id,
            'area_id' => $areaId,
            'catalogo_puesto_id' => $puesto->id,
            'catalogo_turno_id' => $turno->id,
            'salario_base' => round($salarioDiario * $diasPeriodo, 4),
            'bono_productividad' => round($bonoProd * $diasPeriodo, 4),
            'bono_puntualidad' => round($bonoPunt * $diasPeriodo, 4),
            'horas_laboradas_oficiales' => $horas,
            'activo' => $this->parseActivo($fila['activo'] ?? true),
            'bonos' => [],
        ];

        $validator = Validator::make($datos, [
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'departamento_id' => 'required|exists:departamentos,id',
            'area_id' => 'nullable|exists:areas,id',
            'catalogo_puesto_id' => 'required|exists:catalogo_puestos,id',
            'catalogo_turno_id' => 'required|exists:catalogo_turnos,id',
            'salario_base' => 'required|numeric|min:0',
            'bono_productividad' => 'nullable|numeric|min:0',
            'bono_puntualidad' => 'nullable|numeric|min:0',
            'horas_laboradas_oficiales' => 'required|numeric|min:0.5|max:24',
            'activo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * @return array{
     *     departamentos: array<string, Departamento>,
     *     areas: array<string, Area>,
     *     areas_por_nombre: array<string, Area>,
     *     puestos: array<string, CatalogoPuesto>,
     *     turnos: array<string, CatalogoTurno>
     * }
     */
    private function cargarMapasCatalogos(): array
    {
        $departamentos = [];
        foreach (Departamento::query()->where('activo', true)->get() as $depto) {
            $clave = $this->normalizarClave($depto->nombre);
            if ($clave !== null) {
                $departamentos[$clave] = $depto;
            }
        }

        $areas = [];
        $areasPorNombre = [];
        foreach (Area::query()->with('departamento')->get() as $area) {
            if (! $area->departamento || ! $area->departamento->activo) {
                continue;
            }
            $claveArea = $this->normalizarClave($area->nombre);
            $claveDepto = $this->normalizarClave($area->departamento->nombre);
            if ($claveArea === null || $claveDepto === null) {
                continue;
            }
            $areas[$claveDepto . '|' . $claveArea] = $area;
            $areasPorNombre[$claveArea] = $area;
        }

        $puestos = [];
        foreach (CatalogoPuesto::query()->where('activo', true)->get() as $puesto) {
            $clave = $this->normalizarClave($puesto->nombre);
            if ($clave !== null) {
                $puestos[$clave] = $puesto;
            }
        }

        $turnos = [];
        foreach (CatalogoTurno::query()->where('activo', true)->get() as $turno) {
            $clave = $this->normalizarClave($turno->nombre);
            if ($clave !== null) {
                $turnos[$clave] = $turno;
            }
        }

        return [
            'departamentos' => $departamentos,
            'areas' => $areas,
            'areas_por_nombre' => $areasPorNombre,
            'puestos' => $puestos,
            'turnos' => $turnos,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizarFila($row): array
    {
        $fila = [];
        foreach ($row as $key => $value) {
            $clave = $this->normalizarHeader((string) $key);
            if ($clave === null) {
                continue;
            }
            $fila[$clave] = is_string($value) ? trim($value) : $value;
        }

        return $fila;
    }

    private function normalizarHeader(string $header): ?string
    {
        $clave = mb_strtolower(trim($header));
        $clave = str_replace([' ', '-'], '_', $clave);

        $alias = [
            'apellido_paterno' => 'apellido_paterno',
            'apellidopaterno' => 'apellido_paterno',
            'apellido_materno' => 'apellido_materno',
            'apellidomaterno' => 'apellido_materno',
            'departamento' => 'departamento',
            'area' => 'area',
            'área' => 'area',
            'puesto' => 'puesto',
            'turno' => 'turno',
            'salario_diario' => 'salario_diario',
            'salariodiario' => 'salario_diario',
            'bono_productividad' => 'bono_productividad',
            'bono_puntualidad' => 'bono_puntualidad',
            'horas_laboradas_oficiales' => 'horas_laboradas_oficiales',
            'horas' => 'horas_laboradas_oficiales',
            'activo' => 'activo',
            'nombre' => 'nombre',
        ];

        return $alias[$clave] ?? (in_array($clave, PlantillaImportacionColaboradoresService::HEADERS, true) ? $clave : null);
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizarClave(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? ''));

        return $limpio === '' ? null : $limpio;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function parseNumero(mixed $valor, string $campo, bool $requerido): ?float
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            if ($requerido) {
                throw ValidationException::withMessages([$campo => "El campo {$campo} es obligatorio."]);
            }

            return null;
        }

        if (is_string($valor)) {
            $valor = str_replace([',', ' '], ['', ''], $valor);
        }

        if (! is_numeric($valor)) {
            throw ValidationException::withMessages([$campo => "El campo {$campo} debe ser numérico."]);
        }

        return (float) $valor;
    }

    private function parseActivo(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return true;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        if (is_numeric($valor)) {
            return (int) $valor !== 0;
        }

        $texto = mb_strtolower(trim((string) $valor));

        return ! in_array($texto, ['0', 'no', 'false', 'inactivo', 'n'], true);
    }

    /**
     * @param  array{omitidos: int, errores: list<string>, errores_detalle: list<string>}  $stats
     */
    private function registrarError(array &$stats, int $numeroFila, string $referencia, string $mensaje): void
    {
        $stats['omitidos']++;
        $texto = "Fila {$numeroFila} ({$referencia}): {$mensaje}";
        $stats['errores'][] = $texto;
        $stats['errores_detalle'][] = $texto;
    }
}
