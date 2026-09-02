<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolverEtiquetaResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{
     *     codigo_etiqueta: string,
     *     bulto_id: int,
     *     folio: string|null,
     *     tipo: string,
     *     resguardo_id: int,
     *     resguardo_folio: string|null,
     *     estado_resguardo: string
     * }
     */
    public function resolver(User $user, string $codigoCrudo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdvBulto::class);
        }

        $codigo = GeneradorCodigoEtiquetaResguardoPdv::normalizarEntrada($codigoCrudo);
        if ($codigo === '') {
            throw (new ModelNotFoundException)->setModel(ResguardoPdvBulto::class);
        }

        $bulto = ResguardoPdvBulto::query()
            ->where('codigo_etiqueta', $codigo)
            ->with('resguardo:id,sucursal_id,snapshot_folio,estado')
            ->first();

        if (! $bulto instanceof ResguardoPdvBulto
            || ! $bulto->resguardo instanceof ResguardoPdv
            || (int) $bulto->resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdvBulto::class);
        }

        return [
            'codigo_etiqueta' => $bulto->codigo_etiqueta,
            'bulto_id' => $bulto->id,
            'folio' => $bulto->folio,
            'tipo' => $bulto->tipo,
            'resguardo_id' => $bulto->resguardo_id,
            'resguardo_folio' => $bulto->resguardo->snapshot_folio,
            'estado_resguardo' => $bulto->resguardo->estado,
        ];
    }
}
