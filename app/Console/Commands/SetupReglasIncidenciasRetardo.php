<?php

namespace App\Console\Commands;

use App\Models\CatalogoReglaIncidencia;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupReglasIncidenciasRetardo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rh:setup-reglas-retardos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura las reglas predeterminadas para retardos (medio bono y bono completo).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reglas = [
            [
                'nombre' => 'Retardo Leve (0 a 5 minutos)',
                'categoria' => CatalogoReglaIncidencia::CATEGORIA_RETARDO,
                'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_DEDUCCION_NOMINA,
                'factor_penalizacion_puntualidad' => 7.5, // Medio bono en una quincena de 15 días
                'factor_penalizacion_productividad' => 0.0,
                'aplica_deduccion_salario_base' => false,
            ],
            [
                'nombre' => 'Retardo Grave (6 minutos en adelante)',
                'categoria' => CatalogoReglaIncidencia::CATEGORIA_RETARDO,
                'tipo_comportamiento' => CatalogoReglaIncidencia::COMPORTAMIENTO_DEDUCCION_NOMINA,
                'factor_penalizacion_puntualidad' => 15.0, // Bono completo en una quincena de 15 días
                'factor_penalizacion_productividad' => 0.0,
                'aplica_deduccion_salario_base' => false,
            ],
        ];

        foreach ($reglas as $index => $datos) {
            $folio = 'REG-RET-' . ($index + 1);
            
            CatalogoReglaIncidencia::updateOrCreate(
                ['nombre' => $datos['nombre']],
                array_merge($datos, [
                    'uuid' => Str::uuid(),
                    'folio' => $folio,
                    'activo' => true,
                ])
            );
            
            $this->info("Regla configurada: {$datos['nombre']}");
        }

        $this->info('¡Reglas predeterminadas configuradas exitosamente!');
    }
}
