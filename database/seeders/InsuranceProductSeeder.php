<?php

namespace Database\Seeders;

use App\Models\InsuranceProduct;
use Illuminate\Database\Seeder;

class InsuranceProductSeeder extends Seeder
{
    public const PRODUCTS = [
        'seguro-auto' => ['Seguro Auto', 'Proteção para veículos contra colisão, roubo, furto e danos a terceiros.'],
        'seguro-residencial' => ['Seguro Residencial', 'Cobertura para imóveis residenciais contra incêndio, danos elétricos e roubo.'],
        'seguro-de-vida' => ['Seguro de Vida', 'Indenização aos beneficiários em caso de morte ou invalidez do segurado.'],
        'seguro-empresarial' => ['Seguro Empresarial', 'Proteção patrimonial e de responsabilidade civil para empresas.'],
        'seguro-viagem' => ['Seguro Viagem', 'Assistência médica e cobertura de bagagem durante viagens.'],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $slug => [$name, $description]) {
            InsuranceProduct::factory()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        }
    }
}
