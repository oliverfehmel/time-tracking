<?php

namespace App\Twig;

use App\Repository\SettingsRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'settings' => $this->settingsRepository->getOrCreate(),
        ];
    }
}
