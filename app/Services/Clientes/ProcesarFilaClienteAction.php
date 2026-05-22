<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\HistorialMontoCliente;

class ProcesarFilaClienteAction
{
    // --- CONSTANTES Y PROPIEDADES ---
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

    // --- METODO PRINCIPAL ---
    public function ejecutar(array $data, $listas, array $mapaVendedoras): ?array
    {
        $numeroCliente = trim($data['numero_cliente'] ?? '');
        if (empty($numeroCliente)) return null;

        $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
        
        if ($cliente) {
            return $this->actualizarClienteExistente($cliente, $data, $listas, $mapaVendedoras);
        }
        
        $this->crearNuevoCliente($data, $listas, $mapaVendedoras);
        return null; 
    }

    // --- LOGICA DE CREACION ---
    private function crearNuevoCliente(array $data, $listas, array $mapaVendedoras): void
    {
        if (empty(trim($data['nombre'] ?? ''))) return;

        $clienteNuevo = [
            'numero_cliente'     => trim($data['numero_cliente']),
            'nombre'             => trim($data['nombre']),
            'monto_venta_actual' => 0.00,
            'es_heredado'        => $this->evaluarHeredado($data['es_heredado'] ?? null)
        ];

        if (!empty($data['vendedor_id'])) {
            $clienteNuevo['vendedor_id'] = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
        }

        $listaIdCSV = $this->resolverLista($data, $listas);
        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);

        if ($montoExtraido !== null) {
            $clienteNuevo['monto_venta_actual'] = $montoExtraido;
        }

        // Jerarquía de creación: Monto > Lista CSV > Público General
        if ($montoExtraido !== null) {
            $clienteNuevo['lista_actual_id'] = $this->determinarListaPorMonto($montoExtraido, $listas);
        } else {
            $clienteNuevo['lista_actual_id'] = $listaIdCSV ?? $listas->firstWhere('nombre', 'PUBLICO GENERAL')->id;
        }

