<?php

namespace App\Contracts\ApiExterna;

use App\Models\ApiAplicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ApiResourceHandler
{
    public function slug(): string;

    public function list(ApiAplicacion $app, Request $request): JsonResponse;

    public function show(ApiAplicacion $app, Request $request, string $id): JsonResponse;
}
