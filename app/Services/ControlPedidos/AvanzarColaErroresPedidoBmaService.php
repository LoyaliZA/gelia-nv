<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;

class AvanzarColaErroresPedidoBmaService
{
    public function __construct(
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * Quita los campos del dueño que ya corrigió y deja la cola restante.
     *
     * @return list<string>
     */
    public function quitarDueno(PedidoBma $pedido, string $dueno): array
    {
        $actuales = CamposIncorrectosPedidoBma::filtrar($pedido->campos_incorrectos ?? []);

        return CamposIncorrectosPedidoBma::quitarCamposDeDueno($actuales, $dueno);
    }

    /**
     * Quita campos concretos ya resueltos (p. ej. solo numero_rastreo).
     *
     * @param  list<string>  $camposResueltos
     * @return list<string>
     */
    public function quitarCampos(PedidoBma $pedido, array $camposResueltos): array
    {
        $actuales = CamposIncorrectosPedidoBma::filtrar($pedido->campos_incorrectos ?? []);
        $resueltos = CamposIncorrectosPedidoBma::filtrar($camposResueltos);

        return array_values(array_diff($actuales, $resueltos));
    }

    /** @return array<string, mixed> */
    public function attrsColaVacia(): array
    {
        return [
            'campos_incorrectos' => null,
            'detalle_error_datos' => null,
            'error_datos_at' => null,
            'error_datos_por_id' => null,
            'motivo_rechazo' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function attrsColaPendiente(array $camposRestantes): array
    {
        $camposRestantes = CamposIncorrectosPedidoBma::filtrar($camposRestantes);
        $etiquetas = CamposIncorrectosPedidoBma::etiquetasDe($camposRestantes);

        return [
            'campos_incorrectos' => $camposRestantes,
            'motivo_rechazo' => $etiquetas === []
                ? null
                : 'Error de datos pendiente: '.implode(', ', $etiquetas),
        ];
    }

    /**
     * Tras corregir un dueño: limpia meta o notifica al siguiente.
     * No cambia la fase (el caller la define); solo cola + notificación.
     *
     * @param  list<string>  $camposRestantes
     */
    public function notificarSiguienteSiAplica(
        PedidoBma $pedido,
        array $camposRestantes,
        int $usuarioId,
        ?string $faseOverride = null,
    ): void {
        $camposRestantes = CamposIncorrectosPedidoBma::filtrar($camposRestantes);
        $dueno = CamposIncorrectosPedidoBma::duenoActivo($camposRestantes);
        if ($dueno === null) {
            return;
        }

        $destino = CamposIncorrectosPedidoBma::destinoPara($dueno);
        $fase = $faseOverride ?? $destino['fase'];
        $etiquetas = CamposIncorrectosPedidoBma::etiquetasDe(
            CamposIncorrectosPedidoBma::camposDeDueno($camposRestantes, $dueno)
        );
        $resumen = implode(', ', $etiquetas);
        $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));

        $this->notificarService->ejecutar(
            $pedido,
            $destino['tipo_alerta'],
            $this->mensajePara($dueno, $resumen),
            $destino['permisos'],
            $usuarioId,
            $destino['incluir_vendedora'],
            [
                'url' => $destino['url_path'].'&q='.$q,
                'campos_incorrectos' => $camposRestantes,
                'fase_destino' => $fase,
            ]
        );
    }

    public function faseParaGuiasPendientes(PedidoBma $pedido): string
    {
        if ($pedido->empacado_at !== null) {
            return CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA;
        }

        return CatalogoEstatusPedido::FASE_EN_CEDIS;
    }

    private function mensajePara(string $dueno, string $resumen): string
    {
        return match ($dueno) {
            CamposIncorrectosPedidoBma::DUENO_VENDEDORA => "Error de datos pendiente de corrección: {$resumen}.",
            CamposIncorrectosPedidoBma::DUENO_AUXILIAR => "Error de remisión pendiente: {$resumen}. Corrija antes de aprobar.",
            CamposIncorrectosPedidoBma::DUENO_GUIAS => "Error de guía grave: {$resumen}. No enviar hasta corregir.",
            default => "Error pendiente: {$resumen}.",
        };
    }
}
