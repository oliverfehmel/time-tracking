<?php

namespace App\Service;

use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A business rule was violated. Thrown by the domain services and rendered by the UI (form error / flash message)
 * or the API (problem response) — so both report the same rule with the same message.
 */
final class DomainRuleViolation extends RuntimeException
{
    public const int UNPROCESSABLE = 422;
    public const int CONFLICT      = 409;
    public const int FORBIDDEN     = 403;

    /**
     * @param string                          $rule         machine-readable code, e.g. "overlaps_existing"
     * @param string                          $messageKey   translation key (domain "messages")
     * @param array<string,string|int|float>  $parameters   translation parameters; floats are day counts and are
     *                                                      formatted with {@see DayFormatter} for the target locale
     * @param string|null                     $propertyPath input field the rule refers to, if any
     */
    public function __construct(
        public readonly string $rule,
        public readonly string $messageKey,
        public readonly array $parameters = [],
        public readonly int $status = self::UNPROCESSABLE,
        public readonly ?string $propertyPath = null,
    ) {
        parent::__construct($messageKey);
    }

    /**
     * Builds a violation from a translation key like "absence.request.end_before_start"; the rule is its last segment.
     */
    public static function fromKey(string $messageKey, int $status = self::UNPROCESSABLE, ?string $propertyPath = null): self
    {
        $segments = explode('.', $messageKey);

        return new self(end($segments), $messageKey, [], $status, $propertyPath);
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $locale ??= method_exists($translator, 'getLocale') ? $translator->getLocale() : 'de';

        $parameters = [];
        foreach ($this->parameters as $name => $value) {
            $parameters['%' . $name . '%'] = is_float($value) ? DayFormatter::format($value, $locale) : (string) $value;
        }

        return $translator->trans($this->messageKey, $parameters, 'messages', $locale);
    }
}
