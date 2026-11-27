<?php

namespace App\Support\Solicitudes;

use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTag;

class EfectosCancelacionSolicitud
{
    /**
     * @return array{
     *   revierte_beneficios: bool,
     *   asigna_tag: bool,
     *   retira_tag: bool,
     *   cambia_lista: bool,
     *   lista_solicitada: ?string,
     *   lista_regreso: ?string,
     *   tag_nombre: ?string,
     *   tag_anterior: ?string,
     *   tipo_solicitado: ?string,
     *   tipo_regreso: ?string,
     *   monto_operacional: float
     * }
     */
    public function de(SolicitudTag $solicitud): array
    {
        $solicitud->loadMissing([
            'proceso',
            'estado',
            'listaDescuento',
            'vendedor',
            'tipoCliente',
            'cliente.listaDescuento',
            'cliente.vendedor',
            'cliente.tipo',
            'auditorias.estadoAnterior',
        ]);

        $antes = $this->snapshotAntes($solicitud);
        $proceso = mb_strtoupper($solicitud->proceso?->nombre ?? '');
        $asignaTag = str_contains($proceso, 'TAG') || str_contains($proceso, 'ASIGNAR CLIENTE');
        $pideLista = str_contains($proceso, 'LISTA') && (bool) $solicitud->catalogo_lista_descuento_id;
        $listaSolicitada = $solicitud->listaDescuento?->nombre;
        $listaRegreso = $antes['lista_nombre'] ?? $solicitud->cliente?->listaDescuento?->nombre;
        $estado = $this->nombreEstado($solicitud);
        $aplicada = $this->fueAplicada($solicitud, $estado);
        $revierte = $aplicada && $solicitud->proceso?->esFinanciero();
        $cambiaLista = $pideLista
            && $this->nombreVisible($listaSolicitada)
            && $this->nombreVisible($listaRegreso)
            && ! $this->nombresIguales($listaSolicitada, $listaRegreso);

        return [
            'revierte_beneficios' => $revierte,
            'asigna_tag' => $asignaTag,
            'retira_tag' => $asignaTag && $revierte,
            'cambia_lista' => $cambiaLista,
            'lista_solicitada' => $listaSolicitada,
            'lista_regreso' => $listaRegreso,
            'tag_nombre' => $asignaTag ? $solicitud->vendedor?->name : null,
            'tag_anterior' => $asignaTag ? ($antes['tag_vendedor_nombre'] ?? null) : null,
            'tipo_solicitado' => $solicitud->tipoCliente?->nombre,
            'tipo_regreso' => $antes['tipo_cliente_nombre'] ?? $solicitud->cliente?->tipo?->nombre,
            'monto_operacional' => (float) ($solicitud->monto_aplicado_al_cliente ?: 0),
        ];
    }

    public function textoBitacora(SolicitudTag $solicitud, string $motivo): string
    {
        $efectos = $this->de($solicitud);
        $partes = ['SOLICITUD DE CANCELACIÓN: '.$motivo];
        if ($efectos['cambia_lista'] && $efectos['lista_regreso']) {
            $partes[] = 'Lista de regreso: '.$efectos['lista_regreso'];
        }
        if ($efectos['asigna_tag'] && $efectos['tag_nombre']) {
            $partes[] = 'TAG se retira: '.$efectos['tag_nombre'];
        }

        return implode(' | ', $partes);
    }

    public function mensajeAlerta(SolicitudTag $solicitud, string $actor): string
    {
        $efectos = $this->de($solicitud);
        $mensaje = $actor.' solicita cancelar la solicitud FOL-'.$solicitud->id.'.';
        if ($efectos['cambia_lista'] && $efectos['lista_regreso']) {
            $mensaje .= ' El cliente regresaría a '.$efectos['lista_regreso'].'.';
        }
        if ($efectos['asigna_tag']) {
            $mensaje .= ' El TAG se retira.';
        }

        return $mensaje;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotAntes(SolicitudTag $solicitud): ?array
    {
        $registros = $solicitud->relationLoaded('auditorias')
            ? $solicitud->auditorias
            : AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->orderBy('id')->get();

        foreach ($registros->sortBy('id') as $registro) {
            $snapshot = $registro->datos_snapshot;
            if (is_array($snapshot) && isset($snapshot['antes']) && is_array($snapshot['antes']) && $snapshot['antes'] !== []) {
                return $snapshot['antes'];
            }
        }

        return null;
    }

    private function nombreEstado(SolicitudTag $solicitud): string
    {
        if ($solicitud->estado?->nombre) {
            return $solicitud->estado->nombre;
        }

        if ($solicitud->catalogo_estado_solicitud_id) {
            return (string) CatalogoEstadoSolicitud::query()
                ->whereKey($solicitud->catalogo_estado_solicitud_id)
                ->value('nombre');
        }

        return '';
    }

    private function fueAplicada(SolicitudTag $solicitud, string $estado): bool
    {
        if (in_array($estado, ['Respondida', 'Verificada'], true)) {
            return true;
        }

        if (mb_strtolower($estado) !== 'cancelada') {
            return false;
        }

        foreach ($solicitud->auditorias ?? [] as $registro) {
            $anterior = $registro->estadoAnterior?->nombre;
            if (in_array($anterior, ['Respondida', 'Verificada'], true)) {
                return true;
            }
        }

        return false;
    }

    private function nombreVisible(?string $nombre): bool
    {
        return trim((string) $nombre) !== '';
    }

    private function nombresIguales(?string $a, ?string $b): bool
    {
        $normalizar = static fn (?string $valor): string => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $valor)) ?? '');

        return $this->nombreVisible($a) && $normalizar($a) === $normalizar($b);
    }
}
