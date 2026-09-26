<?php

namespace Database\Seeders;

use App\Models\Insurer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seguradoras fictícias: os nomes não correspondem a empresas reais e os
 * domínios usam o TLD reservado .example.
 */
class InsurerSeeder extends Seeder
{
    private const TRADE_NAMES = [
        'Aurora Seguros',
        'Horizonte Seguradora',
        'Atlântica Proteção',
        'Serra Azul Seguros',
        'Confiança Mútua',
        'Rio Firme Seguros',
        'Vértice Seguradora',
        'Âncora Seguros',
    ];

    public function run(): void
    {
        foreach (self::TRADE_NAMES as $index => $tradeName) {
            $domain = Str::slug($tradeName).'.example';

            Insurer::factory()->create([
                'trade_name' => $tradeName,
                'legal_name' => $tradeName.' S.A.',
                'email' => "contato@{$domain}",
                'website' => "https://www.{$domain}",
                'is_active' => $index !== array_key_last(self::TRADE_NAMES),
            ]);
        }
    }
}
