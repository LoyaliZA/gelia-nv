<?php

namespace Database\Seeders;

use App\Models\ApiCampoRecurso;
use App\Models\ApiRecurso;
use Illuminate\Database\Seeder;

class ApiExternaSeeder extends Seeder
{
    public function run(): void
    {
        $recurso = ApiRecurso::firstOrCreate(
            ['slug' => 'clientes'],
            [
                'nombre' => 'Clientes',
                'descripcion' => 'Base de clientes del sistema',
                'activo' => true,
                'lectura_habilitada' => true,
                'escritura_habilitada' => false,
            ]
        );

        $campos = [
            ['slug' => 'numero_cliente', 'etiqueta' => 'Número de cliente', 'es_sensible' => false, 'orden' => 1],
            ['slug' => 'nombre', 'etiqueta' => 'Nombre', 'es_sensible' => false, 'orden' => 2],
            ['slug' => 'nombre_razon_social', 'etiqueta' => 'Razón social', 'es_sensible' => false, 'orden' => 3],
            ['slug' => 'rfc', 'etiqueta' => 'RFC', 'es_sensible' => true, 'orden' => 4],
            ['slug' => 'codigo_postal', 'etiqueta' => 'Código postal', 'es_sensible' => true, 'orden' => 5],
            ['slug' => 'regimen_fiscal', 'etiqueta' => 'Régimen fiscal', 'es_sensible' => true, 'orden' => 6],
            ['slug' => 'correo_electronico', 'etiqueta' => 'Correo electrónico', 'es_sensible' => true, 'orden' => 7],
            ['slug' => 'uso_factura', 'etiqueta' => 'Uso de factura', 'es_sensible' => true, 'orden' => 8],
            ['slug' => 'lista_descuento', 'etiqueta' => 'Lista de descuento', 'es_sensible' => false, 'orden' => 9],
            ['slug' => 'vendedor', 'etiqueta' => 'Vendedor', 'es_sensible' => false, 'orden' => 10],
            ['slug' => 'monto_venta_actual', 'etiqueta' => 'Monto venta actual', 'es_sensible' => false, 'orden' => 11],
            ['slug' => 'es_heredado', 'etiqueta' => 'Es heredado', 'es_sensible' => false, 'orden' => 12],
            ['slug' => 'tipo_cliente', 'etiqueta' => 'Tipo de cliente', 'es_sensible' => false, 'orden' => 13],
            ['slug' => 'lista_bloqueada', 'etiqueta' => 'Lista bloqueada', 'es_sensible' => false, 'orden' => 14],
        ];

        foreach ($campos as $campo) {
            ApiCampoRecurso::updateOrCreate(
                [
                    'api_recurso_id' => $recurso->id,
                    'slug' => $campo['slug'],
                ],
                [
                    'etiqueta' => $campo['etiqueta'],
                    'es_sensible' => $campo['es_sensible'],
                    'habilitado_global' => true,
                    'orden' => $campo['orden'],
                ]
            );
        }
    }
}
