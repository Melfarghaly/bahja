<?php

/*
|--------------------------------------------------------------------------
| Architecture rules (CLAUDE.md)
|--------------------------------------------------------------------------
| Validation lives in Form Requests, business logic in Service classes, and
| controllers only orchestrate. These tests keep the layers honest.
*/

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('controllers stay thin: no raw database access or inline validation')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Validator',
    ]);

arch('form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->classes()
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('services never depend on the HTTP layer')
    ->expect('App\Services')
    ->not->toUse([
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
    ]);

arch('models never depend on services or the HTTP layer')
    ->expect('App\Models')
    ->not->toUse(['App\Services', 'App\Http']);

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();
