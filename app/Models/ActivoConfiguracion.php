<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivoConfiguracion extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     */
    protected $table = 'activo_configuraciones';

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'terminos_condiciones',
    ];

    /**
     * Obtener los términos y condiciones configurados o el valor por defecto.
     */
    public static function obtenerTerminos(): string
    {
        $config = static::query()->first();

        if ($config && !empty($config->terminos_condiciones)) {
            return $config->terminos_condiciones;
        }

        return "1. Recepción e Inventario: El colaborador declara recibir a su entera satisfacción el activo descrito anteriormente en las condiciones de entrega detalladas en este documento.\n2. Uso y Resguardo: El colaborador se obliga a destinar el activo única y exclusivamente para el desempeño de sus funciones laborales dentro de la empresa, comprometiéndose a mantenerlo bajo su resguardo, cuidado y en condiciones óptimas de operación.\n3. Responsabilidad por Daños: El colaborador se compromete a regresar los activos en las mismas condiciones en las que le fueron entregados, salvo por el desgaste natural derivado de su uso adecuado. En caso de presentar daños parciales o totales causados por negligencia, descuido o mal uso, el colaborador acepta la responsabilidad de cubrir la totalidad del costo de mantenimiento, reparación o reposición del activo.\n4. Devolución: El colaborador se compromete a devolver el activo de inmediato al serle requerido por el departamento correspondiente o al momento del término de la relación laboral.";
    }
}
