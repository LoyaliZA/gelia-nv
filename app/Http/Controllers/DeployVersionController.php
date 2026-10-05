<?php

namespace App\Http\Controllers;

use App\Support\GeliaBuildVersion;
use Illuminate\Http\JsonResponse;

class DeployVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['version' => GeliaBuildVersion::actual()])
            ->header('Cache-Control', 'no-store');
    }
}
