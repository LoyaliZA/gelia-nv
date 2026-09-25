<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AndroidAssetLinksController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $package = (string) config('webauthn.android.package_name', '');
        $fingerprints = config('webauthn.android.sha256_cert_fingerprints', []);

        if ($package === '' || $fingerprints === []) {
            return response()->json([], 404);
        }

        return response()->json([
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                    'delegate_permission/common.get_login_creds',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => array_values($fingerprints),
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
