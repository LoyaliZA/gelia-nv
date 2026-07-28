<?php

namespace Database\Seeders;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use Illuminate\Database\Seeder;

/**
 * Catálogo SAT c_RegimenFiscal + c_UsoCFDI (completo vigente).
 * Idempotente: php artisan db:seed --class=CatalogosFiscalesSeeder --force
 */
class CatalogosFiscalesSeeder extends Seeder
{
    /** @var list<array{codigo: string, nombre: string}> */
    private const REGIMENES = [
        ['codigo' => '601', 'nombre' => 'General de Ley Personas Morales'],
        ['codigo' => '603', 'nombre' => 'Personas Morales con Fines no Lucrativos'],
        ['codigo' => '605', 'nombre' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios'],
        ['codigo' => '606', 'nombre' => 'Arrendamiento'],
        ['codigo' => '607', 'nombre' => 'Régimen de Enajenación o Adquisición de Bienes'],
        ['codigo' => '608', 'nombre' => 'Demás ingresos'],
        ['codigo' => '610', 'nombre' => 'Residentes en el Extranjero sin Establecimiento Permanente en México'],
        ['codigo' => '611', 'nombre' => 'Ingresos por Dividendos (socios y accionistas)'],
        ['codigo' => '612', 'nombre' => 'Personas Físicas con Actividades Empresariales y Profesionales'],
        ['codigo' => '614', 'nombre' => 'Ingresos por intereses'],
        ['codigo' => '615', 'nombre' => 'Régimen de los ingresos por obtención de premios'],
        ['codigo' => '616', 'nombre' => 'Sin obligaciones fiscales'],
        ['codigo' => '620', 'nombre' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos'],
        ['codigo' => '621', 'nombre' => 'Incorporación Fiscal'],
        ['codigo' => '622', 'nombre' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'],
        ['codigo' => '623', 'nombre' => 'Opcional para Grupos de Sociedades'],
        ['codigo' => '624', 'nombre' => 'Coordinados'],
        ['codigo' => '625', 'nombre' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas'],
        ['codigo' => '626', 'nombre' => 'Régimen Simplificado de Confianza'],
    ];

    /** @var list<array{codigo: string, nombre: string}> */
    private const USOS_CFDI = [
        ['codigo' => 'G01', 'nombre' => 'Adquisición de mercancías'],
        ['codigo' => 'G02', 'nombre' => 'Devoluciones, descuentos o bonificaciones'],
        ['codigo' => 'G03', 'nombre' => 'Gastos en general'],
        ['codigo' => 'I01', 'nombre' => 'Construcciones'],
        ['codigo' => 'I02', 'nombre' => 'Mobiliario y equipo de oficina por inversiones'],
        ['codigo' => 'I03', 'nombre' => 'Equipo de transporte'],
        ['codigo' => 'I04', 'nombre' => 'Equipo de cómputo y accesorios'],
        ['codigo' => 'I05', 'nombre' => 'Dados, troqueles, moldes, matrices y herramental'],
        ['codigo' => 'I06', 'nombre' => 'Comunicaciones telefónicas'],
        ['codigo' => 'I07', 'nombre' => 'Comunicaciones satelitales'],
        ['codigo' => 'I08', 'nombre' => 'Otra maquinaria y equipo'],
        ['codigo' => 'D01', 'nombre' => 'Honorarios médicos, dentales y gastos hospitalarios'],
        ['codigo' => 'D02', 'nombre' => 'Gastos médicos por incapacidad o discapacidad'],
        ['codigo' => 'D03', 'nombre' => 'Gastos funerarios'],
        ['codigo' => 'D04', 'nombre' => 'Donativos'],
        ['codigo' => 'D05', 'nombre' => 'Intereses reales efectivamente pagados por créditos hipotecarios'],
        ['codigo' => 'D06', 'nombre' => 'Aportaciones voluntarias al SAR'],
        ['codigo' => 'D07', 'nombre' => 'Primas por seguros de gastos médicos'],
        ['codigo' => 'D08', 'nombre' => 'Gastos de transportación escolar obligatoria'],
        ['codigo' => 'D09', 'nombre' => 'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones'],
        ['codigo' => 'D10', 'nombre' => 'Pagos por servicios educativos (colegiaturas)'],
        ['codigo' => 'S01', 'nombre' => 'Sin efectos fiscales'],
        ['codigo' => 'CP01', 'nombre' => 'Pagos'],
        ['codigo' => 'CN01', 'nombre' => 'Nómina'],
    ];

    public function run(): void
    {
        foreach (self::REGIMENES as $row) {
            CatalogoRegimenFiscal::query()->updateOrCreate(
                ['codigo' => $row['codigo']],
                ['nombre' => $row['nombre'], 'activo' => true]
            );
        }

        foreach (self::USOS_CFDI as $row) {
            CatalogoUsoCfdi::query()->updateOrCreate(
                ['codigo' => $row['codigo']],
                ['nombre' => $row['nombre'], 'activo' => true]
            );
        }

        // Self-check: códigos clave deben existir tras el seed.
        assert(CatalogoRegimenFiscal::query()->where('codigo', '601')->exists());
        assert(CatalogoRegimenFiscal::query()->where('codigo', '605')->exists());
        assert(CatalogoUsoCfdi::query()->where('codigo', 'G03')->exists());
        assert(CatalogoUsoCfdi::query()->where('codigo', 'S01')->exists());

        $this->command?->info(
            'Catálogos fiscales: '.count(self::REGIMENES).' regímenes, '.count(self::USOS_CFDI).' usos de CFDI.'
        );
    }
}
