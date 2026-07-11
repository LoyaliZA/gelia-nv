<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use App\Models\CobranzaBitacora;
use App\Models\User;
use App\Notifications\AlertaCargaReporteCobranzaNotification;
use App\Notifications\AlertasAumentoCreditoMasivoNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportarReporteCobranzaService
{
    public function __construct(
        private ConfirmarPagoCobranzaService $confirmarPago,
        private RecalcularCreditoClienteService $recalcularCredito,
        private SincronizarAlertasOperativasService $sincronizarAlertas,
    ) {}

    public function analizarCreditosNuevos(UploadedFile $archivo): array
    {
        ini_set('auto_detect_line_endings', true);

        [$file, $headers, $delimiter] = $this->abrirArchivoCsv($archivo);

        try {
            if ($this->esFormatoCxc($headers)) {
                return $this->analizarCreditosNuevosCxc($file, $headers, $delimiter);
            }

            return $this->analizarCreditosNuevosLegacy($file, $headers, $delimiter);
        } finally {
            fclose($file);
        }
    }

    public function ejecutar(UploadedFile $archivo, array $fechasInicioPorClave = []): array
    {
        ini_set('auto_detect_line_endings', true);

        [$file, $headers, $delimiter] = $this->abrirArchivoCsv($archivo);

        if ($this->esFormatoCxc($headers)) {
            $resultado = $this->ejecutarFormatoCxc($file, $headers, $delimiter, $fechasInicioPorClave);
        } else {
            $resultado = $this->ejecutarFormatoLegacy($file, $headers, $delimiter, $fechasInicioPorClave);
        }

        fclose($file);

        return $resultado;
    }

    private function analizarCreditosNuevosLegacy($file, array $headers, string $delimiter): array
    {
        $today = now()->toDateString();
        $creditosNuevos = [];

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
            if ($cliente === false) {
                continue;
            }

            if ($cliente && $this->clienteTieneFacturasActivas($cliente->id)) {
                continue;
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

        return $creditosNuevos;
    }

    private function analizarCreditosNuevosCxc($file, array $headers, string $delimiter): array
    {
        $today = now()->toDateString();
        $creditosNuevos = [];
        $clientesEvaluados = [];

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $parsed = $this->parsearFilaCxc($row, $headers);
            if ($parsed === null) {
                continue;
            }

            ['data' => $data, 'nombreCliente' => $nombreCliente, 'folio' => $folio] = $parsed;
            $saldo = $this->limpiarMonto($data['saldo_pendiente'] ?? '0');

            if ($saldo <= 0 || $this->esSaldoAFavor($data)) {
                continue;
            }

            $cliente = $this->resolverCliente(null, $nombreCliente);
            if ($cliente === false) {
                continue;
            }
            $clave = $this->generarClave(null, $nombreCliente, $cliente);

            if (isset($clientesEvaluados[$clave])) {
                $clientesEvaluados[$clave]['monto'] += $saldo;
                continue;
            }

            if ($cliente && $this->clienteTieneFacturasActivas($cliente->id)) {
                continue;
            }

            $clientesEvaluados[$clave] = [
                'clave' => $clave,
                'numero_cliente' => $cliente?->numero_cliente ?? $clave,
                'nombre' => $cliente?->nombre ?? ($nombreCliente ?: 'Cliente Importado'),
                'monto' => $saldo,
                'fecha_inicio_sugerida' => $this->parsearFecha($data['fecha_emision'] ?? '') ?? $today,
                'es_cliente_nuevo' => $cliente === null,
                'cliente_id' => $cliente?->id,
            ];
        }

        return array_values($clientesEvaluados);
    }

    private function ejecutarFormatoLegacy($file, array $headers, string $delimiter, array $fechasInicioPorClave): array
    {
        $clientesProcesados = [];
        $contadorNuevos = 0;
        $contadorActualizados = 0;
        $contadorCreditosNuevos = 0;
        $alertasDetectadas = [];
        $alertasLimiteExcedidoMasivo = [];
        $clientesNuevosCreditosAnotificados = [];
        $pagosDetectados = [];

        DB::transaction(function () use ($file, $headers, $delimiter, $fechasInicioPorClave, &$clientesProcesados, &$contadorNuevos, &$contadorActualizados, &$contadorCreditosNuevos, &$alertasDetectadas, &$alertasLimiteExcedidoMasivo, &$clientesNuevosCreditosAnotificados, &$pagosDetectados) {
            $today = now()->toDateString();

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                $parsed = $this->parsearFila($row, $headers);
                if ($parsed === null) {
                    continue;
                }

                ['data' => $data, 'clientNum' => $clientNum, 'nombreCliente' => $nombreCliente] = $parsed;
                $consolidado = $this->limpiarMonto($data['consolidado'] ?? '0');
                $cliente = $this->resolverCliente($clientNum, $nombreCliente);
                if ($cliente === false) {
                    continue;
                }
                $clave = $this->generarClave($clientNum, $nombreCliente, $cliente);

                if (!$cliente) {
                    $listaId = $this->resolverListaPublicoGeneral();
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
                    $vencimientoCalculado = Carbon::parse($fechaInicioCredito)->addDays($diasCredito)->toDateString();

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
                        $this->evaluarLimiteCliente($cliente, $facturaActiva, $consolidado, $today, $alertasLimiteExcedidoMasivo);
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
                            $extra->update(['pagada' => true]);
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
                            $this->procesarAumentoLegacy(
                                $cliente,
                                $facturaActiva,
                                $montoAnterior,
                                $consolidado,
                                $today,
                                $alertasDetectadas,
                                $alertasLimiteExcedidoMasivo
                            );
                        }

                        $this->recalcularCredito->ejecutar(
                            $cliente->fresh(),
                            auth()->id(),
                            'importacion',
                            false
                        );
                    }
                    $contadorActualizados++;
                } else {
                    $this->liquidarCreditoDesdeImport(
                        $cliente,
                        'Saldo consolidado $0 detectado en importación.',
                        $pagosDetectados,
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
                    'Cliente ausente en el reporte importado; pago liquidado automáticamente.',
                    $pagosDetectados,
                );
                $contadorActualizados++;
            }
        });

        return $this->finalizarImportacion(
            $clientesProcesados,
            $contadorNuevos,
            $contadorActualizados,
            $contadorCreditosNuevos,
            $clientesNuevosCreditosAnotificados,
            $alertasDetectadas,
            $alertasLimiteExcedidoMasivo,
            $pagosDetectados,
        );
    }

    private function ejecutarFormatoCxc($file, array $headers, string $delimiter, array $fechasInicioPorClave): array
    {
        $clientesProcesados = [];
        $foliosEnReporte = [];
        $contadorNuevos = 0;
        $contadorActualizados = 0;
        $contadorCreditosNuevos = 0;
        $alertasDetectadas = [];
        $alertasLimiteExcedidoMasivo = [];
        $clientesNuevosCreditosAnotificados = [];
        $pagosDetectados = [];
        $creditosActivosPrevios = [];

        DB::transaction(function () use ($file, $headers, $delimiter, $fechasInicioPorClave, &$clientesProcesados, &$foliosEnReporte, &$contadorNuevos, &$contadorActualizados, &$contadorCreditosNuevos, &$alertasDetectadas, &$alertasLimiteExcedidoMasivo, &$clientesNuevosCreditosAnotificados, &$pagosDetectados, &$creditosActivosPrevios) {
            $today = now()->toDateString();
            $filas = [];

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                $parsed = $this->parsearFilaCxc($row, $headers);
                if ($parsed === null) {
                    continue;
                }
                $filas[] = $parsed;
            }

            foreach ($filas as $parsed) {
                ['data' => $data, 'nombreCliente' => $nombreCliente, 'folio' => $folio] = $parsed;
                $foliosEnReporte[] = $folio;

                $saldo = $this->limpiarMonto($data['saldo_pendiente'] ?? '0');
                $importeOriginal = $this->limpiarMonto($data['importe_original'] ?? '0');
                $fechaEmision = $this->parsearFecha($data['fecha_emision'] ?? '') ?? $today;
                $fechaVencimiento = $this->parsearFecha($data['fecha_vencimiento'] ?? '') ?? $fechaEmision;
                $noVenta = trim($data['no._venta'] ?? $data['no_venta'] ?? '');

                $cliente = $this->resolverCliente(null, $nombreCliente);
                $clave = $this->generarClave(null, $nombreCliente, $cliente);

                if ($cliente === false) {
                    continue;
                }

                if (!$cliente) {
                    $listaId = $this->resolverListaPublicoGeneral();
                    $cliente = Cliente::create([
                        'numero_cliente' => 'TEMP-' . uniqid(),
                        'nombre' => $nombreCliente ?: 'Cliente Importado',
                        'fecha_inicio_credito' => $fechasInicioPorClave[$clave] ?? $fechaEmision,
                        'lista_actual_id' => $listaId,
                    ]);
                    $contadorNuevos++;
                }

                if (!in_array($cliente->id, $clientesProcesados, true)) {
                    $clientesProcesados[] = $cliente->id;
                }

                if (!array_key_exists($cliente->id, $creditosActivosPrevios)) {
                    $creditosActivosPrevios[$cliente->id] = $this->clienteTieneFacturasActivas($cliente->id);
                    $this->liquidarFoliosLegacyDelCliente($cliente->id);
                }

                $facturaExistente = CobranzaFactura::where('folio', $folio)->first();
                $teniaFacturasActivas = $creditosActivosPrevios[$cliente->id];

                if ($saldo <= 0 || $this->esSaldoAFavor($data)) {
                    if ($facturaExistente && !$facturaExistente->pagada) {
                        $resultadoPago = $this->confirmarPago->marcarPagadaDesdeImport(
                            $facturaExistente,
                            'Saldo pendiente $0 o saldo a favor detectado en importación CXC (folio {folio}).',
                            auth()->id()
                        );
                        $this->registrarPagoDetectado($pagosDetectados, $cliente, $resultadoPago);
                    }
                    $contadorActualizados++;
                    continue;
                }

                $montoAnterior = $facturaExistente ? (float) $facturaExistente->monto : 0.0;
                $esNuevoFolio = $facturaExistente === null;
                $tieneAbonoPorMontoAnterior = !$esNuevoFolio && $montoAnterior > 0 && $saldo < $montoAnterior;

                $nuevoTieneAbono = $importeOriginal > $saldo;
                $factura = CobranzaFactura::updateOrCreate(
                    ['folio' => $folio],
                    [
                        'cliente_id' => $cliente->id,
                        'monto' => $saldo,
                        'fecha_emision' => $fechaEmision,
                        'fecha_vencimiento' => $fechaVencimiento,
                        'pagada' => false,
                        'pago_pendiente_confirmacion' => false,
                        'detectado_en_import_at' => null,
                        'tiene_abono' => $nuevoTieneAbono,
                    ]
                );

                if (!$teniaFacturasActivas) {
                    $fechaInicio = $fechasInicioPorClave[$clave] ?? $fechaEmision;
                    $cliente->update(['fecha_inicio_credito' => $fechaInicio]);

                    CobranzaBitacora::create([
                        'cliente_id' => $cliente->id,
                        'usuario_id' => auth()->id() ?? 1,
                        'tipo_evento' => 'inicio_credito',
                        'monto_anterior' => 0,
                        'monto_nuevo' => $saldo,
                        'descripcion' => 'Inicio de nuevo crédito detectado por importación CXC. Folio: ' . $folio . '.',
                    ]);

                    $clientesNuevosCreditosAnotificados[] = [
                        'cliente' => $cliente->fresh(),
                        'monto' => $saldo,
                    ];
                    $contadorCreditosNuevos++;
                }

                $abonoDesdeImporteOriginal = $esNuevoFolio && $importeOriginal > $saldo;
                if ($tieneAbonoPorMontoAnterior || $abonoDesdeImporteOriginal) {
                    CobranzaBitacora::create([
                        'cliente_id' => $cliente->id,
                        'usuario_id' => auth()->id() ?? 1,
                        'tipo_evento' => 'abono',
                        'monto_anterior' => $tieneAbonoPorMontoAnterior ? $montoAnterior : $importeOriginal,
                        'monto_nuevo' => $saldo,
                        'descripcion' => 'Abono parcial detectado en folio ' . $folio . ($noVenta ? " (venta {$noVenta})." : '.'),
                        'es_alerta' => false,
                    ]);
                } elseif (!$esNuevoFolio && $saldo > $montoAnterior) {
                    $this->procesarAumentoLegacy(
                        $cliente,
                        $factura,
                        $montoAnterior,
                        $saldo,
                        $today,
                        $alertasDetectadas,
                        $alertasLimiteExcedidoMasivo
                    );
                }

                $this->sincronizarFechaInicioCreditoCliente($cliente);
                $clienteRefrescado = $cliente->fresh();
                $saldoTotal = $this->saldoTotalCliente($clienteRefrescado->id);
                $this->evaluarLimiteCliente($clienteRefrescado, $factura, $saldoTotal, $today, $alertasLimiteExcedidoMasivo);

                $limiteFinal = (float) $clienteRefrescado->monto_credito_autorizado;
                if ($limiteFinal > 0 && $saldoTotal <= $limiteFinal) {
                    \App\Models\CobranzaAlerta::where('cliente_id', $clienteRefrescado->id)
                        ->where('tipo', 'limite_superado')
                        ->where('estado', '!=', 'resuelta')
                        ->update(['estado' => 'resuelta']);
                }

                $contadorActualizados++;
            }

            $facturasActivas = CobranzaFactura::query()
                ->where('pagada', false)
                ->where('monto', '>', 0)
                ->get();

            foreach ($facturasActivas as $facturaActiva) {
                if (in_array($facturaActiva->folio, $foliosEnReporte, true)) {
                    continue;
                }

                if (str_starts_with($facturaActiva->folio, 'COB-')
                    && in_array($facturaActiva->cliente_id, $clientesProcesados, true)) {
                    continue;
                }

                $facturaActiva->loadMissing('cliente');
                $resultadoPago = $this->confirmarPago->marcarPagadaDesdeImport(
                    $facturaActiva,
                    'Folio ausente en reporte CXC; pago liquidado automáticamente (folio {folio}).',
                    auth()->id()
                );
                $this->registrarPagoDetectado($pagosDetectados, $facturaActiva->cliente, $resultadoPago);
                $contadorActualizados++;
            }
        });

        return $this->finalizarImportacion(
            $clientesProcesados,
            $contadorNuevos,
            $contadorActualizados,
            $contadorCreditosNuevos,
            $clientesNuevosCreditosAnotificados,
            $alertasDetectadas,
            $alertasLimiteExcedidoMasivo,
            $pagosDetectados,
        );
    }

    private function finalizarImportacion(
        array $clientesProcesados,
        int $contadorNuevos,
        int $contadorActualizados,
        int $contadorCreditosNuevos,
        array $clientesNuevosCreditosAnotificados,
        array $alertasDetectadas,
        array $alertasLimiteExcedidoMasivo,
        array $pagosDetectados = [],
    ): array {
        $this->sincronizarAlertas->ejecutar();

        $alertasLimiteExcedidoMasivo = $this->filtrarAlertasLimiteVigentes($alertasLimiteExcedidoMasivo);

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

        $this->confirmarPago->notificarPagosDetectadosMasivo($pagosDetectados);

        $resultado = [
            'procesados' => count(array_unique($clientesProcesados)),
            'nuevos' => $contadorNuevos,
            'actualizados' => $contadorActualizados,
            'creditos_nuevos' => $contadorCreditosNuevos,
            'creditos_nuevos_detalle' => $clientesNuevosCreditosAnotificados,
            'pagos_detectados' => count($pagosDetectados),
        ];

        $this->enviarAlertaCarga($resultado);

        return $resultado;
    }

    /**
     * Elimina del lote de notificación clientes que ya no superan su límite
     * (p. ej. detectados antes de registrar un abono en la misma importación).
     *
     * @param  array<int, array{cliente: Cliente, monto_actual: float, limite: float}>  $alertasLimiteExcedidoMasivo
     * @return array<int, array{cliente: Cliente, monto_actual: float, limite: float}>
     */
    private function filtrarAlertasLimiteVigentes(array $alertasLimiteExcedidoMasivo): array
    {
        return array_values(array_filter(array_map(function (array $alerta) {
            $cliente = $alerta['cliente'] instanceof Cliente
                ? $alerta['cliente']->fresh()
                : Cliente::find($alerta['cliente']->id ?? null);

            if (!$cliente) {
                return null;
            }

            $limite = (float) $cliente->monto_credito_autorizado;
            $consolidado = $this->saldoTotalCliente($cliente->id);

            if ($limite <= 0 || $consolidado <= $limite) {
                return null;
            }

            return [
                'cliente' => $cliente,
                'monto_actual' => $consolidado,
                'limite' => $limite,
            ];
        }, $alertasLimiteExcedidoMasivo)));
    }

    private function procesarAumentoLegacy(
        Cliente $cliente,
        CobranzaFactura $facturaActiva,
        float $montoAnterior,
        float $consolidado,
        string $today,
        array &$alertasDetectadas,
        array &$alertasLimiteExcedidoMasivo,
    ): void {
        $vencido = Carbon::parse($today)->greaterThan(Carbon::parse($facturaActiva->fecha_vencimiento));
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

            $this->evaluarLimiteCliente($cliente, $facturaActiva, $consolidado, $today, $alertasLimiteExcedidoMasivo);
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

    private function evaluarLimiteCliente(
        Cliente $cliente,
        CobranzaFactura $factura,
        float $montoActual,
        string $today,
        array &$alertasLimiteExcedidoMasivo,
    ): void {
        $limiteFinal = (float) $cliente->monto_credito_autorizado;
        if ($limiteFinal <= 0 || $montoActual <= $limiteFinal) {
            return;
        }

        $alertaPendiente = \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
            ->where('tipo', 'limite_superado')
            ->where('estado', 'pendiente')
            ->first();

        if (!$alertaPendiente) {
            \App\Models\CobranzaAlerta::create([
                'cliente_id' => $cliente->id,
                'factura_id' => $factura->id,
                'tipo' => 'limite_superado',
                'dias_atraso' => null,
                'fecha_alerta' => $today,
                'estado' => 'pendiente',
            ]);
            $alertasLimiteExcedidoMasivo[] = [
                'cliente' => $cliente,
                'monto_actual' => $montoActual,
                'limite' => $limiteFinal,
            ];
        }
    }

    private function sincronizarFechaInicioCreditoCliente(Cliente $cliente): void
    {
        $fechaMinima = CobranzaFactura::query()
            ->where('cliente_id', $cliente->id)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->min('fecha_emision');

        if ($fechaMinima) {
            $cliente->update(['fecha_inicio_credito' => $fechaMinima]);
        }
    }

    private function saldoTotalCliente(int $clienteId): float
    {
        return (float) CobranzaFactura::query()
            ->where('cliente_id', $clienteId)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->sum('monto');
    }

    private function clienteTieneFacturasActivas(int $clienteId): bool
    {
        return CobranzaFactura::query()
            ->where('cliente_id', $clienteId)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->exists();
    }

    private function resolverListaPublicoGeneral(): int
    {
        $listaId = \App\Models\CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->first()?->id;
        if (!$listaId) {
            $lista = \App\Models\CatalogoListaDescuento::firstOrCreate(
                ['nombre' => 'PUBLICO GENERAL'],
                ['monto_requerido' => 0.00, 'activo' => 1]
            );
            $listaId = $lista->id;
        }

        return $listaId;
    }

    private function esFormatoCxc(array $headers): bool
    {
        return in_array('folio', $headers, true)
            && in_array('saldo_pendiente', $headers, true);
    }

    private function esSaldoAFavor(array $data): bool
    {
        $vigencia = mb_strtolower(trim($data['vigencia'] ?? ''));

        return str_contains($vigencia, 'saldo a favor');
    }

    private function parsearFilaCxc(array $row, array $headers): ?array
    {
        $parsed = $this->parsearFila($row, $headers);
        if ($parsed === null) {
            return null;
        }

        $folio = trim((string) ($parsed['data']['folio'] ?? ''));
        if ($folio === '' || stripos($folio, 'total') === 0) {
            return null;
        }

        $parsed['folio'] = $folio;

        return $parsed;
    }

    private function parsearFecha(string $fechaRaw): ?string
    {
        $fechaRaw = trim($fechaRaw);
        if ($fechaRaw === '') {
            return null;
        }

        try {
            return Carbon::parse($fechaRaw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * @return Cliente|null|false null si no existe, false si hay ambigüedad
     */
    private function resolverCliente(?string $clientNum, string $nombreCliente): Cliente|null|false
    {
        if ($clientNum) {
            return Cliente::where('numero_cliente', $clientNum)->first();
        }

        $nombreNormalizado = mb_strtolower(trim($nombreCliente));
        $coincidencias = Cliente::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [$nombreNormalizado])
            ->get();

        if ($coincidencias->count() > 1) {
            return false;
        }

        return $coincidencias->first();
    }

    private function generarClave(?string $clientNum, string $nombreCliente, Cliente|null|false $cliente): string
    {
        if ($cliente instanceof Cliente) {
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

    private function liquidarFoliosLegacyDelCliente(int $clienteId): void
    {
        $foliosLegacy = CobranzaFactura::query()
            ->where('cliente_id', $clienteId)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->where('folio', 'like', 'COB-%')
            ->get();

        foreach ($foliosLegacy as $folioLegacy) {
            $this->confirmarPago->marcarPagadaDesdeImport(
                $folioLegacy,
                'Migración a CXC: consolidado legacy reemplazado por folios del reporte (folio {folio}).',
                auth()->id(),
                notificar: false,
            );
        }
    }

    private function liquidarCreditoDesdeImport(Cliente $cliente, string $motivo, array &$pagosDetectados): void
    {
        $resultado = $this->confirmarPago->liquidarDesdeImport(
            $cliente,
            'Pago liquidado automáticamente por importación. ' . $motivo,
            auth()->id(),
            false,
        );

        $this->registrarPagoDetectado($pagosDetectados, $cliente, $resultado);
    }

    private function registrarPagoDetectado(array &$pagosDetectados, ?Cliente $cliente, array $resultado): void
    {
        if (!($resultado['ok'] ?? false) || !$cliente) {
            return;
        }

        $monto = (float) ($resultado['monto_pagado'] ?? 0);
        if ($monto <= 0) {
            return;
        }

        $clienteId = $cliente->id;
        if (!isset($pagosDetectados[$clienteId])) {
            $pagosDetectados[$clienteId] = [
                'cliente' => $cliente->fresh() ?? $cliente,
                'montoPagado' => 0.0,
            ];
        }

        $pagosDetectados[$clienteId]['montoPagado'] += $monto;
    }
}
