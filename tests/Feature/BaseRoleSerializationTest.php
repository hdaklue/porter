<?php

declare(strict_types=1);

use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;

test('BaseRole implements Arrayable interface correctly', function () {
    $admin = new TestAdmin();
    $array = $admin->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys(['name', 'level', 'label', 'description', 'plain_key'])
        ->and(array_key_exists('db_key', $array))->toBeFalse() // db_key should be excluded by default
        ->and($array['name'])->toBe('TestAdmin')
        ->and($array['level'])->toBe(10)
        ->and($array['label'])->toBe('Test Administrator')
        ->and($array['description'])->toBe('Test role with full administrative privileges')
        ->and($array['plain_key'])->toBe('test_admin');
});

test('BaseRole implements Jsonable interface correctly', function () {
    $editor = new TestEditor();
    $json = $editor->toJson();

    expect($json)->toBeString();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['name'])->toBe('TestEditor')
        ->and($decoded['level'])->toBe(5)
        ->and($decoded['plain_key'])->toBe('test_editor');
});

test('BaseRole JSON serialization with options', function () {
    $admin = new TestAdmin();
    $prettyJson = $admin->toJson(JSON_PRETTY_PRINT);

    expect($prettyJson)->toBeString()
        ->and($prettyJson)->toContain("\n")
        ->and($prettyJson)->toContain('    '); // Should have indentation

    $decoded = json_decode($prettyJson, true);
    expect($decoded['name'])->toBe('TestAdmin');
});

test('role array structure is consistent across different role types', function () {
    $admin = new TestAdmin();
    $editor = new TestEditor();

    $adminArray = $admin->toArray();
    $editorArray = $editor->toArray();

    expect(array_keys($adminArray))->toBe(array_keys($editorArray))
        ->and($adminArray['name'] !== $editorArray['name'])->toBeTrue()
        ->and($adminArray['level'] !== $editorArray['level'])->toBeTrue();
});

test('db_key is included when explicitly requested', function () {
    $admin = new TestAdmin();

    $arrayWithoutDbKey = $admin->toArray();
    $arrayWithDbKey = $admin->toArray(includeDbKey: true);

    expect(array_key_exists('db_key', $arrayWithoutDbKey))->toBeFalse()
        ->and($arrayWithDbKey)->toHaveKey('db_key')
        ->and($arrayWithDbKey['db_key'])->toBeString()
        ->and(empty($arrayWithDbKey['db_key']))->toBeFalse();
});

test('JSON serialization respects db_key parameter', function () {
    $admin = new TestAdmin();

    $jsonWithoutDbKey = $admin->toJson();
    $jsonWithDbKey = $admin->toJson(includeDbKey: true);

    $decodedWithout = json_decode($jsonWithoutDbKey, true);
    $decodedWith = json_decode($jsonWithDbKey, true);

    expect(array_key_exists('db_key', $decodedWithout))->toBeFalse()
        ->and($decodedWith)->toHaveKey('db_key')
        ->and($decodedWith['db_key'])->toBeString();
});
