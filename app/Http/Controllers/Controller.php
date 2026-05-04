<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Con esta simple línea, todos tus controladores heredan el método $this->authorize()
    use AuthorizesRequests, ValidatesRequests;
}