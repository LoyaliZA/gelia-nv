<?php

namespace App\Console\Commands;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTag;
use App\Services\Clientes\ClasificacionComercialCliente;
use Illuminate\Console\Command;

class ReportarConversionesOmitidasCommand extends Command
{
    protected $signature = 'solicitudes:reportar-conversiones-omitidas {--csv : Salida CSV} {--limit=100 : Máximo de filas a listar}';

    protected $description = 'Lista solicitudes autorizadas de Menudeo con lista mayorista y conversión no registrada. No aplica correcciones.';

    public function handle(): int
    {
        $estados = array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]);
        if ($estados === []) {
            $this->warn('No hay estados Respondida/Verificada en el catálogo.');

            return self::SUCCESS;
        }

        $clasificacion = app(ClasificacionComercialCliente::class);
        $limit = max(1, (int) $this->option('limit'));
        $csv = (bool) $this->option('csv');
        $filas = [];

        SolicitudTag::query()
            ->with(['cliente.listaDescuento', 'listaDescuento', 'estado'])
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->where('convertir_menudeo_mayoreo', false)
            ->where('monto_cotizado', '>', 0)
            ->whereNotNull('catalogo_lista_descuento_id')
            ->orderBy('id')
            ->chunkById(200, function ($solicitudes) use ($clasificacion, $limit, &$filas) {
                foreach ($solicitudes as $solicitud) {
                    if (count($filas) >= $limit) {
                        return false;
                    }
                    $lista = $solicitud->listaDescuento;
                    $cliente = $solicitud->cliente;
                    if (! $cliente || ! $clasificacion->esListaMayorista($lista) || ! $clasificacion->esMenudeo($cliente)) {
                        continue;
                    }
                    $aplicacion = (string) ($solicitud->aplicacion_cliente ?: 'sin_registrar');
                    $efectivaMayorista = $clasificacion->esListaMayorista($cliente->listaDescuento)
                        && ! $cliente->es_inactivo
                        && ! $clasificacion->esMenudeo($cliente);
                    if ($aplicacion === 'aplicada' && $efectivaMayorista) {
                        continue;
                    }
                    $filas[] = [
                        (string) $solicitud->id,
                        (string) $cliente->numero_cliente,
                        (string) ($solicitud->estado?->nombre ?? ''),
                        (string) $solicitud->monto_cotizado,
                        (string) ($lista->nombre ?? ''),
                        $aplicacion,
                        $cliente->es_menudeo ? 'menudeo' : 'mayoreo',
                        (string) ($cliente->listaDescuento?->nombre ?? ''),
                    ];
                }
            });

        if ($filas === []) {
            $this->info('Sin solicitudes afectadas. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $encabezado = ['solicitud_id', 'numero_cliente', 'estado', 'monto_cotizado', 'lista_solicitada', 'aplicacion_cliente', 'modalidad_actual', 'lista_almacenada'];
        if ($csv) {
            $this->line(implode(',', $encabezado));
            foreach ($filas as $fila) {
                $this->line(implode(',', $fila));
            }
        } else {
            $this->table($encabezado, $filas);
        }
        $this->info('Solicitudes: '.count($filas).'. Recuperación puntual: bandeja Super Admin de aplicación al cliente. No se aplicó corrección masiva.');

        return self::SUCCESS;
    }
}
