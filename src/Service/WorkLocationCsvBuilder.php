<?php

namespace App\Service;

final class WorkLocationCsvBuilder
{
    public function build(array $months): string
    {
        $counts = [];
        foreach ($months as $month) {
            foreach ($month['days'] as $day) {
                if ($day['netSeconds'] > 0 && $day['workLocationName'] !== null) {
                    $name = $day['workLocationName'];
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        $rows = ["\xEF\xBB\xBF" . "Arbeitsort,Tage\r\n"];
        foreach ($counts as $name => $days) {
            $rows[] = $this->escapeValue($name) . ',' . $days . "\r\n";
        }

        return implode('', $rows);
    }

    private function escapeValue(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
