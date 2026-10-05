<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListarVisitasProgramadasService
{
    public function __construct(
        private readonly SerializarVisitaProgramadaService $serializar,
    ) {}

    /**
     * @return array{servidor_at: string, visitas: list<array<string, mixed>>}
     */
    public function bandejaPdvDia(User $user, int $sucursalId, CarbonInterface $ahora): array
    {
        $visitas = $this->queryBase()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha', $ahora->toDateString())
            ->where('estado', VisitaClienteProgramada::ESTADO_PROGRAMADA)
            ->orderBy('hora_exacta')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'visitas' => $this->serializar->coleccion($visitas, $ahora),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, VisitaClienteProgramada>
     */
    public function paginarParaRegistrador(User $user, string $vista, CarbonInterface $ahora, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBase()
            ->where('registrado_por_user_id', $user->id);

        if ($vista === 'vigentes') {
            $query->where('estado', VisitaClienteProgramada::ESTADO_PROGRAMADA)
                ->whereDate('fecha', '>=', $ahora->toDateString());
        } else {
            $query->where(function (Builder $inner) use ($ahora) {
                $inner->whereDate('fecha', '<', $ahora->toDateString())
                    ->orWhereIn('estado', [
                        VisitaClienteProgramada::ESTADO_ASISTIO,
                        VisitaClienteProgramada::ESTADO_NO_ASISTIO,
                    ]);
            });
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Builder<VisitaClienteProgramada>
     */
    private function queryBase(): Builder
    {
        return VisitaClienteProgramada::query()->with([
            'cliente:id,numero_cliente,nombre,nombre_razon_social',
            'sucursal:id,nombre',
            'registradoPor:id,name,departamento_id',
            'registradoPor.departamento:id,nombre',
        ]);
    }
}
