<?php

namespace App\Support;

/**
 * Monta padrões de busca "contém" para LIKE/ILIKE.
 *
 * O termo já chega ao banco como parâmetro (sem risco de SQL injection), mas
 * % e _ digitados pelo usuário seriam interpretados como curingas: buscar "%"
 * listaria tudo e "_" casaria qualquer caractere. Eles são escapados com a
 * barra invertida, o caractere de escape padrão do LIKE no PostgreSQL.
 */
final class Like
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '\%_').'%';
    }
}
