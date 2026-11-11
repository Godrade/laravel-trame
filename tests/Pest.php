<?php

use Godrade\Trame\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/**
 * One section > one row > one column per given block list.
 *
 * @param  array<int, array<int, array<string, mixed>>>  $columns
 */
function trameDoc(array ...$columns): array
{
    return ['sections' => [[
        'id' => 'sec_1',
        'settings' => [],
        'rows' => [[
            'id' => 'row_1',
            'settings' => [],
            'columns' => array_map(fn (array $blocks, int $i) => [
                'id' => 'col_'.($i + 1),
                'span' => ['base' => 12, 'md' => intdiv(12, max(1, count($columns)))],
                'blocks' => $blocks,
            ], $columns, array_keys($columns)),
        ]],
    ]]];
}

function heading(array $text, array $extra = []): array
{
    return ['type' => 'heading', 'data' => ['text' => $text, ...$extra]];
}

function trameUser(): User
{
    $user = new User;
    $user->forceFill(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret'])->save();

    return $user;
}
