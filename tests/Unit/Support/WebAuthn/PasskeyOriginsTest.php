<?php

namespace Tests\Unit\Support\WebAuthn;

use App\Support\WebAuthn\PasskeyOrigins;
use Tests\TestCase;

class PasskeyOriginsTest extends TestCase
{
    public function test_agrega_origen_android_apk_key_hash(): void
    {
        config(['webauthn.origins' => 'https://gelianv.neobash.site']);
        putenv('WEBAUTHN_ANDROID_APK_KEY_HASHES=android:apk-key-hash:abc123');

        PasskeyOrigins::apply();

        $this->assertSame(
            'https://gelianv.neobash.site,android:apk-key-hash:abc123',
            config('webauthn.origins')
        );
    }
}
