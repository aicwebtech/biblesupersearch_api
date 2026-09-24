<?php

namespace Tests\Feature\Lang;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every locale carries every key in resources/lang/en/basic.php.
 *
 * Bible::getCopyrightStatement() labels the copyright line with __('basic.copyright'), and a
 * key missing from a locale does not fail - Laravel answers with the key itself, so the
 * statement would read 'basic.copyright (c) 1611' for that language and nothing would say so.
 * The same is true of every other key in this file, which is why the check is over all of
 * them rather than only the one that prompted it.
 *
 * The keys are compared, not the values: a translation identical to the English one is a
 * legitimate answer in a language that borrows the word.
 */
class BasicTranslationsTest extends TestCase
{
    /**
     * The lang directory, resolved from this file rather than through the path helpers.
     *
     * PHPUnit calls a data provider before setUp() has booted the application, so a provider
     * cannot use them.
     */
    private static function langPath(string $relative = ''): string
    {
        return __DIR__ . '/../../../resources/lang' . ($relative === '' ? '' : '/' . $relative);
    }

    /** @return string[] The locale directories holding a basic.php */
    private static function locales(): array
    {
        $locales = [];

        foreach(glob(self::langPath('*'), GLOB_ONLYDIR) as $dir) {
            if(is_file($dir . '/basic.php')) {
                $locales[] = basename($dir);
            }
        }

        sort($locales);

        return $locales;
    }

    public static function localeDataProvider(): array
    {
        $cases = [];

        foreach(self::locales() as $locale) {
            $cases[$locale] = [$locale];
        }

        return $cases;
    }

    /** @param string $locale */
    #[DataProvider('localeDataProvider')]
    public function testEveryLocaleHasEveryBasicKey(string $locale): void
    {
        $expected = array_keys(require self::langPath('en/basic.php'));
        $actual   = array_keys(require self::langPath($locale . '/basic.php'));

        $this->assertSame(
            [],
            array_values(array_diff($expected, $actual)),
            $locale . '/basic.php is missing keys present in en/basic.php'
        );
    }

    /**
     * A key resolves to a translation rather than to its own name, which is what Laravel
     * answers when the key is absent.
     *
     * @param string $locale
     */
    #[DataProvider('localeDataProvider')]
    public function testTheCopyrightLabelResolvesInEveryLocale(string $locale): void
    {
        $original = app()->getLocale();

        try {
            app()->setLocale($locale);

            $label = __('basic.copyright');

            $this->assertNotSame('basic.copyright', $label, $locale . ' does not translate basic.copyright');
            $this->assertNotSame('', trim($label), $locale . ' translates basic.copyright to nothing');
        }
        finally {
            app()->setLocale($original);
        }
    }

    /** The template is what a new locale is copied from, so it has to carry the keys too. */
    public function testTheTemplateCarriesEveryKey(): void
    {
        $this->assertContains('template', self::locales(), 'The lang template is missing its basic.php');
    }

    /** Guards the provider itself: an empty glob would make every test above vacuous. */
    public function testTheLocaleListIsNotEmpty(): void
    {
        $this->assertGreaterThan(40, count(self::locales()));
    }
}