        Cliente::create($clienteNuevo);
    }

    // --- LOGICA DE ACTUALIZACION ---
    private function actualizarClienteExistente(Cliente $cliente, array $data, $listas, array $mapaVendedoras): ?array
    {
        // --- CAPA DE SEGURIDAD ABSOLUTA (SEGURO DE LISTAS ANCLADAS) ---
        if ($cliente->lista_bloqueada) {
            $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);
            
            if ($montoExtraido !== null && !$this->montosSonIguales($cliente->monto_venta_actual, $montoExtraido)) {
                
                // Registrar auditoría financiera antes de actualizar el monto
                HistorialMontoCliente::create([
                    'cliente_id'          => $cliente->id,
                    'monto_anterior'      => $cliente->monto_venta_actual,
                    'monto_nuevo'         => $montoExtraido,
                    'diferencia_aplicada' => $montoExtraido - $cliente->monto_venta_actual
                ]);

                // Actualizar solo el monto. Su lista, nombre y vendedor quedan intocables.
                $cliente->update(['monto_venta_actual' => $montoExtraido]);
            }
            // Retornamos null para garantizar que jamás aparezca en el reporte de "Ascensos"
            return null; 
        }

        $updateData = [];
        $listaOriginalId = $cliente->lista_actual_id; 
        $cambioReporte = null;
        
        // 1. Procesar Nombre
        if (!empty(trim($data['nombre'] ?? ''))) {
            $updateData['nombre'] = trim($data['nombre']);
        }

        // 2. Procesar Vendedor
        if (!empty($data['vendedor_id'])) {
            $vendedorIdTraducido = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
            if ($vendedorIdTraducido) $updateData['vendedor_id'] = $vendedorIdTraducido;
        }

        // 3. Procesar Herencia
        if (isset($data['es_heredado']) && $data['es_heredado'] !== '') {
            $esHeredadoCsv = $this->evaluarHeredado($data['es_heredado']);
            if ($cliente->es_heredado !== $esHeredadoCsv) {
                $updateData['es_heredado'] = $esHeredadoCsv;
            }
        }

        // 4. Analizar Lista del CSV (como referencia inicial)
        $listaIdCSV = $this->resolverLista($data, $listas);
        if ($listaIdCSV && $cliente->lista_actual_id !== $listaIdCSV) {
            $updateData['lista_actual_id'] = $listaIdCSV;
        }
        
        // 5. Analizar Monto (Supremacía sobre la Lista del CSV)
        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);
        
        // Utilizamos evaluación precisa de decimales para evitar fallos silenciosos
        if ($montoExtraido !== null && !$this->montosSonIguales($cliente->monto_venta_actual, $montoExtraido)) {
            $updateData['monto_venta_actual'] = $montoExtraido;

            // Determinar contra qué lista comparar (La que mandó el CSV o la actual de DB)
            $listaEvaluacionId = $updateData['lista_actual_id'] ?? $cliente->lista_actual_id;

            if (!$this->esListaExcluida($listaEvaluacionId, $listas)) {
                $nuevaListaIdPorMonto = $this->determinarListaPorMonto($montoExtraido, $listas);

                // Comprobar si el cálculo por monto es superior a lo que se planeaba dejar
                $listaActualValidacion = $listas->firstWhere('id', $listaEvaluacionId);
                $nuevaListaCalculada = $listas->firstWhere('id', $nuevaListaIdPorMonto);

                if ($listaActualValidacion && $nuevaListaCalculada && ($nuevaListaCalculada->monto_requerido > $listaActualValidacion->monto_requerido)) {
                    // El monto gana y sobreescribe cualquier lista mandada por Wizerp
                    $updateData['lista_actual_id'] = $nuevaListaIdPorMonto;
                }
            }

            // Registrar en historial financiero
            HistorialMontoCliente::create([
                'cliente_id'          => $cliente->id,
                'monto_anterior'      => $cliente->monto_venta_actual,
                'monto_nuevo'         => $montoExtraido,
                'diferencia_aplicada' => $montoExtraido - $cliente->monto_venta_actual
            ]);
        }
        
        // 6. Ejecutar Actualizaciones y Reportes
        if (!empty($updateData)) {
            $listaDefinitivaId = $updateData['lista_actual_id'] ?? $listaOriginalId;

            if ($listaDefinitivaId !== $listaOriginalId) {
                $listaAnterior = $listas->firstWhere('id', $listaOriginalId);
                $listaNueva = $listas->firstWhere('id', $listaDefinitivaId);

                // Auditar únicamente si es ascenso
                if ($listaAnterior && $listaNueva && ($listaNueva->monto_requerido > $listaAnterior->monto_requerido)) {
                    $cambioReporte = [
                        'numero_cliente' => $cliente->numero_cliente,
                        'nombre'         => $cliente->nombre,
                        'lista_anterior' => $listaAnterior->nombre,
                        'lista_nueva'    => $listaNueva->nombre,
                        'monto_nuevo'    => $updateData['monto_venta_actual'] ?? $cliente->monto_venta_actual
                    ];
                }
            }

            $cliente->update($updateData);
        }

        return $cambioReporte;
    }

    // --- EVALUADORES BOLEANOS ---
    private function evaluarHeredado(?string $valor): bool
    {
        if (!$valor) return false;
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
        // Uso de epsilon para tolerar diferencias a nivel de sistema decimal
        return abs($montoA - $montoB) < 0.001;
    }

    // --- TRADUCTORES Y BUSCADORES ---
    private function traducirVendedora(?string $nombreWizerp, array $mapaVendedoras): ?int
    {
        if (empty(trim($nombreWizerp ?? ''))) return null;
        $nombreNormalizado = strtoupper(trim($nombreWizerp));
        return $mapaVendedoras[$nombreNormalizado] ?? null;
    }

    private function resolverLista(array $data, $listas): ?int
    {
        if (empty(trim($data['codigo_lista'] ?? ''))) return null;
        $codigo = strtoupper(trim($data['codigo_lista']));

        if (isset($this->mapaWizerp[$codigo])) {
            return $listas->where('nombre', $this->mapaWizerp[$codigo])->first()->id ?? null;
        }
        
        return null;
    }

    private function limpiarMonto(?string $montoRaw): ?float
    {
        if ($montoRaw === null || trim($montoRaw) === '') return null;
        $montoLimpio = trim(str_replace(['$', ','], '', $montoRaw));
        if ($montoLimpio === '-') return 0.00;
        return is_numeric($montoLimpio) ? (float) $montoLimpio : null;
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if (in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS'])) continue;
            if ($monto >= $lista->monto_requerido) return $lista->id;
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id; 
    }
}