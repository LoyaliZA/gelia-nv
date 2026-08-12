<?php

namespace App\Http\Controllers\SaldosAFavor;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SaldosAFavor\SafComprobanteCaja;
use App\Models\SaldosAFavor\SafComprobanteReimpresion;
use App\Models\SaldosAFavor\SafImpresionPreferencia;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\SaldoFavorComprobantePdvNotification;
use App\Services\SaldosAFavor\AplicarReservaSafService;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\LiberarReservaSafService;
use App\Services\SaldosAFavor\ObtenerOCrearCuentaSafService;
use App\Services\SaldosAFavor\ReservarSaldoSafService;
use App\Services\SaldosAFavor\ValidarClienteSafService;
use App\Support\RhReciboAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CajaSaldosAFavorController extends Controller
{
    public function index(ConsultarCuentaClienteSafService $consultar, Request $request): Response
    {
        $cliente = null;
        $cuenta = null;
        if ($request->filled('cliente_id')) {
            $cliente = Cliente::find($request->integer('cliente_id'));
            if ($cliente) {
                $cuenta = $consultar->handle($cliente->id);
            }
        }

        $preferencia = SafImpresionPreferencia::firstOrCreate(
            ['user_id' => Auth::id(), 'terminal_key' => 'default'],
            ['perfil' => '80mm', 'copias' => 1]
        );

        return Inertia::render('SaldosAFavor/Caja', [
            'cliente' => $cliente?->only(['id', 'numero_cliente', 'nombre']),
            'cuenta' => $cuenta ? [
                'disponible' => $cuenta['disponible'],
                'reservado' => $cuenta['reservado'],
                'creditos_usables' => $cuenta['creditos_usables'],
            ] : null,
            'motivos' => SafMotivo::where('activo', true)->orderBy('orden')->get(['id', 'codigo', 'nombre', 'categoria', 'requiere_detalle']),
            'sucursales' => Sucursal::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'preferencia' => $preferencia,
            'comprobantes_recientes' => SafComprobanteCaja::with('cliente:id,numero_cliente,nombre')
                ->where('generado_por_id', Auth::id())
                ->whereDate('created_at', now()->toDateString())
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function generarCredito(Request $request, GenerarCreditoSafService $service): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'saf_motivo_id' => ['required', 'exists:saf_motivos,id'],
            'detalle_motivo' => ['nullable', 'string', 'max:2000'],
            'documento_origen' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'evidencias' => ['required', 'array', 'min:1'],
            'evidencias.*' => ['file', 'max:10240'],
        ]);

        try {
            $credito = $service->handle(array_merge($datos, [
                'canal_origen' => 'punto_venta',
                'generado_por_id' => Auth::id(),
                'origen_manual' => true,
            ]), $request->file('evidencias', []) ?? []);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['monto' => $e->getMessage()]);
        }

        return redirect()
            ->route('saldos_favor.caja.index', ['cliente_id' => $credito->cliente_id])
            ->with('success', "Saldo a favor {$credito->folio} generado en Caja.");
    }

    public function aplicar(
        Request $request,
        ReservarSaldoSafService $reservar,
        AplicarReservaSafService $aplicar,
        LiberarReservaSafService $liberar,
        ObtenerOCrearCuentaSafService $cuentas,
        ConsultarCuentaClienteSafService $consultar,
        ValidarClienteSafService $validarCliente,
    ): RedirectResponse {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'referencia_venta' => ['nullable', 'string', 'max:128'],
            'sucursal' => ['required', 'string', 'max:128'],
            'caja' => ['required', 'string', 'max:64'],
            'perfil_impresion' => ['nullable', 'in:80mm,58mm,carta'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.saf_credito_id' => ['required', 'exists:saf_creditos,id'],
            'items.*.monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $validarCliente->assertTransferible((int) $datos['cliente_id']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['cliente_id' => $e->getMessage()]);
        }

        try {
            $comprobante = DB::transaction(function () use ($datos, $reservar, $aplicar, $liberar, $cuentas, $consultar) {
                $clienteId = (int) $datos['cliente_id'];
                $antes = $consultar->handle($clienteId);
                $cuenta = $cuentas->handle($clienteId);
                $montoTotal = round(array_sum(array_map(fn ($i) => (float) $i['monto'], $datos['items'])), 2);

                $reservas = $reservar->handle($clienteId, $montoTotal, Auth::id(), $datos['items'], [
                    'referencia_externa' => $datos['referencia_venta'] ?? null,
                ]);

                $detalle = [];
                $movimientoIds = [];
                try {
                    foreach ($reservas as $item) {
                        $credito = $aplicar->handle($item['credito']->id, $item['monto'], Auth::id(), [
                            'referencia_externa' => $datos['referencia_venta'] ?? null,
                        ]);
                        $movId = SafMovimiento::query()
                            ->where('saf_credito_id', $credito->id)
                            ->where('tipo', SafMovimiento::TIPO_APLICACION)
                            ->orderByDesc('id')
                            ->value('id');
                        if ($movId) {
                            $movimientoIds[] = $movId;
                        }
                        $detalle[] = [
                            'saf_credito_id' => $item['credito']->id,
                            'folio' => $item['credito']->folio,
                            'canal_origen' => $item['credito']->canal_origen,
                            'documento_origen' => $item['credito']->documento_origen,
                            'generado_por' => $item['credito']->generadoPor?->name,
                            'monto' => $item['monto'],
                        ];
                    }
                } catch (\Throwable $e) {
                    foreach ($reservas as $item) {
                        try {
                            $liberar->handle($item['credito']->id, $item['monto'], Auth::id());
                        } catch (\Throwable) {
                        }
                    }
                    throw $e;
                }

                $despues = $consultar->handle($clienteId);
                $folio = $this->siguienteFolioCaja();
                $branding = $this->resolverBrandingUsuario(Auth::user());

                $comprobante = SafComprobanteCaja::create([
                    'folio' => $folio,
                    'cliente_id' => $clienteId,
                    'saf_cuenta_id' => $cuenta->id,
                    'referencia_venta' => $datos['referencia_venta'] ?? null,
                    'sucursal' => $datos['sucursal'],
                    'caja' => $datos['caja'],
                    'saldo_anterior' => $antes['disponible'],
                    'monto_aplicado' => $montoTotal,
                    'saldo_restante' => $despues['disponible'],
                    'creditos_detalle' => $detalle,
                    'estado' => SafComprobanteCaja::ESTADO_PENDIENTE_FIRMA,
                    'perfil_impresion' => $datos['perfil_impresion'] ?? '80mm',
                    'generado_por_id' => Auth::id(),
                    'departamento_id' => $branding['departamento_id'],
                    'logo_key' => $branding['logo_key'],
                    'aplicado_at' => now(),
                ]);

                if ($movimientoIds !== []) {
                    SafMovimiento::query()
                        ->whereIn('id', $movimientoIds)
                        ->update(['saf_comprobante_caja_id' => $comprobante->id]);
                }

                SafImpresionPreferencia::updateOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'terminal_key' => 'default',
                    ],
                    [
                        'perfil' => $datos['perfil_impresion'] ?? '80mm',
                        'sucursal' => $datos['sucursal'],
                        'caja' => $datos['caja'],
                    ]
                );

                return $comprobante;
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        $revisores = User::permission('saldos_favor.revisar')->get();
        if ($revisores->isNotEmpty()) {
            Notification::send($revisores, new SaldoFavorComprobantePdvNotification($comprobante));
        }

        return redirect()
            ->route('saldos_favor.caja.comprobante', $comprobante)
            ->with('success', "Aplicación registrada. Folio {$comprobante->folio}.");
    }

    public function comprobante(SafComprobanteCaja $comprobante): Response
    {
        $comprobante->load(['cliente', 'generadoPor:id,name', 'departamento:id,nombre,logo_key_claro']);

        return Inertia::render('SaldosAFavor/Comprobante', [
            'comprobante' => $comprobante,
            'encabezado' => $this->encabezadoComprobante($comprobante),
        ]);
    }

    public function marcarFirmado(Request $request, SafComprobanteCaja $comprobante): RedirectResponse
    {
        if ($comprobante->estado === SafComprobanteCaja::ESTADO_CANCELADO) {
            return back()->with('error', 'El comprobante está cancelado.');
        }

        $request->validate([
            'evidencia_firmada' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $ruta = $comprobante->ruta_evidencia_firmada;
        if ($request->hasFile('evidencia_firmada')) {
            $ruta = $request->file('evidencia_firmada')
                ->store("saldos_favor/caja/{$comprobante->id}", 'public');
        }

        $comprobante->update([
            'estado' => SafComprobanteCaja::ESTADO_FIRMADO_PENDIENTE_REVISION,
            'firmado_at' => now(),
            'ruta_evidencia_firmada' => $ruta,
        ]);

        return back()->with('success', 'Comprobante marcado como firmado.');
    }

    public function imprimir(Request $request, SafComprobanteCaja $comprobante): HttpResponse
    {
        $perfil = $request->get('perfil', $comprobante->perfil_impresion ?: '80mm');
        $reimpresion = $request->boolean('reimpresion');
        $autoprint = $request->boolean('autoprint', true);

        if ($reimpresion) {
            SafComprobanteReimpresion::create([
                'saf_comprobante_caja_id' => $comprobante->id,
                'usuario_id' => Auth::id(),
                'perfil_impresion' => $perfil,
            ]);
            if ($comprobante->estado !== SafComprobanteCaja::ESTADO_CANCELADO) {
                $comprobante->update(['es_reimpresion' => true]);
            }
        }

        $comprobante->load(['cliente', 'generadoPor:id,name', 'departamento']);

        return response()->view('saldos_favor.comprobante_caja', [
            'comprobante' => $comprobante,
            'perfil' => $perfil,
            'esReimpresion' => $reimpresion || $comprobante->es_reimpresion,
            'encabezado' => $this->encabezadoComprobante($comprobante),
            'autoprint' => $autoprint,
        ]);
    }

    public function descargar(Request $request, SafComprobanteCaja $comprobante): SymfonyResponse
    {
        $perfil = $request->get('perfil', $comprobante->perfil_impresion ?: '80mm');
        $comprobante->load(['cliente', 'generadoPor:id,name', 'departamento']);

        $encabezado = $this->encabezadoComprobante($comprobante);
        $items = count($comprobante->creditos_detalle ?? []);
        // Altura aproximada al contenido (evita hoja vacía enorme al archivar).
        $alto = max(680, 480 + ($items * 60) + 320);

        $ancho = match ($perfil) {
            '58mm' => 164,
            'carta' => 612,
            default => 226,
        };

        $pdf = Pdf::loadView('saldos_favor.comprobante_caja_pdf', [
            'comprobante' => $comprobante,
            'perfil' => $perfil,
            'esReimpresion' => $comprobante->es_reimpresion,
            'encabezado' => $encabezado,
        ]);
        $pdf->setPaper([0, 0, $ancho, $alto], 'portrait');

        return $pdf->download("{$comprobante->folio}.pdf");
    }

    /**
     * @return array{departamento_id: ?int, logo_key: string}
     */
    private function resolverBrandingUsuario(?User $user): array
    {
        $depto = $user?->departamentoParaBranding();
        $encabezado = RhReciboAssets::encabezadoParaDepartamento(
            $depto?->nombre,
            'negro',
            $depto,
            $depto?->logo_key_claro,
        );

        return [
            'departamento_id' => $depto?->id,
            'logo_key' => $encabezado['logos'][0]['key'] ?? \App\Support\DepartamentoLogoAssets::FALLBACK_KEY,
        ];
    }

    /**
     * @return array{mostrar_aromas: bool, mostrar_bellaroma: bool, logos: array<int, array{key: string, base64: string, w: int, h: int, alt: string}>}
     */
    private function encabezadoComprobante(SafComprobanteCaja $comprobante): array
    {
        $comprobante->loadMissing('departamento');

        return RhReciboAssets::encabezadoParaDepartamento(
            $comprobante->departamento?->nombre,
            'negro',
            $comprobante->departamento,
            $comprobante->logo_key ?: $comprobante->departamento?->logo_key_claro,
        );
    }

    public function guardarPreferencia(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'perfil' => ['required', 'in:80mm,58mm,carta'],
            'copias' => ['nullable', 'integer', 'min:1', 'max:5'],
            'terminal_key' => ['nullable', 'string', 'max:64'],
            'sucursal' => ['nullable', 'string', 'max:128'],
            'caja' => ['nullable', 'string', 'max:64'],
        ]);

        SafImpresionPreferencia::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'terminal_key' => $datos['terminal_key'] ?? 'default',
            ],
            [
                'perfil' => $datos['perfil'],
                'copias' => $datos['copias'] ?? 1,
                'sucursal' => $datos['sucursal'] ?? null,
                'caja' => $datos['caja'] ?? null,
            ]
        );

        return back()->with('success', 'Preferencia de impresión guardada.');
    }

    private function siguienteFolioCaja(): string
    {
        $ultimo = SafComprobanteCaja::query()->lockForUpdate()->orderByDesc('id')->value('folio');
        $n = 1;
        if ($ultimo && preg_match('/SAF-PDV-(\d+)/', $ultimo, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return sprintf('SAF-PDV-%06d', $n);
    }
}
