<?php

// V23 : les migrations 1 à 4 passent sur une base vide, et migrate:fresh les rejoue.

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

const TABLES_DU_SOCLE = [
    'utilisateurs', 'profils_climatiques', 'familles_botaniques',
    'jardins', 'especes',
    'adhesions', 'types_intervention', 'zones', 'regles_reference',
    'plantes', 'compteurs_codes_courts',
];

it('rejoue les migrations du socle deux fois, et les tables existent', function () {
    expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0);
    expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0);

    foreach (TABLES_DU_SOCLE as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table {$table} absente");
    }
});

it('ne garde du squelette que les tables techniques', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::getColumnType('sessions', 'user_id'))->toBe('uuid');
});
