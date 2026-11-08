<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Services\ControlPedidos\ServirArchivoPedidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServirArchivoPedidoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function imagen(): string
    {
        $imagen = imagecreatetruecolor(2800, 900);
        for ($x = 0; $x < 2800; $x++) {
            imageline($imagen, $x, 0, $x, 899, imagecolorallocate($imagen, $x % 256, ($x * 3) % 256, ($x * 7) % 256));
        }
        ob_start();
        imagepng($imagen, null, 0);
        $contenido = ob_get_clean();
        imagedestroy($imagen);
        Storage::disk('public')->put('pedido/captura.png', $contenido);

        return $contenido;
    }

    private function respuesta(string $query = '', array $headers = [])
    {
        $request = Request::create('/archivo'.$query);
        $request->headers->add($headers);

        return app(ServirArchivoPedidoService::class)->responder($request, 'public', 'pedido/captura.png', 'captura.png', 'image/png');
    }

    public function test_miniatura_y_vista_reducen_peso_sin_modificar_el_original(): void
    {
        $original = $this->imagen();
        foreach (['miniatura' => 360, 'previa' => 2560] as $vista => $lado) {
            $response = $this->respuesta('?vista='.$vista);
            $path = $response->getFile()->getPathname();
            $this->assertSame('image/webp', $response->headers->get('Content-Type'));
            $this->assertSame($lado, getimagesize($path)[0]);
            $this->assertLessThan(strlen($original), filesize($path));
            $this->assertStringStartsWith(Storage::disk('local')->path('pedidos_vistas/'), $path);
        }
        $descarga = $this->respuesta();
        $this->assertSame($original, file_get_contents($descarga->getFile()->getPathname()));
        $this->assertStringContainsString('captura.png', $descarga->headers->get('Content-Disposition'));
        $this->assertSame($original, Storage::disk('public')->get('pedido/captura.png'));
    }

    public function test_reutiliza_derivados_y_revalida_el_cuerpo_por_etag(): void
    {
        $this->imagen();
        $primera = $this->respuesta('?vista=miniatura');
        $path = $primera->getFile()->getPathname();
        touch($path, time() - 60);
        $segunda = $this->respuesta('?vista=miniatura');
        $this->assertSame($path, $segunda->getFile()->getPathname());
        $this->assertSame(1, count(Storage::disk('local')->allFiles('pedidos_vistas')));
        $this->assertTrue($segunda->headers->hasCacheControlDirective('private'));
        $this->assertTrue($segunda->headers->hasCacheControlDirective('no-cache'));
        $tercera = $this->respuesta('?vista=miniatura', ['If-None-Match' => $segunda->getEtag()]);
        $this->assertSame(304, $tercera->getStatusCode());
        $this->assertNotSame($segunda->getEtag(), $this->respuesta()->getEtag());
    }

    public function test_pdf_se_conserva_y_admite_rangos_para_el_visor(): void
    {
        $contenido = '%PDF-1.4 documento ficticio de prueba';
        Storage::disk('local')->put('pedido.pdf', $contenido);
        $request = Request::create('/archivo?vista=miniatura');
        $request->headers->set('Range', 'bytes=0-7');
        $response = app(ServirArchivoPedidoService::class)->responder($request, 'local', 'pedido.pdf', 'pedido.pdf', 'application/pdf');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame($contenido, file_get_contents($response->getFile()->getPathname()));
        $response->prepare($request);
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 0-7/'.strlen($contenido), $response->headers->get('Content-Range'));
        $this->assertEmpty(Storage::disk('local')->allFiles('pedidos_vistas'));
    }

    public function test_imagen_no_decodificable_conserva_el_original(): void
    {
        Storage::disk('public')->put('pedido/captura.png', 'no es una imagen');
        $this->assertSame('no es una imagen', file_get_contents($this->respuesta('?vista=miniatura')->getFile()->getPathname()));
    }

    public function test_los_nombres_tecnicos_se_acortan_sin_cambiar_el_valor_guardado(): void
    {
        $nombre = 'captura-123e4567-e89b-12d3-a456-426614174000.png';
        $doc = new PedidoBmaDocumento(['pedido_bma_id' => 45, 'tipo' => 'pdf_pedido', 'nombre_original' => $nombre]);
        $doc->id = 123;
        $this->assertSame('pedido-45-123.png', $doc->nombre_original);
        $this->assertSame($nombre, $doc->getAttributes()['nombre_original']);
        $this->assertSame('pedido-45-123.png', $doc->toArray()['nombre_original']);
        $doc->nombre_original = 'Lista del cliente.png';
        $this->assertSame('Lista del cliente.png', $doc->nombre_original);
        $tarea = new PedidoBmaTareaDocumento(['pedido_bma_tarea_preparacion_id' => 9, 'tipo_evidencia' => 'evidencia_general', 'nombre_original' => str_repeat('a', 40).'.jpg']);
        $tarea->id = 77;
        $this->assertSame('evidencia-9-77.jpg', $tarea->nombre_original);
    }
}
