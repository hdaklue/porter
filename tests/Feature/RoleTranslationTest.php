<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\Roles\Admin;
use Hdaklue\Porter\Roles\Editor;
use Hdaklue\Porter\Roles\Guest;
use Hdaklue\Porter\Roles\Manager;
use Hdaklue\Porter\Tests\TestCase;
use Illuminate\Support\Facades\App;

class RoleTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure translations are loaded
        $this->app['translator']->addNamespace('porter', __DIR__.'/../../lang');
    }

    public function test_role_labels_work_with_english_translations(): void
    {
        App::setLocale('en');

        $admin = new Admin();
        $manager = new Manager();
        $editor = new Editor();
        $guest = new Guest();

        expect($admin->getLabel())->toBe('Administrator');
        expect($manager->getLabel())->toBe('Manager');
        expect($editor->getLabel())->toBe('Editor');
        expect($guest->getLabel())->toBe('Guest');
    }

    public function test_role_descriptions_work_with_english_translations(): void
    {
        App::setLocale('en');

        $admin = new Admin();
        $manager = new Manager();
        $editor = new Editor();
        $guest = new Guest();

        expect($admin->getDescription())->toBe('Full system access with all administrative privileges');
        expect($manager->getDescription())->toBe('Manages teams and resources with elevated permissions');
        expect($editor->getDescription())->toBe('Creates and modifies content with publishing capabilities');
        expect($guest->getDescription())->toBe('Limited access for temporary or anonymous users');
    }

    public function test_role_labels_work_with_arabic_translations(): void
    {
        App::setLocale('ar');

        $admin = new Admin();
        $manager = new Manager();
        $editor = new Editor();
        $guest = new Guest();

        expect($admin->getLabel())->toBe('مدير النظام');
        expect($manager->getLabel())->toBe('مدير');
        expect($editor->getLabel())->toBe('محرر');
        expect($guest->getLabel())->toBe('ضيف');
    }

    public function test_role_descriptions_work_with_arabic_translations(): void
    {
        App::setLocale('ar');

        $admin = new Admin();
        $manager = new Manager();
        $editor = new Editor();
        $guest = new Guest();

        expect($admin->getDescription())->toBe('وصول كامل للنظام مع جميع صلاحيات الإدارة');
        expect($manager->getDescription())->toBe('إدارة الفرق والموارد مع صلاحيات مرتفعة');
        expect($editor->getDescription())->toBe('إنشاء وتعديل المحتوى مع إمكانيات النشر');
        expect($guest->getDescription())->toBe('وصول محدود للمستخدمين المؤقتين أو المجهولين');
    }

    public function test_role_translation_fallback_behavior(): void
    {
        // Test with non-existent locale
        App::setLocale('fr');

        $admin = new Admin();

        // Should fall back to the translation key if no translation exists
        $label = $admin->getLabel();

        // If no French translation exists, Laravel will return the key or fallback to English
        expect($label)->toBeString();
        expect($label)->not()->toBeEmpty();
    }

    public function test_role_array_representation_includes_translated_labels(): void
    {
        App::setLocale('en');

        $admin = new Admin();
        $array = $admin->toArray();

        expect($array)->toHaveKey('label');
        expect($array)->toHaveKey('description');
        expect($array['label'])->toBe('Administrator');
        expect($array['description'])->toBe('Full system access with all administrative privileges');
        expect($array['name'])->toBe('admin');
        expect($array['level'])->toBe(6);
    }

    public function test_role_json_representation_includes_translated_labels(): void
    {
        App::setLocale('en');

        $admin = new Admin();
        $json = json_decode($admin->toJson(), true);

        expect($json)->toHaveKey('label');
        expect($json)->toHaveKey('description');
        expect($json['label'])->toBe('Administrator');
        expect($json['description'])->toBe('Full system access with all administrative privileges');
        expect($json['name'])->toBe('admin');
        expect($json['level'])->toBe(6);
    }
}
