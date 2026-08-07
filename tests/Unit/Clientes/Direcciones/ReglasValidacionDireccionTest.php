<?php

namespace Tests\Unit\Clientes\Direcciones;

use App\Support\Clientes\Direcciones\ReglasValidacionDireccion;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ReglasValidacionDireccionTest extends TestCase
{
    public function test_normal_exige_calle_colonia_cp_municipio(): void
    {
        $validator = Validator::make([
            'nombre_destinatario' => 'Ana',
            'estado' => 'CDMX',
        ], ReglasValidacionDireccion::internas(false));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('calle', $validator->errors()->toArray());
        $this->assertArrayHasKey('colonia', $validator->errors()->toArray());
        $this->assertArrayHasKey('codigo_postal', $validator->errors()->toArray());
        $this->assertArrayHasKey('municipio', $validator->errors()->toArray());
    }

    public function test_irregular_pasa_sin_calle_colonia_cp_con_referencias_y_municipio(): void
    {
        $data = [
            'domicilio_irregular' => true,
            'nombre_destinatario' => 'Ana López',
            'estado' => 'Jalisco',
            'municipio' => 'Guadalajara',
            'referencias' => 'Domicilio conocido frente a la iglesia del pueblo',
        ];

        $validator = Validator::make($data, ReglasValidacionDireccion::internas(true));
        ReglasValidacionDireccion::afterIrregular($validator, true);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_irregular_falla_sin_municipio_ni_ciudad(): void
    {
        $data = [
            'domicilio_irregular' => true,
            'nombre_destinatario' => 'Ana López',
            'estado' => 'Jalisco',
            'referencias' => 'Domicilio conocido frente a la iglesia',
        ];

        $validator = Validator::make($data, ReglasValidacionDireccion::internas(true));
        ReglasValidacionDireccion::afterIrregular($validator, true);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('municipio', $validator->errors()->toArray());
    }

    public function test_irregular_falla_referencias_cortas(): void
    {
        $data = [
            'domicilio_irregular' => true,
            'nombre_destinatario' => 'Ana López',
            'estado' => 'Jalisco',
            'ciudad' => 'Zapopan',
            'referencias' => 'corto',
        ];

        $validator = Validator::make($data, ReglasValidacionDireccion::internas(true));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('referencias', $validator->errors()->toArray());
    }
}
