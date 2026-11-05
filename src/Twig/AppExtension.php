<?php

namespace App\Twig;

use App\Repository\SettingsRepository;
use App\Service\DayFormatter;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'settings' => $this->settingsRepository->getOrCreate(),
        ];
    }

    public function getFilters(): array
    {
        return [
            // Tageswerte lokalisiert ausgeben: 3 → „3“, 2.5 → „2,5“ (de) bzw. „2.5“ (en)
            new TwigFilter('days', fn (int|float|null $days): string => DayFormatter::format($days, $this->localeSwitcher->getLocale())),
        ];
    }
}
