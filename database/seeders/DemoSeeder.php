<?php

namespace Database\Seeders;

use App\Services\Demo\SemillaModoDemo;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app(SemillaModoDemo::class)->sembrar();
    }
}
