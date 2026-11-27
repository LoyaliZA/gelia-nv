<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\ImpresionGuiaCedis;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\ColaImpresionGuiasCedis;
use App\Services\ControlPedidos\CombinarPdfGuiasCedis;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ImpresionGuiasCedisTest extends TestCase
{
    public function test_solo_admite_el_pdf_confirmado_y_vigente_de_la_caja_del_pedido(): void
    {
        $cola = new ColaImpresionGuiasCedis;
        $version = new ImpresionGuiaCedis(['pedido_bma_id' => 5, 'pedido_bma_caja_id' => 2]);
        $pdf = new PedidoBmaDocumento([
            'pedido_bma_id' => 5,
            'pedido_bma_caja_id' => 2,
            'tipo' => PedidoBmaDocumento::TIPO_GUIA,
            'activo' => true,
            'relacion_tipo' => GuiasPdfPorCaja::AVANCE,
        ]);
        $caja = new PedidoBmaCaja(['pedido_bma_id' => 5, 'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA]);
        $version->setRelation('documento', $pdf)->setRelation('caja', $caja);

        $this->assertFalse($cola->vigente($version));
        $pdf->relacion_tipo = GuiasPdfPorCaja::CONFIRMADA;
        $this->assertTrue($cola->vigente($version));
        $pdf->pedido_bma_caja_id = 3;
        $this->assertFalse($cola->vigente($version));
        $pdf->pedido_bma_caja_id = 2;
        $caja->estado_operativo = PedidoBmaCaja::ESTADO_RETIRADA;
        $this->assertFalse($cola->vigente($version));
        $caja->estado_operativo = PedidoBmaCaja::ESTADO_ACTIVA;
        $pdf->activo = false;
        $this->assertFalse($cola->vigente($version));
    }

    public function test_la_version_general_historica_conserva_su_copia_privada(): void
    {
        $version = new ImpresionGuiaCedis(['pedido_bma_id' => 5, 'ruta_copia' => 'impresion_guias_cedis/5/original.pdf']);
        $version->setRelation('documento', null);
        $cola = new ColaImpresionGuiasCedis;

        $this->assertFalse($cola->vigente($version));
        $this->assertSame(['local', 'impresion_guias_cedis/5/original.pdf'], $cola->archivo($version));
    }

    public function test_qpdf_copia_paginas_en_orden_sin_cambiar_formatos_ni_originales(): void
    {
        if (! (new ExecutableFinder)->find('qpdf')) {
            $this->markTestSkipped('La imagen que ejecuta la prueba requiere qpdf.');
        }

        $primero = $this->pdfTemporal(288, 432, 0);
        $segundo = $this->pdfTemporal(612, 792, 90);
        $originales = array_map('file_get_contents', [$primero, $segundo]);
        $destino = null;
        try {
            $destino = (new CombinarPdfGuiasCedis)->ejecutar([$primero, $segundo]);
            $paginas = new Process(['qpdf', '--show-pages', $destino]);
            $paginas->mustRun();
            preg_match_all('/page \d+: (\d+) \d+ R/', $paginas->getOutput(), $objetos);
            $this->assertCount(2, $objetos[1]);
            foreach ([[288, 432, 0], [612, 792, 90]] as $i => [$ancho, $alto, $rotacion]) {
                $objeto = new Process(['qpdf', '--show-object='.$objetos[1][$i], $destino]);
                $objeto->mustRun();
                $this->assertStringContainsString("/MediaBox [ 0 0 {$ancho} {$alto} ]", $objeto->getOutput());
                $this->assertStringContainsString("/Rotate {$rotacion}", $objeto->getOutput());
            }
            $this->assertSame($originales, array_map('file_get_contents', [$primero, $segundo]));
        } finally {
            unlink($primero);
            unlink($segundo);
            if ($destino !== null) {
                unlink($destino);
            }
        }
    }

    private function pdfTemporal(int $ancho, int $alto, int $rotacion): string
    {
        $documento = "%PDF-1.4\n";
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$ancho} {$alto}] /Rotate {$rotacion} /Resources << >> /Contents 4 0 R >>",
            "<< /Length 0 >>\nstream\nendstream",
        ];
        $referencias = "0000000000 65535 f \n";
        foreach ($objetos as $indice => $objeto) {
            $referencias .= sprintf("%010d 00000 n \n", strlen($documento));
            $documento .= ($indice + 1)." 0 obj\n{$objeto}\nendobj\n";
        }
        $xref = strlen($documento);
        $documento .= "xref\n0 5\n{$referencias}trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        $ruta = tempnam(sys_get_temp_dir(), 'cedis-prueba-');
        file_put_contents($ruta, $documento);

        return $ruta;
    }
}
