<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Collection;

class ProcesarFilaClienteAction
{
    protected $mapaWizerp = [
        'PG'              => 'PUBLICO GENERAL',
        '7'               => 'COLABORADORES',
        '5'               => 'PLATAFORMAS',
        '4'               => 'MAYOREO DIAMANTE',
        '3'               => 'MAYOREO PLATA',
        '2'               => 'MAYOREO BRONCE',
        '1'               => 'MAYOREO ORO',
        'DIAMANTE'        => 'MAYOREO DIAMANTE',
        'ORO'             => 'MAYOREO ORO',
        'PLATA'           => 'MAYOREO PLATA',
        'BRONCE'          => 'MAYOREO BRONCE',
        'COLABORADORES'   => 'COLABORADORES',
        'PLATAFORMAS'     => 'PLATAFORMAS',
        'PUBLICO GENERAL' => 'PUBLICO GENERAL',
    ];

    public function ejecutar(
        array $data,
        $listas,
        array $mapaVendedoras,
        Collection $clientesPorNumero,
        array &$historialBatch,
        bool $importaCodigoLista,
        int &$marcadosInactivos,
        array &$alertasLimiteExcedido
    ): ?array {
        $numeroCliente = trim($data['numero_cliente'] ?? '');
        if (empty($numeroCliente)) {
            return null;
        }

        $cliente = $clientesPorNumero->get($numeroCliente);

        if ($cliente) {
            return $this->actualizarClienteExistente(
                $cliente,
                $data,
                $listas,
                $mapaVendedoras,
                $historialBatch,
                $importaCodigoLista,
                $marcadosInactivos,
                $alertasLimiteExcedido
            );
        }

        $nuevo = $this->crearNuevoCliente($data, $listas, $mapaVendedoras, $importaCodigoLista, $marcadosInactivos, $alertasLimiteExcedido);
        if ($nuevo) {
            $clientesPorNumero->put($numeroCliente, $nuevo);
        }

        return null;
    }

    private function crearNuevoCliente(
        array $data,
        $listas,
        array $mapaVendedoras,
        bool $importaCodigoLista,
        int &$marcadosInactivos,
        array &$alertasLimiteExcedido
    ): ?Cliente {
        if (empty(trim($data['nombre'] ?? ''))) {
            return null;
        }

        $listaPgId = $this->idListaPublicoGeneral($listas);
        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);

        $clienteNuevo = [
            'numero_cliente'     => trim($data['numero_cliente']),
            'nombre'             => trim($data['nombre']),
            'monto_venta_actual' => $montoExtraido ?? 0.00,
            'es_heredado'        => $this->evaluarHeredado($data['es_heredado'] ?? null),
            'es_inactivo'        => false,
        ];

        if (!empty($data['vendedor_id'])) {
            $clienteNuevo['vendedor_id'] = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
        }

        if ($importaCodigoLista && $this->codigoListaVacio($data)) {
            $clienteNuevo['es_inactivo'] = true;
            $clienteNuevo['lista_actual_id'] = $listaPgId;
            $marcadosInactivos++;
        } elseif ($importaCodigoLista) {
            $listaIdCSV = $this->resolverLista($data, $listas);
            $clienteNuevo['lista_actual_id'] = $this->resolverListaFinal($listaIdCSV, $montoExtraido, $listas);
        } else {
            $clienteNuevo['lista_actual_id'] = $this->resolverListaFinal(null, $montoExtraido, $listas);
        }

        if (isset($data['monto_credito_autorizado']) && trim($data['monto_credito_autorizado']) !== '') {
            $clienteNuevo['monto_credito_autorizado'] = $this->limpiarMonto($data['monto_credito_autorizado']);
        }
        if (isset($data['dias_credito']) && trim($data['dias_credito']) !== '') {
            $clienteNuevo['dias_credito'] = (int) trim($data['dias_credito']);
        }

        $camposNuevos = [
            'direccion_fiscal', 'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal', 'pais_fiscal',
            'direccion_contacto', 'colonia_contacto', 'municipio_contacto', 'estado_contacto', 'pais_contacto', 'cp_contacto', 'telefono',
            'parte_relacional', 'variable_contable',
            'rfc', 'codigo_postal', 'regimen_fiscal', 'correo_electronico', 'uso_factura', 'nombre_razon_social'
        ];

        foreach ($camposNuevos as $campo) {
            if (isset($data[$campo]) && trim($data[$campo]) !== '') {
                $clienteNuevo[$campo] = trim($data[$campo]);
            }
        }

        if (isset($data['dias_cheque_postfechado']) && trim($data['dias_cheque_postfechado']) !== '') {
            $clienteNuevo['dias_cheque_postfechado'] = (int) trim($data['dias_cheque_postfechado']);
        }

        $nuevoRegistro = Cliente::create($clienteNuevo);

        // Se elimina la comparación entre monto_venta_actual y monto_credito_autorizado, 
        // ya que monto_venta_actual representa ventas, no deuda.
        // Al ser un cliente nuevo, su deuda actual (saldo de facturas) es 0.

