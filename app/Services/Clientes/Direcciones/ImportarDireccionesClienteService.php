<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Services\Almacenes\ReporteErroresImportacionService;
use App\Support\Clientes\Direcciones\ReglasValidacionDireccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportarDireccionesClienteService
{
    public const HEADERS = [
        'numero_cliente',
        'es_principal',
        'nombre_destinatario',
        'telefono_destinatario',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'ciudad',
        'estado',
        'pais',
        'referencias',
        'indicaciones_entrega',
        'etiqueta',
        'tipo_direccion',
        'domicilio_irregular',
        'anexa_remision',
    ];

    public function __construct(
        private GestionDireccionesClienteService $gestion,
        private NormalizadorDireccion $normalizador,
        private DetectorDireccionDuplicada $detector,
        private ReporteErroresImportacionService $reporte,
    ) {}

    public function descargarPlantilla(): StreamedResponse
    {
        $filename = 'plantilla_direcciones_clientes.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);
            fputcsv($out, [
                '8699',
                '1',
                'Ana Pérez',
                '5512345678',
                'Av. Reforma',
                '100',
                '',
                'Centro',
                '06000',
                'Cuauhtémoc',
                'CDMX',
                'CDMX',
                'México',
                '',
                '',
                'Casa',
                'envio',
                '0',
                '1',
            ]);
            fputcsv($out, [
                '8699',
                '0',
                'Luis Gómez',
                '5598765432',
                '',
                '',
                '',
                '',
                '',
                'Xochimilco',
                '',
                'CDMX',
                'México',
                'Domicilio conocido frente a la iglesia, portón azul',
                'Llamar al llegar',
                'Bodega',
                'envio',
                '1',
                '0',
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{importados: int, actualizados: int, omitidos: int, errores: list<string>, errores_detalle: list<string>, reporte_url: ?string}
     */
    public function ejecutar(UploadedFile $archivo, ?int $usuarioId = null): array
    {
        $path = $archivo->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo subido.',
            ]);
        }

        $stats = [
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $rows = (new FastExcel)->import($path);
        $clientesCache = [];

        foreach ($rows as $index => $row) {
            $fila = $this->normalizarFila($row);
            if ($this->filaVacia($fila)) {
                continue;
            }

            $numeroFila = $index + 2;
            $referencia = (string) ($fila['numero_cliente'] ?? '—');

            try {
                $resultado = $this->importarFila($fila, $usuarioId, $clientesCache);
                if ($resultado === 'importada' || $resultado === 'principal_como_adicional') {
                    $stats['importados']++;
                    if ($resultado === 'principal_como_adicional') {
                        $aviso = 'Ya hay principal; se creó como adicional.';
                        $this->reporte->agregar($numeroFila, $referencia, 'es_principal', $aviso);
                        $stats['errores'][] = "Fila {$numeroFila}: {$aviso}";
                    }
                } else {
                    $stats['omitidos']++;
                    $mensaje = match ($resultado) {
                        'cliente_inexistente' => 'Cliente no encontrado.',
                        'duplicada' => 'Dirección duplicada (ya existe).',
                        default => 'Fila omitida.',
                    };
                    $campo = $resultado === 'duplicada' ? 'direccion' : 'numero_cliente';
                    $this->reporte->agregar($numeroFila, $referencia, $campo, $mensaje);
                    $stats['errores'][] = "Fila {$numeroFila}: {$mensaje}";
                }
            } catch (\Throwable $e) {
                $stats['omitidos']++;
                $mensaje = $e->getMessage();
                $this->reporte->agregar($numeroFila, $referencia, 'general', $mensaje);
                $stats['errores'][] = "Fila {$numeroFila}: {$mensaje}";
            }
        }

        $token = $this->reporte->generarCsv();
        $stats['reporte_url'] = $token
            ? route('almacenes.importaciones.reporte_errores', ['token' => $token])
            : null;
        $stats['errores_detalle'] = $this->reporte->resumen();

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, Cliente|null>  $clientesCache
     */
    private function importarFila(array $fila, ?int $usuarioId, array &$clientesCache): string
    {
        $numeroCliente = trim((string) ($fila['numero_cliente'] ?? ''));
        if ($numeroCliente === '') {
            throw new \InvalidArgumentException('numero_cliente es requerido.');
        }

        if (! array_key_exists($numeroCliente, $clientesCache)) {
            $clientesCache[$numeroCliente] = Cliente::query()
                ->where('numero_cliente', $numeroCliente)
                ->first();
        }

        $cliente = $clientesCache[$numeroCliente];
        if (! $cliente) {
            return 'cliente_inexistente';
        }

        $irregular = $this->parseBool($fila['domicilio_irregular'] ?? false);
        $quierePrincipal = $this->parseBool($fila['es_principal'] ?? false);

        $datos = $this->normalizador->ejecutar([
            'nombre_destinatario' => $fila['nombre_destinatario'] ?? '',
            'telefono_destinatario' => $fila['telefono_destinatario'] ?? null,
            'calle' => $fila['calle'] ?? null,
            'numero_exterior' => $fila['numero_exterior'] ?? null,
            'numero_interior' => $fila['numero_interior'] ?? null,
            'colonia' => $fila['colonia'] ?? null,
            'codigo_postal' => $fila['codigo_postal'] ?? null,
            'municipio' => $fila['municipio'] ?? null,
            'ciudad' => $fila['ciudad'] ?? null,
            'estado' => $fila['estado'] ?? null,
            'pais' => ($fila['pais'] ?? null) ?: 'México',
            'referencias' => $fila['referencias'] ?? null,
            'indicaciones_entrega' => $fila['indicaciones_entrega'] ?? null,
            'etiqueta' => $fila['etiqueta'] ?? null,
            'tipo_direccion' => ($fila['tipo_direccion'] ?? null) ?: ClienteDireccion::TIPO_ENVIO,
            'domicilio_irregular' => $irregular,
            'anexa_remision' => $this->parseBool($fila['anexa_remision'] ?? false),
        ]);

        $validator = Validator::make($datos, ReglasValidacionDireccion::internas($irregular));
        ReglasValidacionDireccion::afterIrregular($validator, $irregular);
        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        if ($this->detector->existe($cliente->id, $datos)) {
            return 'duplicada';
        }

        $ctx = [
            'usuario_id' => $usuarioId,
            'origen' => ClienteDireccion::ORIGEN_IMPORT_CATALOGO,
            'verificar' => true,
        ];

        $tieneActivas = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->activas()
            ->exists();

        $tienePrincipal = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->activas()
            ->where('es_principal', true)
            ->exists();

        if (! $tieneActivas) {
            $this->gestion->crearPrimeraDireccion($cliente->id, $datos, array_merge($ctx, [
                'es_principal' => true,
            ]));

            return 'importada';
        }

        if ($quierePrincipal && ! $tienePrincipal) {
            $this->gestion->crearDireccionAdicional($cliente->id, $datos, array_merge($ctx, [
                'es_principal' => true,
            ]));

            return 'importada';
        }

        if ($quierePrincipal && $tienePrincipal) {
            $this->gestion->crearDireccionAdicional($cliente->id, $datos, array_merge($ctx, [
                'es_principal' => false,
            ]));

            return 'principal_como_adicional';
        }

        $this->gestion->crearDireccionAdicional($cliente->id, $datos, array_merge($ctx, [
            'es_principal' => false,
        ]));

        return 'importada';
    }

    /**
     * @param  array<string|int, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizarFila(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $k = mb_strtolower(trim((string) $key));
            $k = str_replace([' ', '-'], '_', $k);
            $out[$k] = is_string($value) ? trim($value) : $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach (self::HEADERS as $h) {
            $v = $fila[$h] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseBool(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        $v = mb_strtolower(trim((string) $valor));

        return in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'y', 's'], true);
    }
}
