<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('policies:expire')
    ->dailyAt('00:05')
    ->withoutOverlapping();
