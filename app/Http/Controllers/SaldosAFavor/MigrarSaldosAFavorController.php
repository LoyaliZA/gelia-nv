<?php

namespace App\Http\Controllers\SaldosAFavor;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SaldosAFavor\SafMotivo;
use App\Services\SaldosAFavor\AplicarReservaSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MigrarSaldosAFavorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SaldosAFavor/Migrar', [
            'motivos' => SafMotivo::where('activo', true)->orderBy('orden')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function plantilla(): StreamedResponse
    {
        $headers = [
            'numero_cliente',
            'monto_original',
            'monto_aplicado',
            'fecha_generacion',
            'fecha_vencimiento',
            'documento_origen',
            'remision_aplicacion',
            'motivo',
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, [
                '12345',
                '500.00',
                '0',
                '2026-01-15',
                '',
                'DOC-HIST-001',
                '',
                'Migración histórica',
            ]);
            fclose($out);
        }, 'plantilla_saldos_a_favor.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt'],
            'canal_origen' => ['required', 'string', 'max:64'],
        ]);

        $filas = $this->parseCsv($request->file('archivo')->getRealPath());
        $resultado = $this->clasificar($filas, $request->string('canal_origen')->toString());

        return response()->json($resultado);
    }

    public function importar(Request $request, GenerarCreditoSafService $generar, AplicarReservaSafService $aplicar): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt'],
            'canal_origen' => ['required', 'string', 'max:64'],
            'confirmar' => ['required', 'accepted'],
        ]);

        $filas = $this->parseCsv($request->file('archivo')->getRealPath());
        $clasificado = $this->clasificar($filas, $request->string('canal_origen')->toString());
        $motivoMigracion = SafMotivo::where('codigo', 'migracion_historica')->value('id');

        $importados = 0;
        $errores = [];

        DB::transaction(function () use ($clasificado, $generar, $aplicar, $motivoMigracion, $request, &$importados, &$errores) {
            foreach ($clasificado['ok'] as $row) {
                try {
                    $payload = [
                        'cliente_id' => $row['cliente_id'],
                        'monto' => $row['monto_original'],
                        'saf_motivo_id' => $motivoMigracion,
                        'canal_origen' => $request->string('canal_origen')->toString(),
                        'documento_origen' => $row['documento_origen'],
                        'detalle_motivo' => $row['motivo'] ?: 'Migración histórica',
                        'generado_por_id' => Auth::id(),
                        'fecha_generacion' => $row['fecha_generacion'] ?: now(),
                        'observaciones' => 'Importación CSV conciliada',
                        'omitir_monto_minimo' => true,
                    ];
                    if (! empty($row['fecha_vencimiento'])) {
                        $payload['fecha_vencimiento'] = $row['fecha_vencimiento'];
                    }

                    $credito = $generar->handle($payload);

                    $aplicado = round((float) $row['monto_aplicado'], 2);
                    if ($aplicado > 0) {
                        $aplicar->aplicarDirecto($credito->id, min($aplicado, (float) $credito->monto_disponible), Auth::id(), [
                            'observaciones' => 'Aplicación histórica importada',
                            'referencia_externa' => $row['remision_aplicacion'] ?? null,
                        ]);
                    }
                    $importados++;
                } catch (\Throwable $e) {
                    $errores[] = "Fila {$row['fila']}: ".$e->getMessage();
                }
            }
        });

        $msg = "Importados: {$importados}. Excepciones omitidas: ".count($clasificado['excepciones']).'.';
        if ($errores) {
            $msg .= ' Errores: '.implode('; ', array_slice($errores, 0, 5));
        }

        return redirect()->route('saldos_favor.migrar.index')->with('success', $msg);
    }

    /**
     * CSV esperado: numero_cliente,monto_original,monto_aplicado,fecha_generacion,fecha_vencimiento,documento_origen,remision_aplicacion,motivo
     */
    private function parseCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = null;
        $filas = [];
        $n = 0;
        while (($data = fgetcsv($fh)) !== false) {
            $n++;
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $data);
                continue;
            }
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $data[$i] ?? null;
            }
            $row['_fila'] = $n;
            $filas[] = $row;
        }
        fclose($fh);

        return $filas;
    }

    private function clasificar(array $filas, string $canal): array
    {
        $ok = [];
        $excepciones = [];

        foreach ($filas as $row) {
            $numero = preg_replace('/\D+/', '', (string) ($row['numero_cliente'] ?? ''));
            $monto = $this->parseMonto($row['monto_original'] ?? null);
            $aplicado = $this->parseMonto($row['monto_aplicado'] ?? 0) ?? 0;
            $cliente = $numero !== '' ? Cliente::where('numero_cliente', $numero)->first() : null;

            if (! $cliente || $monto === null || $monto <= 0) {
                $excepciones[] = [
                    'fila' => $row['_fila'],
                    'motivo' => ! $cliente ? 'cliente_no_resuelto' : 'monto_invalido',
                    'numero_cliente' => $row['numero_cliente'] ?? null,
                ];
                continue;
            }

            $ok[] = [
                'fila' => $row['_fila'],
                'cliente_id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'monto_original' => $monto,
                'monto_aplicado' => min($aplicado, $monto),
                'fecha_generacion' => $row['fecha_generacion'] ?? null,
                'fecha_vencimiento' => trim((string) ($row['fecha_vencimiento'] ?? '')) ?: null,
                'documento_origen' => $row['documento_origen'] ?? null,
                'remision_aplicacion' => $row['remision_aplicacion'] ?? null,
                'motivo' => $row['motivo'] ?? null,
                'canal_origen' => $canal,
            ];
        }

        return [
            'ok' => $ok,
            'excepciones' => $excepciones,
            'resumen' => [
                'ok' => count($ok),
                'excepciones' => count($excepciones),
                'monto_ok' => round(array_sum(array_column($ok, 'monto_original')), 2),
            ],
        ];
    }

    private function parseMonto(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $s = trim((string) $valor);
        $s = str_replace(['$', ' '], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        if (! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }
}
