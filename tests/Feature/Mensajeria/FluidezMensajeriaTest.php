<?php

namespace Tests\Feature\Mensajeria;

use App\Events\ConversacionLeida;
use App\Jobs\Mensajeria\ProcesarAdjuntoMensajeJob;
use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeLectura;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class FluidezMensajeriaTest extends TestCase
{
    use RefreshDatabaseSafe;

    public function test_marcar_leida_registra_el_lote_y_avisa_con_un_cursor(): void
    {
        Event::fake([ConversacionLeida::class]);

        [$lector, $autor, $conversacion] = $this->conversacionDirecta();

        foreach (range(1, 12) as $n) {
            Mensaje::query()->create([
                'conversacion_id' => $conversacion->id,
                'user_id' => $autor->id,
                'tipo' => Mensaje::TIPO_TEXTO,
                'contenido' => "Pendiente {$n}",
            ]);
        }

        $this->actingAs($lector)
            ->put('/mensajeria/conversaciones/'.$conversacion->id.'/leer')
            ->assertOk();

        $this->assertSame(12, MensajeLectura::query()->where('user_id', $lector->id)->count());

        Event::assertDispatched(ConversacionLeida::class, function (ConversacionLeida $evento) use ($autor) {
            return $evento->userId === $autor->id
                && $evento->payload['alcance'] === 'autor'
                && $evento->payload['leido_hasta_id']
                && $evento->payload['estado_lectura'] === 'leido'
                && $evento->payload['mensajes'] === [];
        });
    }

    public function test_el_adjunto_responde_antes_de_generar_la_miniatura(): void
    {
        Storage::fake('public');
        Queue::fake();

        [$autor, , $conversacion] = $this->conversacionDirecta();
        $archivo = UploadedFile::fake()->image('foto.jpg', 80, 40);

        $respuesta = $this->actingAs($autor)
            ->post('/mensajeria/conversaciones/'.$conversacion->id.'/adjuntos', [
                'archivo' => $archivo,
                'tipo' => Mensaje::TIPO_IMAGEN,
            ])
            ->assertCreated();

        $respuesta->assertJsonPath('mensaje.tipo', Mensaje::TIPO_IMAGEN);
        $respuesta->assertJsonPath('mensaje.adjuntos.0.thumbnail_url', null);
        $this->assertStringContainsString('image/', (string) $respuesta->json('mensaje.adjuntos.0.mime'));

        Queue::assertPushed(ProcesarAdjuntoMensajeJob::class);
    }

    /** @return array{0: User, 1: User, 2: Conversacion} */
    private function conversacionDirecta(): array
    {
        $lector = User::factory()->create();
        $autor = User::factory()->create();
        $conversacion = Conversacion::query()->create([
            'tipo' => Conversacion::TIPO_DIRECTO,
            'creado_por' => $autor->id,
        ]);
        ConversacionParticipante::query()->create([
            'conversacion_id' => $conversacion->id,
            'user_id' => $autor->id,
            'rol' => ConversacionParticipante::ROL_ADMIN,
        ]);
        ConversacionParticipante::query()->create([
            'conversacion_id' => $conversacion->id,
            'user_id' => $lector->id,
            'rol' => ConversacionParticipante::ROL_MIEMBRO,
        ]);

        return [$lector, $autor, $conversacion];
    }
}
