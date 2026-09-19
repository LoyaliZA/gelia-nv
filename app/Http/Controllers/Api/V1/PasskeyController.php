<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Passkeys\PasskeyLoginOptionsRequest;
use App\Http\Requests\Api\V1\Passkeys\PasskeyLoginVerifyRequest;
use App\Http\Requests\Api\V1\Passkeys\PasskeyRegisterOptionsRequest;
use App\Http\Requests\Api\V1\Passkeys\PasskeyRegisterRequest;
use App\Http\Requests\Api\V1\Passkeys\PasskeyUpdateRequest;
use App\Models\User;
use App\Services\Auth\WebAuthnCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PasskeyController extends Controller
{
    public function __construct(
        protected WebAuthnCredentialService $passkeys
    ) {}

    public function registerOptions(PasskeyRegisterOptionsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->passkeys->opcionesRegistro($user, $request));
    }

    public function register(PasskeyRegisterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json($this->passkeys->registrar($user, $request->validated(), $request), 201);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function loginOptions(PasskeyLoginOptionsRequest $request): JsonResponse
    {
        return response()->json($this->passkeys->opcionesLogin($request->validated()));
    }

    public function loginVerify(PasskeyLoginVerifyRequest $request): JsonResponse
    {
        try {
            return response()->json($this->passkeys->verificarLogin($request->validated(), $request));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->passkeys->listar($user)]);
    }

    public function destroy(Request $request, string $passkey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        try {
            $this->passkeys->revocar($user, $passkey);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['message' => 'Credencial revocada.']);
    }

    public function update(PasskeyUpdateRequest $request, string $passkey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->passkeys->renombrar($user, $passkey, $request->validated('nickname')));
    }
}
