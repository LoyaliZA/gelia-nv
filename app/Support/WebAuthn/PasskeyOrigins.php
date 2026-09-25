<?php

namespace App\Support\WebAuthn;

class PasskeyOrigins
{
    public static function apply(): void
    {
        $current = config('webauthn.origins');
        $origins = is_array($current)
            ? $current
            : array_filter(array_map('trim', explode(',', (string) $current)));

        $extra = (string) env('WEBAUTHN_ANDROID_APK_KEY_HASHES', '');
        foreach (array_filter(array_map('trim', explode(',', $extra))) as $hash) {
            $origins[] = str_starts_with($hash, 'android:apk-key-hash:')
                ? $hash
                : 'android:apk-key-hash:'.$hash;
        }

        if ($origins === []) {
            return;
        }

        config(['webauthn.origins' => implode(',', array_values(array_unique($origins)))]);
    }
}
