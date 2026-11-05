<?php

namespace App\Tests\Twig;

use App\Repository\SettingsRepository;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AppExtensionTest extends TestCase
{
    public function testDaysFilter_usesCurrentLocale(): void
    {
        $switcher = new LocaleSwitcher('de', []);
        $twig     = new Environment(new ArrayLoader(['t' => '{{ a|days }} / {{ b|days }} / {{ c|days }}']));
        $twig->addExtension(new AppExtension($this->createStub(SettingsRepository::class), $switcher));

        $this->assertSame('2,5 / 3 / ', $twig->render('t', ['a' => 2.5, 'b' => 3.0, 'c' => null]));

        $switcher->setLocale('en');
        $this->assertSame('2.5 / 3 / ', $twig->render('t', ['a' => 2.5, 'b' => 3.0, 'c' => null]));
    }
}
