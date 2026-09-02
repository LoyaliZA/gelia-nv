<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerarEtiquetasResguardoPdvService
{
    public const MAX_ETIQUETAS = 100;

    /** Formato aprobado: etiqueta 50×30 mm, QR con URL de resolución (sin datos personales). */
    private const ANCHO_MM = 50.0;

    private const ALTO_MM = 30.0;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  list<int>|null  $bultoIds
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        ?array $bultoIds = null,
    ): DomPdfInstance {
        $this->alcance->asegurarConsultaPiso($actor, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $activaId = $this->alcance->sucursalActivaId($actor);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        return DB::transaction(function () use ($resguardo, $actor, $bultoIds) {
            $resguardo = ResguardoPdv::query()
                ->with(['bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id')])
                ->lockForUpdate()
                ->findOrFail($resguardo->id);

            $bultos = $this->filtrarBultos($resguardo->bultos, $bultoIds);
            if ($bultos->isEmpty()) {
                throw ValidationException::withMessages([
                    'bultos' => 'No hay bultos con etiqueta disponible para este resguardo.',
                ]);
            }

            if ($bultos->count() > self::MAX_ETIQUETAS) {
                throw ValidationException::withMessages([
                    'bultos' => 'Demasiadas etiquetas solicitadas (máximo '.self::MAX_ETIQUETAS.').',
                ]);
            }

            $this->asegurarCodigosEtiqueta($bultos);

            $items = $bultos->map(fn (ResguardoPdvBulto $bulto) => [
                'folio' => $bulto->folio ?: 'Sin folio',
                'tipo' => $bulto->tipo,
                'referencia_pedido' => $resguardo->snapshot_folio ?: ('Resguardo #'.$resguardo->id),
                'codigo_etiqueta' => $bulto->codigo_etiqueta,
                'qr_base64' => $this->qrBase64($bulto->codigo_etiqueta),
            ])->values()->all();

            ResguardoPdvEvento::query()->create([
                'resguardo_id' => $resguardo->id,
                'tipo_evento' => ResguardoPdvEvento::TIPO_ETIQUETAS_GENERADAS,
                'actor_id' => $actor->id,
                'ocurrido_at' => now(),
                'snapshot_json' => [
                    'bulto_ids' => $bultos->pluck('id')->values()->all(),
                    'cantidad' => $bultos->count(),
                ],
            ]);

            return Pdf::loadView('punto-venta.resguardos.etiquetas_bultos', [
                'items' => $items,
                'ancho_mm' => self::ANCHO_MM,
                'alto_mm' => self::ALTO_MM,
            ])->setPaper([0, 0, self::ANCHO_MM * 2.83465, self::ALTO_MM * 2.83465]);
        });
    }

    /**
     * @param  Collection<int, ResguardoPdvBulto>  $bultos
     * @param  list<int>|null  $bultoIds
     * @return Collection<int, ResguardoPdvBulto>
     */
    private function filtrarBultos(Collection $bultos, ?array $bultoIds): Collection
    {
        if ($bultoIds === null || $bultoIds === []) {
            return $bultos->values();
        }

        $ids = array_map('intval', $bultoIds);

        return $bultos->whereIn('id', $ids)->values();
    }

    /**
     * @param  Collection<int, ResguardoPdvBulto>  $bultos
     */
    private function asegurarCodigosEtiqueta(Collection $bultos): void
    {
        foreach ($bultos as $bulto) {
            if (filled($bulto->codigo_etiqueta)) {
                continue;
            }

            $bulto->update([
                'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
            ]);
        }
    }

    private function qrBase64(string $codigo, int $size = 200): string
    {
        $url = route('punto_venta.resguardos.etiquetas.resolver', ['codigo' => $codigo], absolute: true);
        $qrCode = new QrCode(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 2,
        );

        return base64_encode((new PngWriter())->write($qrCode)->getString());
    }
}
