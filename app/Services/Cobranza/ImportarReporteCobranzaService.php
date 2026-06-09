<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use App\Events\AlertaAumentoCreditoEvent;
use App\Models\CobranzaBitacora;
use App\Models\User;
use App\Notifications\AlertaAumentoCreditoNotification;
use App\Notifications\AlertasAumentoCreditoMasivoNotification;
use Exception;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarReporteCobranzaService
{
    public function ejecutar(UploadedFile $archivo): array
    {
        ini_set('auto_detect_line_endings', true);
        
        $path = $archivo->getRealPath();
        $delimiter = $this->detectarDelimitador($path);
        $file = fopen($path, 'r');

        // Buscar fila de cabeceras
        $headersRaw = null;
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }
            // Limpiar el primer elemento de posibles BOM
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

        $headers = $this->procesarCabeceras($headersRaw);

        $clientesProcesados = [];
        $contadorNuevos = 0;
        $contadorActualizados = 0;
        $alertasDetectadas = [];
        $alertasLimiteExcedidoMasivo = [];

        DB::transaction(function () use ($file, $headers, $delimiter, &$clientesProcesados, &$contadorNuevos, &$contadorActualizados, &$alertasDetectadas, &$alertasLimiteExcedidoMasivo) {
            $today = now()->toDateString();

            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }

                // Mapear cabeceras con robustez
                $data = [];
                foreach ($headers as $index => $header) {
                    $data[$header] = $row[$index] ?? '';
                }

                // Si no hay columna "cliente", omitir
                if (!isset($data['cliente'])) {
                    continue;
                }

                $clienteVal = trim($data['cliente']);

                // Ignorar la fila "Total" o vacías
                if (stripos($clienteVal, 'total') === 0 || empty($clienteVal)) {
                    continue;
                }

                // Extraer número de cliente (primer bloque de dígitos)
                preg_match('/^\d+/', $clienteVal, $matches);
                $clientNum = $matches[0] ?? null;

                if (!$clientNum) {
                    // Si no tiene número al inicio, usar el string completo como nombre
                    $nombreCliente = $clienteVal;
                    // Buscar por nombre
                    $cliente = Cliente::where('nombre', $nombreCliente)->first();
                } else {
                    $cliente = Cliente::where('numero_cliente', $clientNum)->first();
                    $nombreCliente = trim(preg_replace('/^\d+\s*[-_]?\s*/', '', $clienteVal));
                }

                // Limpiar montos
                $consolidado = $this->limpiarMonto($data['consolidado'] ?? '0');
                $porVencer = $this->limpiarMonto($data['por_vencer'] ?? '0');
                $de1a30 = $this->limpiarMonto($data['de_1_a_30_dias'] ?? '0');
                $de31a60 = $this->limpiarMonto($data['de_31_a_60_dias'] ?? '0');
                $de61a90 = $this->limpiarMonto($data['de_61_a_90_dias'] ?? '0');
                $de91a120 = $this->limpiarMonto($data['de_91_a_120_dias'] ?? '0');
                $masDe120 = $this->limpiarMonto($data['mas_de_120_dias'] ?? '0');

                $totalOverdue = $de1a30 + $de31a60 + $de61a90 + $de91a120 + $masDe120;

                if (!$cliente) {
                    $listaId = \App\Models\CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->first()?->id;
                    if (!$listaId) {
                        $lista = \App\Models\CatalogoListaDescuento::firstOrCreate(
                            ['nombre' => 'PUBLICO GENERAL'],
                            ['monto_requerido' => 0.00, 'activo' => 1]
                        );
                        $listaId = $lista->id;
                    }

                    // Crear cliente nuevo
                    $cliente = Cliente::create([
                        'numero_cliente' => $clientNum ?? ('TEMP-' . uniqid()),
                        'nombre' => $nombreCliente ?: 'Cliente Importado',
                        'fecha_inicio_credito' => $consolidado > 0 ? $today : null,
                        'lista_actual_id' => $listaId,
                    ]);
                    $contadorNuevos++;
                }

                $clientesProcesados[] = $cliente->id;

                // Lógica de Inicio de Crédito y Factura
                if ($consolidado > 0) {
                    // Apertura de crédito: el CSV solo trae montos, no días ni límite autorizado
                    if (null === $cliente->fecha_inicio_credito) {
                        $cliente->update(['fecha_inicio_credito' => $today]);
                    }

                    // Plazo basado en configuración o 30 por defecto
                    $diasCredito = ($cliente->dias_credito > 0) ? $cliente->dias_credito : 30;
                    $fechaInicioCredito = $cliente->fecha_inicio_credito ? $cliente->fecha_inicio_credito->toDateString() : $today;
                    $vencimientoCalculado = \Carbon\Carbon::parse($fechaInicioCredito)->addDays($diasCredito)->toDateString();

                    // Buscar factura de cobranza activa (impaga)
                    $facturaActiva = CobranzaFactura::where('cliente_id', $cliente->id)
                        ->where('pagada', false)
                        ->first();

                    if (!$facturaActiva) {
                        // Creación de crédito, la fecha_emision es cuando inició, y el vencimiento es estricto a los días
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
                            'descripcion' => 'Inicio de nuevo crédito detectado por importación.',
                        ]);
                        
                        // Validar si inicia ya excedido
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
                                    'limite' => $limiteFinal
                                ];
                            }
                        }

                    } else {
                        $montoAnterior = $facturaActiva->monto;

                        // Actualizamos monto y fecha de vencimiento (por si el usuario cambió los días de crédito)
                        $facturaActiva->update([
                            'monto' => $consolidado,
                            'fecha_vencimiento' => $vencimientoCalculado,
                        ]);

                        if ($consolidado < $montoAnterior) {
                            $facturaActiva->update(['tiene_abono' => true]);
                            // Detección de ABONO parcial
                            CobranzaBitacora::create([
                                'cliente_id' => $cliente->id,
                                'usuario_id' => auth()->id() ?? 1,
                                'tipo_evento' => 'abono',
                                'monto_anterior' => $montoAnterior,
                                'monto_nuevo' => $consolidado,
                                'descripcion' => 'Abono parcial detectado.',
                                'es_alerta' => false,
                            ]);
                        } elseif ($consolidado > $montoAnterior) {
                            // Detección de AUMENTO
                            $vencido = \Carbon\Carbon::parse($today)->greaterThan(\Carbon\Carbon::parse($facturaActiva->fecha_vencimiento));
                            $limiteFinal = (float) $cliente->monto_credito_autorizado;
                            $limiteSuperado = ($limiteFinal > 0 && $consolidado > $limiteFinal);
                            
                            if ($vencido) {
                                // Aumento IRREGULAR (después de fecha vencimiento)
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

                                // Agrupar para correo masivo y notificaciones web
                                $alertasDetectadas[] = [
                                    'cliente' => $cliente,
                                    'montoAnterior' => $montoAnterior,
                                    'montoNuevo' => $consolidado,
                                ];
                            } elseif ($limiteSuperado) {
                                // Aumento con Límite Superado
                                CobranzaBitacora::create([
                                    'cliente_id' => $cliente->id,
                                    'usuario_id' => auth()->id() ?? 1,
                                    'tipo_evento' => 'alerta_limite',
                                    'monto_anterior' => $montoAnterior,
                                    'monto_nuevo' => $consolidado,
                                    'descripcion' => 'Aumento de crédito detectado superando el límite autorizado de $' . number_format($limiteFinal, 2) . '.',
                                    'es_alerta' => true,
                                ]);

                                // Crear alerta pendiente si no existe
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
                                        'limite' => $limiteFinal
                                    ];
                                }
                            } else {
                                // Aumento NORMAL (dentro de días de crédito y no excede límite)
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
                    // Si no tiene saldo activo, marcar facturas activas como pagadas
                    $facturasActivas = CobranzaFactura::where('cliente_id', $cliente->id)->where('pagada', false)->get();
                    if ($facturasActivas->isNotEmpty()) {
                        $montoPagado = $facturasActivas->sum('monto');
                        CobranzaFactura::where('cliente_id', $cliente->id)
                            ->where('pagada', false)
                            ->update(['pagada' => true]);

                        CobranzaBitacora::create([
                            'cliente_id' => $cliente->id,
                            'usuario_id' => auth()->id(),
                            'tipo_evento' => 'pago',
                            'monto_anterior' => $montoPagado,
                            'monto_nuevo' => 0,
                            'descripcion' => 'Crédito liquidado (saldo consolidado $0).',
                        ]);

                        \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                            ->where('estado', 'pendiente')
                            ->delete();
                    }

                    // Limpiar fecha de inicio de crédito y alerta
                    $cliente->update([
                        'fecha_inicio_credito' => null,
                        'alerta_aumento_credito' => false,
                    ]);
                }
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

        fclose($file);

        return [
            'procesados' => count($clientesProcesados),
            'nuevos' => $contadorNuevos,
            'actualizados' => $contadorActualizados,
        ];
    }

    private function detectarDelimitador(string $path): string
    {
        $file = fopen($path, 'r');
        if (!$file) {
            return ',';
        }
        $line = fgets($file); // Primera línea
        $line2 = fgets($file); // Segunda línea
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
            // Remover acentos y espacios
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
        // Soporte para formato de contabilidad negativo: (123.45) -> -123.45
        if (str_starts_with($montoLimpio, '(') && str_ends_with($montoLimpio, ')')) {
            $montoLimpio = '-' . substr($montoLimpio, 1, -1);
        }
        return is_numeric($montoLimpio) ? (float) $montoLimpio : 0.00;
    }
}