        return $nuevoRegistro;
    }

    private function actualizarClienteExistente(
        Cliente $cliente,
        array $data,
        $listas,
        array $mapaVendedoras,
        array &$historialBatch,
        bool $importaCodigoLista,
        int &$marcadosInactivos,
        array &$alertasLimiteExcedido
    ): ?array {
        $updateData = [];
        $listaOriginalId = $cliente->lista_actual_id;
        $cambioReporte = null;
        $listaPgId = $this->idListaPublicoGeneral($listas);

        if (!empty(trim($data['nombre'] ?? ''))) {
            $updateData['nombre'] = trim($data['nombre']);
        }

        if (!empty($data['vendedor_id'])) {
            $vendedorIdTraducido = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
            if ($vendedorIdTraducido) {
                $updateData['vendedor_id'] = $vendedorIdTraducido;
            }
        }

        if (isset($data['es_heredado']) && $data['es_heredado'] !== '') {
            $esHeredadoCsv = $this->evaluarHeredado($data['es_heredado']);
            if ($cliente->es_heredado !== $esHeredadoCsv) {
                $updateData['es_heredado'] = $esHeredadoCsv;
            }
        }

        $listaIdCSV = null;
        if ($importaCodigoLista && !$cliente->lista_bloqueada) {
            if ($this->codigoListaVacio($data)) {
                if (!$cliente->es_inactivo) {
                    $marcadosInactivos++;
                }
                $updateData['es_inactivo'] = true;
                if ($cliente->lista_actual_id !== $listaPgId) {
                    $updateData['lista_actual_id'] = $listaPgId;
                }
            } else {
                $listaIdCSV = $this->resolverLista($data, $listas);
                $updateData['es_inactivo'] = false;
                if ($listaIdCSV && $cliente->lista_actual_id !== $listaIdCSV) {
                    $updateData['lista_actual_id'] = $listaIdCSV;
                }
            }
        }

        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);

        if ($montoExtraido !== null && !$this->montosSonIguales($cliente->monto_venta_actual, $montoExtraido)) {
            $updateData['monto_venta_actual'] = $montoExtraido;

            $listaEvaluacionId = $updateData['lista_actual_id'] ?? $cliente->lista_actual_id;

            if (!$cliente->lista_bloqueada && $montoExtraido > 0.001 && !$this->esListaExcluida($listaEvaluacionId, $listas)) {
                $nuevaListaIdPorMonto = $this->determinarListaPorMonto($montoExtraido, $listas);

                $listaActualValidacion = $listas->firstWhere('id', $listaEvaluacionId);
                $nuevaListaCalculada = $listas->firstWhere('id', $nuevaListaIdPorMonto);

                if ($listaActualValidacion && $nuevaListaCalculada && ($nuevaListaCalculada->monto_requerido > $listaActualValidacion->monto_requerido)) {
                    $updateData['lista_actual_id'] = $nuevaListaIdPorMonto;
                }
            }

            $this->agregarHistorialMonto($cliente, $montoExtraido, $historialBatch);
        }

        if (isset($data['monto_credito_autorizado']) && trim($data['monto_credito_autorizado']) !== '') {
            $nuevoLimite = $this->limpiarMonto($data['monto_credito_autorizado']);
            if (!$this->montosSonIguales((float) $cliente->monto_credito_autorizado, (float) $nuevoLimite)) {
                $updateData['monto_credito_autorizado'] = $nuevoLimite;
            }
        }

        if (isset($data['dias_credito']) && trim($data['dias_credito']) !== '') {
            $nuevosDias = (int) trim($data['dias_credito']);
            if ($cliente->dias_credito !== $nuevosDias) {
                $updateData['dias_credito'] = $nuevosDias;
            }
        }

        $camposNuevos = [
            'direccion_fiscal', 'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal', 'pais_fiscal',
            'direccion_contacto', 'colonia_contacto', 'municipio_contacto', 'estado_contacto', 'pais_contacto', 'cp_contacto', 'telefono',
            'parte_relacional', 'variable_contable',
            'rfc', 'codigo_postal', 'regimen_fiscal', 'correo_electronico', 'uso_factura', 'nombre_razon_social'
        ];

        foreach ($camposNuevos as $campo) {
            if (isset($data[$campo]) && trim($data[$campo]) !== '') {
                $valorLimpio = trim($data[$campo]);
                if ($cliente->$campo !== $valorLimpio) {
                    $updateData[$campo] = $valorLimpio;
                }
            }
        }

        if (isset($data['dias_cheque_postfechado']) && trim($data['dias_cheque_postfechado']) !== '') {
            $diasCheque = (int) trim($data['dias_cheque_postfechado']);
            if ($cliente->dias_cheque_postfechado !== $diasCheque) {
                $updateData['dias_cheque_postfechado'] = $diasCheque;
            }
        }

        if (!empty($updateData)) {
            $listaDefinitivaId = $updateData['lista_actual_id'] ?? $listaOriginalId;

            if ($listaDefinitivaId !== $listaOriginalId) {
                $listaAnterior = $listas->firstWhere('id', $listaOriginalId);
                $listaNueva = $listas->firstWhere('id', $listaDefinitivaId);

                if ($listaAnterior && $listaNueva && ($listaNueva->monto_requerido > $listaAnterior->monto_requerido)) {
                    $cambioReporte = [
                        'numero_cliente' => $cliente->numero_cliente,
                        'nombre'         => $cliente->nombre,
                        'lista_anterior' => $listaAnterior->nombre,
                        'lista_nueva'    => $listaNueva->nombre,
                        'monto_nuevo'    => $updateData['monto_venta_actual'] ?? $cliente->monto_venta_actual,
                    ];
                }
            }

            $cliente->update($updateData);
        }

        $limiteFinal = (float) $cliente->monto_credito_autorizado;
        $deudaReal = (float) ($cliente->facturaCobranzaActiva?->monto ?? 0);

        if ($limiteFinal > 0 && $deudaReal > $limiteFinal) {
            $alertasLimiteExcedido[] = [
                'cliente' => $cliente,
                'monto_actual' => $deudaReal,
                'limite' => $limiteFinal
            ];
        }

        return $cambioReporte;
    }

    private function codigoListaVacio(array $data): bool
    {
        return trim((string) ($data['codigo_lista'] ?? '')) === '';
    }

    private function idListaPublicoGeneral($listas): int
    {
        return $listas->firstWhere('nombre', 'PUBLICO GENERAL')->id;
    }

    private function agregarHistorialMonto(Cliente $cliente, float $montoNuevo, array &$historialBatch): void
    {
        $ahora = now();
        $historialBatch[] = [
            'cliente_id'          => $cliente->id,
            'monto_anterior'      => $cliente->monto_venta_actual,
            'monto_nuevo'         => $montoNuevo,
            'diferencia_aplicada' => $montoNuevo - $cliente->monto_venta_actual,
            'created_at'          => $ahora,
            'updated_at'          => $ahora,
        ];
    }

    private function evaluarHeredado(?string $valor): bool
    {
        if (!$valor) {
            return false;
        }
        $limpio = strtoupper(trim($valor));

        return in_array($limpio, ['1', 'SI', 'SÍ', 'TRUE', 'VERDADERO', 'X', 'YES']);
    }

    private function esListaExcluida(int $listaId, $listas): bool
    {
        $lista = $listas->firstWhere('id', $listaId);

        return $lista && in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS']);
    }

    private function montosSonIguales(float $montoA, float $montoB): bool
    {
        return abs($montoA - $montoB) < 0.001;
    }

    private function traducirVendedora(?string $nombreWizerp, array $mapaVendedoras): ?int
    {
        if (empty(trim($nombreWizerp ?? ''))) {
            return null;
        }
        $nombreNormalizado = strtoupper(trim($nombreWizerp));

        return $mapaVendedoras[$nombreNormalizado] ?? null;
    }

    private function resolverLista(array $data, $listas): ?int
    {
        if ($this->codigoListaVacio($data)) {
            return null;
        }
        $raw = trim((string) ($data['codigo_lista'] ?? ''));
        $codigo = $this->normalizarCodigoLista($raw);

        if (!isset($this->mapaWizerp[$codigo])) {
            return null;
        }

        return $listas->where('nombre', $this->mapaWizerp[$codigo])->first()->id ?? null;
    }

    /**
     * Jerarquía: con monto > 0 el monto solo puede ascender la lista del CSV;
     * con monto 0 o vacío prevalece codigo_lista del CSV.
     */
    private function resolverListaFinal(?int $listaIdCSV, ?float $montoExtraido, $listas): int
    {
        $listaPgId = $this->idListaPublicoGeneral($listas);

        if ($montoExtraido === null || $montoExtraido <= 0.001) {
            return $listaIdCSV ?? $listaPgId;
        }

        $listaPorMonto = $this->determinarListaPorMonto($montoExtraido, $listas);

        if (!$listaIdCSV) {
            return $listaPorMonto;
        }

        $listaCsv = $listas->firstWhere('id', $listaIdCSV);
        $listaMonto = $listas->firstWhere('id', $listaPorMonto);

        if ($listaCsv && $listaMonto && ($listaMonto->monto_requerido > $listaCsv->monto_requerido)) {
            return $listaPorMonto;
        }

        return $listaIdCSV;
    }

    private function normalizarCodigoLista(string $raw): string
    {
        $codigo = strtoupper(trim($raw));

        if (preg_match('/^(\d+)\.0+$/', $codigo, $matches)) {
            return $matches[1];
        }

        if (is_numeric($codigo) && !isset($this->mapaWizerp[$codigo])) {
            return (string) (int) (float) $codigo;
        }

        return $codigo;
    }

    private function limpiarMonto(?string $montoRaw): ?float
    {
        if ($montoRaw === null || trim($montoRaw) === '') {
            return null;
        }
        $montoLimpio = trim(str_replace(['$', ','], '', $montoRaw));
        if ($montoLimpio === '-') {
            return 0.00;
        }

        return is_numeric($montoLimpio) ? (float) $montoLimpio : null;
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if (in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS'])) {
                continue;
            }
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }

        return $this->idListaPublicoGeneral($listas);
    }
}
