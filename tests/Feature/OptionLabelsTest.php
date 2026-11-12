<?php

use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Fields\Field;

function optionLabels(array $fields, string $name): array
{
    $field = collect($fields)->firstWhere('name', $name);

    return array_column($field['options'], 'label', 'value');
}

it('translates plain option values, field-specific labels first', function () {
    app()->setLocale('fr');
    $definitions = Trame::definitions();
    $block = fn (string $type) => collect($definitions['blocks'])->firstWhere('type', $type)['fields'];

    expect(optionLabels($block('heading'), 'align'))->toBe(['start' => 'Début', 'center' => 'Centre', 'end' => 'Fin'])
        ->and(optionLabels($block('heading'), 'level'))->toMatchArray([1 => 'H1', 6 => 'H6'])
        ->and(optionLabels($definitions['layout']['column'], 'valign'))->toBe(['start' => 'Haut', 'center' => 'Milieu', 'end' => 'Bas'])
        ->and(optionLabels($definitions['layout']['block'], 'radius'))->toMatchArray(['none' => 'Droit', 'theme' => 'Thème', 'sm' => 'S', 'full' => 'Cercle'])
        ->and(optionLabels($definitions['layout']['block'], 'border'))->toBe(['none' => 'Aucune', 'thin' => 'Fine', 'thick' => 'Épaisse'])
        ->and(optionLabels($block('button'), 'size'))->toBe(['md' => 'Normale', 'lg' => 'Grande'])
        ->and(optionLabels($block('button'), 'variant'))->toBe(['solid' => 'Plein', 'outline' => 'Contour', 'link' => 'Lien'])
        ->and(optionLabels($block('callout'), 'tone'))->toBe(['info' => 'Information', 'success' => 'Succès', 'warning' => 'Attention'])
        ->and(optionLabels($block('text'), 'size'))->toBe(['base' => 'Normale', 'lead' => 'Chapô']);

    app()->setLocale('en');

    expect(optionLabels(Trame::definitions()['layout']['column'], 'valign'))->toBe(['start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom']);
});

it('keeps unknown values and explicit labels as they are', function () {
    expect(array_column(Field::select('x')->options(['foo', 'center'])->toArray()['options'], 'label'))->toBe(['foo', 'Centre'])
        ->and(array_column(Field::select('x')->options(['center' => 'Au milieu'])->toArray()['options'], 'label'))->toBe(['Au milieu']);
});
