<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::factory()->count(60)->individual()->create();
        Client::factory()->count(20)->company()->create();
        Client::factory()->count(3)->individual()->inactive()->create();
        Client::factory()->company()->inactive()->create();
    }
}
