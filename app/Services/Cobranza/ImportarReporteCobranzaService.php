<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use App\Models\CobranzaBitacora;
use App\Models\User;
use App\Notifications\AlertaCargaReporteCobranzaNotification;
use App\Notifications\AlertasAumentoCreditoMasivoNotification;
use Exception;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportarReporteCobranzaService
{
    public function __construct(
        private ConfirmarPagoCobranzaService $confirmarPago,
    ) {}

    public function analizarCreditosNuevos(UploadedFile $archivo): array
    {
        ini_set('auto_detect_line_endings', true);

        [$file, $headers, $delimiter] = $this->abrirArchivoCsv($archivo);
        $today = now()->toDateString();
        $creditosNuevos = [];

        try {
            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                $parsed = $this->parsearFila($row, $headers);
                if ($parsed === null) {
                    continue;
                }

                ['data' => $data, 'clientNum' => $clientNum, 'nombreCliente' => $nombreCliente] = $parsed;
                $consolidado = $this->limpiarMonto($data['consolidado'] ?? '0');

                if ($consolidado <= 0) {
                    continue;
                }

                $cliente = $this->resolverCliente($clientNum, $nombreCliente);

                if ($cliente) {
                    $facturaActiva = CobranzaFactura::where('cliente_id', $cliente->id)
                        ->where('pagada', false)
                        ->exists();

                    if ($facturaActiva) {
                        continue;
                    }
                }

                $clave = $this->generarClave($clientNum, $nombreCliente, $cliente);

                $creditosNuevos[] = [
                    'clave' => $clave,
                    'numero_cliente' => $cliente?->numero_cliente ?? ($clientNum ?? $clave),
                    'nombre' => $cliente?->nombre ?? ($nombreCliente ?: 'Cliente Importado'),
                    'monto' => $consolidado,
                    'fecha_inicio_sugerida' => $today,
                    'es_cliente_nuevo' => $cliente === null,
                    'cliente_id' => $cliente?->id,
                ];
            }
        } finally {
            fclose($file);
        }

        return $creditosNuevos;
    }

    public function ejecutar(UploadedFile $archivo, array $fechasInicioPorClave = []): array
    {
        ini_set('auto_detect_line_endings', true);

        [$file, $headers, $delimiter] = $this->abrirArchivoCsv($archivo);

        $clientesProcesados = [];
        $contadorNuevos = 0;
        $contadorActualizados = 0;
        $contadorCreditosNuevos = 0;
        $alertasDetectadas = [];
        $alertasLimiteExcedidoMasivo = [];
        $clientesNuevosCreditosAnotificados = [];

        DB::transaction(function () use ($file, $headers, $delimiter, $fechasInicioPorClave, &$clientesProcesados, &$contadorNuevos, &$contadorActualizados, &$contadorCreditosNuevos, &$alertasDetectadas, &$alertasLimiteExcedidoMasivo, &$clientesNuevosCreditosAnotificados) {
            $today = now()->toDateString();

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                $parsed = $this->parsearFila($row, $headers);
                if ($parsed === null) {
                    continue;
                }

                ['data' => $data, 'clientNum' => $clientNum, 'nombreCliente' => $nombreCliente] = $parsed;
                $consolidado = $this->limpiarMonto($data['consolidado'] ?? '0');
                $cliente = $this->resolverCliente($clientNum, $nombreCliente);
                $clave = $this->generarClave($clientNum, $nombreCliente, $cliente);

                if (!$cliente) {
                    $listaId = \App\Models\CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->first()?->id;
                    if (!$listaId) {
                        $lista = \App\Models\CatalogoListaDescuento::firstOrCreate(
                            ['nombre' => 'PUBLICO GENERAL'],
                            ['monto_requerido' => 0.00, 'activo' => 1]
                        );
                        $listaId = $lista->id;
                    }

                    $fechaInicioNuevo = $consolidado > 0
                        ? ($fechasInicioPorClave[$clave] ?? $today)
                        : null;

                    $cliente = Cliente::create([
                        'numero_cliente' => $clientNum ?? ('TEMP-' . uniqid()),
                        'nombre' => $nombreCliente ?: 'Cliente Importado',
                        'fecha_inicio_credito' => $fechaInicioNuevo,
                        'lista_actual_id' => $listaId,
                    ]);
                    $contadorNuevos++;
                }

                $clientesProcesados[] = $cliente->id;

                if ($consolidado > 0) {
                    $facturaActiva = CobranzaFactura::where('cliente_id', $cliente->id)
                        ->where('pagada', false)
                        ->first();

                    if (!$facturaActiva) {
                        $fechaInicioCredito = $fechasInicioPorClave[$clave] ?? $today;
                        $cliente->update(['fecha_inicio_credito' => $fechaInicioCredito]);
                        $cliente->refresh();
                    } elseif (null === $cliente->fecha_inicio_credito) {
                        $cliente->update(['fecha_inicio_credito' => $today]);
                        $cliente->refresh();
                    }

                    $diasCredito = ($cliente->dias_credito > 0) ? $cliente->dias_credito : 30;
                    $fechaInicioCredito = $cliente->fecha_inicio_credito ? $cliente->fecha_inicio_credito->toDateString() : $today;
                    $vencimientoCalculado = \Carbon\Carbon::parse($fechaInicioCredito)->addDays($diasCredito)->toDateString();

                    if (!$facturaActiva) {
                        $facturaActiva = CobranzaFactura::create([
                            'cliente_id' => $cliente->id,
                            'folio' => 'COB-' . $cliente->numero_cliente . '-' . date('Ymd'),
                            'monto' => $consolidado,
                            'fecha_emision' => $fechaInicioCredito,
                            'fecha_vencimiento' => $vencimientoCalculado,
                            'pagada' => false,
                        ]);

                        CobranzaBitacora::create([
                            'cliente_id' => $cliente->id,
                            'usuario_id' => auth()->id() ?? 1,
                            'tipo_evento' => 'inicio_credito',
                            'monto_anterior' => 0,
                            'monto_nuevo' => $consolidado,
                            'descripcion' => 'Inicio de nuevo crédito detectado por importación. Fecha inicio: ' . $fechaInicioCredito . '.',
                        ]);

                        $clientesNuevosCreditosAnotificados[] = [
                            'cliente' => $cliente,
                            'monto' => $consolidado,
                        ];

                        $contadorCreditosNuevos++;

                        $limiteFinal = (float) $cliente->monto_credito_autorizado;
                        if ($limiteFinal > 0 && $consolidado > $limiteFinal) {
                            $alertaPendiente = \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                                ->where('tipo', 'limite_superado')
                                ->where('estado', 'pendiente')
                                ->first();

                            if (!$alertaPendiente) {
                                \App\Models\CobranzaAlerta::create([
                                    'cliente_id' => $cliente->id,
                                    'factura_id' => $facturaActiva->id,
                                    'tipo' => 'limite_superado',
                                    'dias_atraso' => null,
                                    'fecha_alerta' => $today,
                                    'estado' => 'pendiente',
                                ]);
                                $alertasLimiteExcedidoMasivo[] = [
                                    'cliente' => $cliente,
                                    'monto_actual' => $consolidado,
                                    'limite' => $limiteFinal,
                                ];
                            }
                        }
                    } else {
                        $montoAnterior = $facturaActiva->monto;

                        $facturaActiva->update([
                            'monto' => $consolidado,
                            'fecha_vencimiento' => $vencimientoCalculado,
                            'pago_pendiente_confirmacion' => false,
                            'detectado_en_import_at' => null,
                        ]);

                        $facturasExtra = CobranzaFactura::where('cliente_id', $cliente->id)
                            ->where('pagada', false)
                            ->where('id', '!=', $facturaActiva->id)
                            ->get();
                        foreach ($facturasExtra as $extra) {
                            $extra->update(['pagada' => true, 'tiene_abono' => true]);
                        }

                        if ($consolidado < $montoAnterior) {
                            $facturaActiva->update(['tiene_abono' => true]);
                            CobranzaBitacora::create([
                                'cliente_id' => $cliente->id,
                                'usuario_id' => auth()->id() ?? 1,
                                'tipo_evento' => 'abono',
                                'monto_anterior' => $montoAnterior,
                                'monto_nuevo' => $consolidado,
                                'descripcion' => 'Abono parcial detectado.',
                                'es_alerta' => false,
                            ]);

                            $limiteFinal = (float) $cliente->monto_credito_autorizado;
                            if ($limiteFinal > 0 && $consolidado <= $limiteFinal) {
                                \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                                    ->where('tipo', 'limite_superado')
                                    ->where('estado', '!=', 'resuelta')
                                    ->update(['estado' => 'resuelta']);
                            }
                        } elseif ($consolidado > $montoAnterior) {
                            $vencido = \Carbon\Carbon::parse($today)->greaterThan(\Carbon\Carbon::parse($facturaActiva->fecha_vencimiento));
                            $limiteFinal = (float) $cliente->monto_credito_autorizado;
                            $limiteSuperado = ($limiteFinal > 0 && $consolidado > $limiteFinal);

                            if ($vencido) {
                                CobranzaBitacora::create([
                                    'cliente_id' => $cliente->id,
                                    'usuario_id' => auth()->id() ?? 1,
                                    'tipo_evento' => 'alerta_aumento',
                                    'monto_anterior' => $montoAnterior,
                                    'monto_nuevo' => $consolidado,
                                    'descripcion' => 'Aumento de crédito detectado con periodo de crédito ya vencido.',
                                    'es_alerta' => true,
                                ]);

                                $cliente->update(['alerta_aumento_credito' => true]);

                                $alertasDetectadas[] = [
                                    'cliente' => $cliente,
                                    'montoAnterior' => $montoAnterior,
                                    'montoNuevo' => $consolidado,
                                ];
                            } elseif ($limiteSuperado) {
                                CobranzaBitacora::create([
                                    'cliente_id' => $cliente->id,
                                    'usuario_id' => auth()->id() ?? 1,
                                    'tipo_evento' => 'alerta_limite',
                                    'monto_anterior' => $montoAnterior,
                                    'monto_nuevo' => $consolidado,
                                    'descripcion' => 'Aumento detectado que supera el límite de crédito.',
                                    'es_alerta' => true,
                                ]);

                                $alertaPendiente = \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                                    ->where('tipo', 'limite_superado')
                                    ->where('estado', '!=', 'resuelta')
                                    ->first();

                                if (!$alertaPendiente) {
                                    \App\Models\CobranzaAlerta::create([
                                        'cliente_id' => $cliente->id,
                                        'factura_id' => $facturaActiva->id,
                                        'tipo' => 'limite_superado',
                                        'dias_atraso' => null,
                                        'fecha_alerta' => $today,
                                        'estado' => 'pendiente',
                                    ]);

                                    $alertasLimiteExcedidoMasivo[] = [
                                        'cliente' => $cliente,
                                        'monto_actual' => $consolidado,
                                        'limite' => $limiteFinal,
                                    ];
                                }
                            } else {
                                CobranzaBitacora::create([
                                    'cliente_id' => $cliente->id,
                                    'usuario_id' => auth()->id() ?? 1,
                                    'tipo_evento' => 'aumento_credito',
                                    'monto_anterior' => $montoAnterior,
                                    'monto_nuevo' => $consolidado,
                                    'descripcion' => 'Aumento de crédito normal detectado dentro del periodo de crédito.',
                                    'es_alerta' => false,
                                ]);
                            }
                        }
                    }
                    $contadorActualizados++;
                } else {
                    $this->liquidarCreditoDesdeImport(
                        $cliente,
                        'Saldo consolidado $0 detectado en importación.'
                    );
                    $contadorActualizados++;
                }
            }

            $clientesFaltantes = Cliente::whereHas('facturasCobranza', function ($q) {
                $q->where('pagada', false);
            })
                ->whereNotIn('id', $clientesProcesados)
                ->get();

            foreach ($clientesFaltantes as $clienteFaltante) {
                $this->liquidarCreditoDesdeImport(
                    $clienteFaltante,
                    'Cliente ausente en el reporte importado; pago liquidado automáticamente.'
                );
                $contadorActualizados++;
            }
        });

        if (!empty($alertasDetectadas) || !empty($alertasLimiteExcedidoMasivo)) {
            $usuariosParaNotificar = User::permission('cobranza.recibir_alertas')->get();
            $superAdmins = User::role('Super Admin')->get();
            $todosLosUsuarios = $usuariosParaNotificar->merge($superAdmins)->unique('id');

            if ($todosLosUsuarios->isNotEmpty()) {
                if (!empty($alertasDetectadas)) {
                    Notification::send($todosLosUsuarios, new AlertasAumentoCreditoMasivoNotification($alertasDetectadas));
                }
                if (!empty($alertasLimiteExcedidoMasivo)) {
                    Notification::send($todosLosUsuarios, new \App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification($alertasLimiteExcedidoMasivo));
                }
            }
        }

        if (!empty($clientesNuevosCreditosAnotificados)) {
            $configUsersPagos = \Illuminate\Support\Facades\Cache::rememberForever('cobranza_config_users_pagos', function () {
                $config = \App\Models\CobranzaConfiguracion::where('llave', 'notified_users_pagos')->first();
                return $config && $config->valor ? json_decode($config->valor, true) : [];
            });

            if (!empty($configUsersPagos)) {
                $usuariosPago = User::whereIn('id', $configUsersPagos)->get();
                if ($usuariosPago->isNotEmpty()) {
                    Notification::send($usuariosPago, new \App\Notifications\AlertaNuevoCreditoMasivoNotification($clientesNuevosCreditosAnotificados));
                }
            }
        }

        $resultado = [
            'procesados' => count($clientesProcesados),
            'nuevos' => $contadorNuevos,
            'actualizados' => $contadorActualizados,
            'creditos_nuevos' => $contadorCreditosNuevos,
            'creditos_nuevos_detalle' => $clientesNuevosCreditosAnotificados,
        ];

        $this->enviarAlertaCarga($resultado);

        fclose($file);

        return $resultado;
    }

    private function enviarAlertaCarga(array $resultado): void
    {
        $configUsersCarga = \Illuminate\Support\Facades\Cache::rememberForever('cobranza_config_users_carga', function () {
            $config = \App\Models\CobranzaConfiguracion::where('llave', 'notified_users_carga')->first();
            return $config && $config->valor ? json_decode($config->valor, true) : [];
        });

        if (empty($configUsersCarga)) {
            return;
        }

        $usuariosCarga = User::whereIn('id', $configUsersCarga)->get();
        if ($usuariosCarga->isEmpty()) {
            return;
        }

        Notification::send($usuariosCarga, new AlertaCargaReporteCobranzaNotification(
            $resultado,
            auth()->user(),
        ));
    }

    /**
     * @return array{0: resource, 1: array, 2: string}
     */
    private function abrirArchivoCsv(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        $delimiter = $this->detectarDelimitador($path);
        $file = fopen($path, 'r');

        $headersRaw = null;
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }
            $firstCol = preg_replace('/^\xEF\xBB\xBF/', '', trim($row[0]));
            if (strtolower($firstCol) === 'cliente') {
                $headersRaw = $row;
                break;
            }
        }

        if (!$headersRaw) {
            fclose($file);
            throw new Exception("No se encontró la cabecera 'Cliente' en el archivo.");
        }

        return [$file, $this->procesarCabeceras($headersRaw), $delimiter];
    }

    private function parsearFila(array $row, array $headers): ?array
    {
        if (empty(array_filter($row))) {
            return null;
        }

        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = $row[$index] ?? '';
        }

        if (!isset($data['cliente'])) {
            return null;
        }

        $clienteVal = trim($data['cliente']);

        if (stripos($clienteVal, 'total') === 0 || empty($clienteVal)) {
            return null;
        }

        preg_match('/^\d+/', $clienteVal, $matches);
        $clientNum = $matches[0] ?? null;
        $nombreCliente = $clientNum
            ? trim(preg_replace('/^\d+\s*[-_]?\s*/', '', $clienteVal))
            : $clienteVal;

        return [
            'data' => $data,
            'clientNum' => $clientNum,
            'nombreCliente' => $nombreCliente,
        ];
    }

    private function resolverCliente(?string $clientNum, string $nombreCliente): ?Cliente
    {
        if (!$clientNum) {
            return Cliente::where('nombre', $nombreCliente)->first();
        }

        return Cliente::where('numero_cliente', $clientNum)->first();
    }

    private function generarClave(?string $clientNum, string $nombreCliente, ?Cliente $cliente): string
    {
        if ($cliente) {
            return (string) $cliente->numero_cliente;
        }

        if ($clientNum) {
            return $clientNum;
        }

        return 'nombre:' . md5(mb_strtolower(trim($nombreCliente)));
    }

    private function detectarDelimitador(string $path): string
    {
        $file = fopen($path, 'r');
        if (!$file) {
            return ',';
        }
        $line = fgets($file);
        $line2 = fgets($file);
        fclose($file);

        $texto = $line . ($line2 ?: '');
        if (empty($texto)) {
            return ',';
        }

        $delimitadores = [',' => 0, ';' => 0, "\t" => 0];
        foreach ($delimitadores as $delim => &$count) {
            $count = substr_count($texto, $delim);
        }

        arsort($delimitadores);
        return key($delimitadores) ?: ',';
    }

    private function procesarCabeceras(array $headersRaw): array
    {
        $headersRaw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headersRaw[0]);

        return array_map(function ($header) {
            $h = strtolower(trim($header));
            $h = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', ' ', 'ñ'],
                ['a', 'e', 'i', 'o', 'u', '_', 'n'],
                $h
            );
            return $h;
        }, $headersRaw);
    }

    private function limpiarMonto(string $montoRaw): float
    {
        $montoLimpio = trim(str_replace(['$', ',', ' '], '', $montoRaw));
        if ($montoLimpio === '-' || $montoLimpio === '') {
            return 0.00;
        }
        if (str_starts_with($montoLimpio, '(') && str_ends_with($montoLimpio, ')')) {
            $montoLimpio = '-' . substr($montoLimpio, 1, -1);
        }
        return is_numeric($montoLimpio) ? (float) $montoLimpio : 0.00;
    }

    private function liquidarCreditoDesdeImport(Cliente $cliente, string $motivo): void
    {
        $this->confirmarPago->liquidarDesdeImport(
            $cliente,
            'Pago liquidado automáticamente por importación. ' . $motivo,
            auth()->id(),
        );
    }
}
