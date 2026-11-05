<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * JSON merge patch for PATCH endpoints: the current state (as input DTO) is overlaid with the fields that were sent,
 * so PATCH and POST share one input class and one set of rules.
 *
 * Field rules are only enforced for the fields that were sent: existing data created through the UI or the console
 * (e.g. an absence type key with spaces) must not block changes of other fields. Cross-field rules (Callback
 * constraints, e.g. "end after start") always apply to the merged result.
 */
final readonly class ApiPayload
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @template T of object
     * @param T $current input DTO filled with the resource's current values
     * @return array{0: T, 1: list<string>} the merged and validated input and the names of the fields that were sent
     */
    public function merge(Request $request, object $current): array
    {
        $mimeType = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]));
        if ($request->getContentTypeFormat() !== 'json' && $mimeType !== 'application/merge-patch+json') {
            throw new ApiProblem(415, 'unsupported_media_type');
        }

        try {
            $patch = $request->toArray();
        } catch (JsonException) {
            throw new ApiProblem(400, 'bad_request');
        }

        $sent = array_map('strval', array_keys($patch));
        $data = array_merge((array) $this->normalizer->normalize($current), $patch);

        try {
            $input = $this->denormalizer->denormalize($data, $current::class, null, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $e) {
            throw new ValidationFailedException($data, $this->typeViolations($e, $data));
        }

        $violations = new ConstraintViolationList();
        foreach ($this->validator->validate($input) as $violation) {
            if ($violation->getConstraint() instanceof Callback || in_array($this->rootProperty($violation), $sent, true)) {
                $violations->add($violation);
            }
        }

        if (count($violations) > 0) {
            throw new ValidationFailedException($input, $violations);
        }

        return [$input, $sent];
    }

    /**
     * Same message (translated, domain "validators") as #[MapRequestPayload] uses for type errors on POST.
     *
     * @param array<string,mixed> $data
     */
    private function typeViolations(PartialDenormalizationException $e, array $data): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        foreach ($e->getNotNormalizableValueErrors() as $error) {
            $template   = 'This value should be of type {{ type }}.';
            $parameters = ['{{ type }}' => implode('|', $error->getExpectedTypes() ?? ['?'])];

            $violations->add(new ConstraintViolation(
                $this->translator->trans($template, $parameters, 'validators'),
                $template,
                $parameters,
                $data,
                (string) $error->getPath(),
                null,
            ));
        }

        return $violations;
    }

    private function rootProperty(ConstraintViolationInterface $violation): string
    {
        return (string) preg_split('/[.\[]/', $violation->getPropertyPath())[0];
    }
}
