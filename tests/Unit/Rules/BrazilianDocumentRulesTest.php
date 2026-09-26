<?php

namespace Tests\Unit\Rules;

use App\Rules\Cnpj;
use App\Rules\Cpf;
use PHPUnit\Framework\TestCase;

class BrazilianDocumentRulesTest extends TestCase
{
    public function test_valid_cpfs_pass(): void
    {
        $this->assertTrue(Cpf::isValid('52998224725'));
        $this->assertTrue(Cpf::isValid('11144477735'));
    }

    public function test_invalid_cpfs_fail(): void
    {
        $this->assertFalse(Cpf::isValid('52998224724'), 'wrong check digit');
        $this->assertFalse(Cpf::isValid('11111111111'), 'repeated digits');
        $this->assertFalse(Cpf::isValid('529.982.247-25'), 'formatted input must be normalized first');
        $this->assertFalse(Cpf::isValid('5299822472'), 'too short');
    }

    public function test_valid_cnpjs_pass(): void
    {
        $this->assertTrue(Cnpj::isValid('11222333000181'));
        $this->assertTrue(Cnpj::isValid('11444777000161'));
    }

    public function test_invalid_cnpjs_fail(): void
    {
        $this->assertFalse(Cnpj::isValid('11222333000182'), 'wrong check digit');
        $this->assertFalse(Cnpj::isValid('00000000000000'), 'repeated digits');
        $this->assertFalse(Cnpj::isValid('1122233300018'), 'too short');
    }
}
