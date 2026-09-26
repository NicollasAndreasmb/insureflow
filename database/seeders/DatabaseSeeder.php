<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Popula o ambiente de desenvolvimento com dados fictícios e coerentes.
 *
 * A ordem respeita as dependências entre as tabelas. As datas são relativas ao
 * dia da execução, para que o dashboard sempre tenha vencimentos próximos,
 * parcelas atrasadas e sinistros recentes. O Faker recebe uma semente fixa
 * para que duas execuções no mesmo dia gerem os mesmos dados.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Development seeders must not run in production.');
        }

        fake()->seed(20260926);

        $this->call([
            UserSeeder::class,
            InsuranceProductSeeder::class,
            InsurerSeeder::class,
            ClientSeeder::class,
            PolicySeeder::class,
            ClaimSeeder::class,
        ]);
    }
}
