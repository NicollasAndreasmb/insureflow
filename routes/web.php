<?php

use Illuminate\Support\Facades\Route;

/*
 * Toda navegação fora de /api é resolvida pelo Vue Router no navegador. O Laravel
 * apenas entrega a view que carrega a SPA, para que URLs como /clients/15 funcionem
 * também quando acessadas diretamente ou após um refresh.
 */
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api(/|$)|sanctum/|up$).*$')
    ->name('spa');
