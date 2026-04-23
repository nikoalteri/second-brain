<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Support\Localization\SupportedLocales;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FallbackBehaviorTest extends TestCase
{
    #[Test]
    public function supported_locales_falls_back_to_english_for_unknown_values(): void
    {
        $this->assertSame('en', SupportedLocales::appLocale(null));
        $this->assertSame('en', SupportedLocales::appLocale('fr'));
        $this->assertSame('it', SupportedLocales::appLocale('it'));
    }

    #[Test]
    public function supported_locales_maps_browser_locales_from_app_locales(): void
    {
        $this->assertSame('en-US', SupportedLocales::browserLocale('en'));
        $this->assertSame('it-IT', SupportedLocales::browserLocale('it'));
        $this->assertSame('en-US', SupportedLocales::browserLocale('fr'));
    }
}
