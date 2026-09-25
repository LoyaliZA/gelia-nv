<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AndroidAssetLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_assetlinks_responde_cuando_hay_configuracion_android(): void
    {
        config([
            'webauthn.android.package_name' => 'mx.neobash.gelianv',
            'webauthn.android.sha256_cert_fingerprints' => [
                'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99',
            ],
        ]);

        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertOk()
            ->assertJsonPath('0.target.package_name', 'mx.neobash.gelianv')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99');
    }

    public function test_assetlinks_404_sin_huellas_configuradas(): void
    {
        config([
            'webauthn.android.package_name' => 'mx.neobash.gelianv',
            'webauthn.android.sha256_cert_fingerprints' => [],
        ]);

        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }
}
