<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use App\Support\Comercial\VisitaProgramadaCatalogo;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class SerializarVisitaProgramadaService
{
    public function __construct(
        private readonly ResolverEstadoTiempoVisitaService $estadoTiempo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function item(VisitaClienteProgramada $visita, CarbonInterface $ahora): array
    {
        $visita->loadMissing([
            'cliente:id,numero_cliente,nombre,nombre_razon_social',
            'sucursal:id,nombre',
            'registradoPor:id,name,departamento_id',
            'registradoPor.departamento:id,nombre',
        ]);

        $cliente = $visita->cliente;
        $registrador = $visita->registradoPor;

        return [
            'id' => $visita->id,
            'fecha' => $visita->fecha->toDateString(),
            'tipo_hora' => $visita->tipo_hora,
            'hora_exacta' => $this->formatearHora($visita->hora_exacta),
            'hora_inicio' => $this->formatearHora($visita->hora_inicio),
            'hora_fin' => $this->formatearHora($visita->hora_fin),
            'hora_etiqueta' => $this->etiquetaHora($visita),
            'intencion' => $visita->intencion,
            'intencion_etiqueta' => VisitaProgramadaCatalogo::etiquetaIntencion($visita->intencion),
            'estado' => $visita->estado,
            'estado_etiqueta' => VisitaProgramadaCatalogo::etiquetaEstado($visita->estado),
            'estado_tiempo' => $this->estadoTiempo->resolver($visita, $ahora),
            'cliente' => $cliente ? [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre ?: $cliente->nombre_razon_social,
            ] : null,
            'sucursal' => $visita->sucursal ? [
                'id' => $visita->sucursal->id,
                'nombre' => $visita->sucursal->nombre,
            ] : null,
            'registrado_por' => $registrador ? [
                'id' => $registrador->id,
                'nombre' => $registrador->name,
                'departamento' => $registrador->departamento?->nombre,
            ] : null,
            'llegada_confirmada_at' => $visita->llegada_confirmada_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, VisitaClienteProgramada>  $visitas
     * @return list<array<string, mixed>>
     */
    public function coleccion(Collection $visitas, CarbonInterface $ahora): array
    {
        return $visitas->map(fn (VisitaClienteProgramada $visita) => $this->item($visita, $ahora))->values()->all();
    }

    private function formatearHora(mixed $hora): ?string
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        return substr((string) $hora, 0, 5);
    }

    private function etiquetaHora(VisitaClienteProgramada $visita): string
    {
        if ($visita->tipo_hora === VisitaClienteProgramada::TIPO_HORA_EXACTA && $visita->hora_exacta) {
            return $this->formatearHora($visita->hora_exacta) ?? '—';
        }

        if ($visita->tipo_hora === VisitaClienteProgramada::TIPO_HORA_RANGO
            && $visita->hora_inicio
            && $visita->hora_fin) {
            return sprintf(
                '%s – %s',
                $this->formatearHora($visita->hora_inicio),
                $this->formatearHora($visita->hora_fin),
            );
        }

        return 'Sin hora definida';
    }
}
