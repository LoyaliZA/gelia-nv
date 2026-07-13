<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarGuiasPedidoService
{
    public function __construct(
        private AsignarGuiaPedidoBmaService $asignarGuiaService,
    ) {}

    /**
     * @return array{actualizados: int, omitidos: int, errores: array<int, string>}
     */
    public function ejecutar(string $rutaArchivo, int $usuarioId): array
    {
        $filas = (new FastExcel)->import($rutaArchivo);
        $resultado = [
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        DB::transaction(function () use ($filas, $usuarioId, &$resultado) {
            $numeroFila = 1;

            foreach ($filas as $fila) {
                $numeroFila++;
                $datos = $this->normalizarFila($fila);
                $idPedido = $datos['id_pedido'];
                $guia = $datos['guia_rastreo'];

                if ($idPedido === null && $guia === '') {
                    continue;
                }

                if ($idPedido === null) {
                    $resultado['errores'][$numeroFila] = 'ID_Pedido inválido o vacío.';
                    $resultado['omitidos']++;
                    continue;
                }

                if ($guia === '') {
                    $resultado['errores'][$numeroFila] = 'Guia_Rastreo vacía.';
                    $resultado['omitidos']++;
                    continue;
                }

                $pedido = PedidoBma::with('estatus')->find($idPedido);

                if (!$pedido) {
                    $resultado['errores'][$numeroFila] = "Pedido {$idPedido} no encontrado.";
                    $resultado['omitidos']++;
                    continue;
                }

                try {
                    $this->asignarGuiaService->ejecutar($pedido, $guia, $usuarioId);
                    $resultado['actualizados']++;
                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    $resultado['errores'][$numeroFila] = $e->getMessage();
                    $resultado['omitidos']++;
                }
            }
        });

        return $resultado;
    }

    private function normalizarFila(array $fila): array
    {
        $mapa = [];
        foreach ($fila as $clave => $valor) {
            $mapa[strtolower(trim((string) $clave))] = is_string($valor) ? trim($valor) : $valor;
        }

        $idRaw = $mapa['id_pedido'] ?? null;
        $id = is_numeric($idRaw) ? (int) $idRaw : null;

        return [
            'id_pedido' => $id,
            'guia_rastreo' => (string) ($mapa['guia_rastreo'] ?? ''),
        ];
    }
}
