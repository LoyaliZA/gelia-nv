<?php

namespace App\Services\Facturas;

use App\Models\Cliente;
use App\Models\ReceptorFiscal;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarDatosFiscalesMasivoService
{
    public function __construct(
        private ResolverCodigoCatalogoFiscalService $resolverCatalogo,
        private GestionarDatosFiscalesClienteService $gestionar,
    ) {}

    /**
     * @return array{actualizados: int, omitidos: int, errores: list<string>}
     */
    public function ejecutar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo subido.']);
        }

        $stats = ['actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $rows = (new FastExcel)->import($path);

        foreach ($rows as $index => $row) {
            $fila = $this->normalizarHeaders($row);
            if ($this->filaVacia($fila)) {
                continue;
            }

            $numeroFila = $index + 2;
            $numeroCliente = trim((string) ($fila['NUMERO CLIENTE'] ?? ''));
            $ref = $numeroCliente !== '' ? $numeroCliente : '—';

            try {
                if ($numeroCliente === '') {
                    throw ValidationException::withMessages(['numero_cliente' => 'Falta NUMERO CLIENTE.']);
                }

                $cliente = Cliente::query()->where('numero_cliente', $numeroCliente)->first();
                if (! $cliente) {
                    throw ValidationException::withMessages([
                        'numero_cliente' => "No existe el cliente {$numeroCliente}.",
                    ]);
                }

                $patch = $this->mapearCampos($fila);
                if ($patch === []) {
                    $stats['omitidos']++;
                    continue;
                }

                $merge = array_merge([
                    'rfc' => $cliente->rfc,
                    'codigo_postal' => $cliente->codigo_postal,
                    'regimen_fiscal' => $cliente->regimen_fiscal,
                    'correo_electronico' => $cliente->correo_electronico,
                    'uso_factura' => $cliente->uso_factura,
                    'nombre_razon_social' => $cliente->nombre_razon_social,
                    'telefono' => $cliente->telefono,
                ], $patch);

                $merge = ReglasCatalogosFiscales::aplicarForzados($merge);
                $this->validar($merge);
                $this->gestionar->actualizar($cliente, $merge);
                $stats['actualizados']++;
            } catch (ValidationException $e) {
                $mensaje = collect($e->errors())->flatten()->first() ?: $e->getMessage();
                $stats['errores'][] = "Fila {$numeroFila} ({$ref}): {$mensaje}";
            } catch (\Throwable $e) {
                $stats['errores'][] = "Fila {$numeroFila} ({$ref}): {$e->getMessage()}";
            }
        }

        $stats['errores'] = array_slice($stats['errores'], 0, 50);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function mapearCampos(array $fila): array
    {
        $patch = [];

        $rfc = trim((string) ($fila['RFC'] ?? ''));
        if ($rfc !== '') {
            $patch['rfc'] = ReglasCatalogosFiscales::normalizarRfc($rfc);
        }

        $cp = trim((string) ($fila['CODIGO POSTAL'] ?? ''));
        if ($cp !== '') {
            $patch['codigo_postal'] = preg_replace('/\D+/', '', $cp) ?? '';
        }

        $regimenRaw = trim((string) ($fila['REGIMEN FISCAL'] ?? ''));
        if ($regimenRaw !== '') {
            $codigo = $this->resolverCatalogo->regimen($regimenRaw);
            if ($codigo === null) {
                throw ValidationException::withMessages([
                    'regimen_fiscal' => "Régimen fiscal no válido: {$regimenRaw}",
                ]);
            }
            $patch['regimen_fiscal'] = $codigo;
        }

        $correo = trim((string) ($fila['CORREO ELECTRONICO'] ?? ''));
        if ($correo !== '') {
            $patch['correo_electronico'] = mb_strtolower($correo);
        }

        $usoRaw = trim((string) ($fila['USO DE FACTURA'] ?? ''));
        if ($usoRaw !== '') {
            $codigo = $this->resolverCatalogo->uso($usoRaw);
            if ($codigo === null) {
                throw ValidationException::withMessages([
                    'uso_factura' => "Uso de factura no válido: {$usoRaw}",
                ]);
            }
            $patch['uso_factura'] = $codigo;
        }

        $razon = trim((string) ($fila['NOMBRE (RAZON SOCIAL)'] ?? ''));
        if ($razon !== '') {
            $patch['nombre_razon_social'] = ReglasCatalogosFiscales::normalizarRazonSocial($razon);
        }

        $tel = trim((string) ($fila['NUMERO TELEFONICO'] ?? ''));
        if ($tel !== '') {
            $patch['telefono'] = preg_replace('/\D+/', '', $tel) ?? '';
        }

        return $patch;
    }

    /** @param  array<string, mixed>  $datos */
    private function validar(array $datos): void
    {
        $v = Validator::make($datos, [
            'rfc' => ['nullable', 'string', 'max:13'],
            'codigo_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'regimen_fiscal' => ['nullable', 'string', 'max:10', Rule::exists('catalogo_regimen_fiscal', 'codigo')->where('activo', true)],
            'correo_electronico' => ['nullable', 'email:filter', 'max:255'],
            'uso_factura' => ['nullable', 'string', 'max:10', Rule::exists('catalogo_uso_cfdi', 'codigo')->where('activo', true)],
            'nombre_razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^\d{1,10}$/'],
        ]);

        $v->after(function ($validator) use ($datos) {
            if ($err = ReglasCatalogosFiscales::errorRfc($datos['rfc'] ?? null)) {
                $validator->errors()->add('rfc', $err);
            }
            $forzado = ReglasCatalogosFiscales::usoForzadoPorRegimen($datos['regimen_fiscal'] ?? null);
            $uso = (string) ($datos['uso_factura'] ?? '');
            if ($forzado !== null && $uso !== '' && $uso !== $forzado) {
                $validator->errors()->add('uso_factura', 'Con régimen 605 el uso debe ser S01.');
            }
        });

        $v->validate();
    }

    /** @param  array<string, mixed>  $row @return array<string, string> */
    private function normalizarHeaders(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $k)) ?? '', 'UTF-8');
            $out[$key] = is_scalar($v) ? (string) $v : '';
        }

        return $out;
    }

    /** @param  array<string, mixed>  $fila */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
