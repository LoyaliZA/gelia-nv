<?php

namespace Tests\Unit\Services\Listados;

use App\Services\Listados\PorcentajesListadoService;
use PHPUnit\Framework\TestCase;

class SettingKeyParaListaTest extends TestCase
{
    public function test_empareja_listas_de_listado_sin_confundir_plata_con_plataformas(): void
    {
        $this->assertSame('pct_plata', PorcentajesListadoService::settingKeyParaLista('MAYOREO PLATA'));
        $this->assertSame('pct_plataformas', PorcentajesListadoService::settingKeyParaLista('PLATAFORMAS'));
        $this->assertSame('pct_tiendanubemayoreo', PorcentajesListadoService::settingKeyParaLista('TiendaNube Mayoreo'));
        $this->assertSame('pct_tiendanube', PorcentajesListadoService::settingKeyParaLista('Lista TiendaNube'));
        $this->assertSame('pct_bronce', PorcentajesListadoService::settingKeyParaLista('MAYOREO BRONCE'));
        $this->assertSame('pct_oro', PorcentajesListadoService::settingKeyParaLista('MAYOREO ORO'));
        $this->assertSame('pct_diamante', PorcentajesListadoService::settingKeyParaLista('MAYOREO DIAMANTE'));
        $this->assertNull(PorcentajesListadoService::settingKeyParaLista('PUBLICO GENERAL'));
    }
}
