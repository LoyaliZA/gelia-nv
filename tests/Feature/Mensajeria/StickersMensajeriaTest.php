<?php

namespace Tests\Feature\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeriaSticker;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class StickersMensajeriaTest extends TestCase
{
    use RefreshDatabaseSafe;

    public function test_crea_envia_y_rechaza_el_sticker_de_otra_cuenta(): void
    {
        Storage::fake('public');

        $propia = User::factory()->create();
        $ajena = User::factory()->create();
        $conversacion = Conversacion::query()->create([
            'tipo' => Conversacion::TIPO_DIRECTO,
            'creado_por' => $propia->id,
        ]);
        ConversacionParticipante::query()->create([
            'conversacion_id' => $conversacion->id,
            'user_id' => $propia->id,
            'rol' => ConversacionParticipante::ROL_ADMIN,
        ]);
        ConversacionParticipante::query()->create([
            'conversacion_id' => $conversacion->id,
            'user_id' => $ajena->id,
            'rol' => ConversacionParticipante::ROL_MIEMBRO,
        ]);

        $archivo = UploadedFile::fake()->image('sticker.png', 80, 40);

        $creado = $this->actingAs($propia)
            ->post('/mensajeria/stickers', ['archivo' => $archivo])
            ->assertCreated()
            ->json('sticker');

        $this->assertNotEmpty($creado['id']);
        $this->assertNotEmpty($creado['url']);
        $sticker = MensajeriaSticker::query()->findOrFail($creado['id']);
        Storage::disk('public')->assertExists($sticker->ruta);
        $this->assertSame($propia->id, $sticker->user_id);

        $this->actingAs($propia)
            ->post('/mensajeria/conversaciones/'.$conversacion->id.'/stickers', [
                'sticker_id' => $sticker->id,
            ])
            ->assertCreated()
            ->assertJsonPath('mensaje.tipo', Mensaje::TIPO_STICKER)
            ->assertJsonPath('mensaje.adjuntos.0.mime', $sticker->mime);

        $this->assertDatabaseHas('mensajes', [
            'conversacion_id' => $conversacion->id,
            'tipo' => Mensaje::TIPO_STICKER,
            'user_id' => $propia->id,
        ]);
        $this->assertSame('Sticker', $conversacion->fresh()->ultimo_mensaje_preview);

        $this->actingAs($ajena)
            ->post('/mensajeria/conversaciones/'.$conversacion->id.'/stickers', [
                'sticker_id' => $sticker->id,
            ])
            ->assertForbidden();

        $this->actingAs($ajena)
            ->delete('/mensajeria/stickers/'.$sticker->id)
            ->assertForbidden();
    }
}
