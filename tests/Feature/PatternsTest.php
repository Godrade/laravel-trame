<?php

use Godrade\Trame\Document\DocumentValidator;
use Godrade\Trame\Facades\Trame;
use Godrade\Trame\Patterns\DefaultPattern;
use Godrade\Trame\Patterns\Pattern;
use Godrade\Trame\Patterns\Patterns;

class TestPattern implements Pattern
{
    public function key(): string
    {
        return 'custom';
    }

    public function label(): string
    {
        return 'Custom';
    }

    public function description(): string
    {
        return 'A host pattern';
    }

    public function content(array $locales, string $fallback): array
    {
        return ['type' => 'section', 'settings' => [], 'rows' => [['columns' => [['span' => ['base' => 12], 'blocks' => [
            ['type' => 'heading', 'data' => ['text' => array_fill_keys($locales, 'Hello')]],
        ]]]]]];
    }
}

function patternByKey(string $key): array
{
    return collect(Trame::definitions()['patterns'])->firstWhere('key', $key);
}

it('ships six default patterns in the definitions', function () {
    $patterns = Trame::definitions()['patterns'];

    expect(array_column($patterns, 'key'))->toBe(DefaultPattern::KEYS);

    foreach ($patterns as $pattern) {
        expect($pattern['label'])->not->toBe('')->not->toStartWith('trame::')
            ->and($pattern['description'])->not->toStartWith('trame::')
            ->and($pattern['content']['type'])->toBe('section')
            ->and($pattern['content']['id'])->toStartWith('sec_');
    }
});

it('builds valid documents from every pattern', function (string $key) {
    app(DocumentValidator::class)->validate(['sections' => [patternByKey($key)['content']]]);

    expect(true)->toBeTrue();
})->with(DefaultPattern::KEYS);

it('fills every configured locale', function () {
    config(['trame.locales' => ['fr', 'en', 'de'], 'trame.fallback_locale' => 'fr']);

    $hero = patternByKey('hero');
    $heading = $hero['content']['rows'][0]['columns'][0]['blocks'][0];

    expect($heading['type'])->toBe('heading')
        ->and($heading['data']['level'])->toBe(1)
        ->and($heading['data']['text'])->toHaveKeys(['fr', 'en', 'de'])
        ->and($heading['data']['text']['en'])->not->toBe($heading['data']['text']['fr'])
        ->and($heading['data']['text']['de'])->toBe($heading['data']['text']['fr']);

    app(DocumentValidator::class)->validate(['sections' => [$hero['content']]]);
});

it('uses colours of the palette only', function () {
    config(['trame.palette' => ['white', 'black']]);

    $hero = patternByKey('hero');

    expect($hero['content']['settings']['bg'] ?? null)->toBeNull();
    app(DocumentValidator::class)->validate(['sections' => [$hero['content']]]);
});

it('adds the patterns of the host', function () {
    config(['trame.patterns.extra' => [TestPattern::class]]);

    $patterns = Trame::definitions()['patterns'];
    $custom = patternByKey('custom');

    expect($patterns)->toHaveCount(7)
        ->and($custom['label'])->toBe('Custom')
        ->and($custom['content']['rows'][0]['columns'][0]['blocks'][0]['data']['text'])->toBe(['fr' => 'Hello', 'en' => 'Hello'])
        ->and($custom['content']['id'])->toStartWith('sec_');
});

it('can disable the default patterns', function () {
    config(['trame.patterns.defaults' => false, 'trame.patterns.extra' => [TestPattern::class]]);

    expect(array_column(Trame::definitions()['patterns'], 'key'))->toBe(['custom']);

    config(['trame.patterns.extra' => []]);

    expect(app(Patterns::class)->definitions())->toBe([]);
});

it('rejects an extra pattern that does not implement Pattern', function () {
    config(['trame.patterns.extra' => [stdClass::class]]);

    Trame::definitions();
})->throws(InvalidArgumentException::class);

it('gives fresh ids every time', function () {
    expect(patternByKey('cta')['content']['id'])->not->toBe(patternByKey('cta')['content']['id']);
});
