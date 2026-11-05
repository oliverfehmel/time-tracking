<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * JSON merge patch for PATCH endpoints: the current state (as input DTO) is overlaid with the fields that were sent
 * and validated as a whole — so PATCH and POST share one input class and one set of rules.
 */
final readonly class ApiPayload
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * @template T of object
     * @param T $current input DTO filled with the resource's current values
     * @return array{0: T, 1: list<string>} the merged and validated input and the names of the fields that were sent
     */
    public function merge(Request $request, object $current): array
    {
        if ($request->getContentTypeFormat() !== 'json') {
            throw new ApiProblem(415, 'unsupported_media_type');
        }

        try {
            $patch = $request->toArray();
        } catch (JsonException) {
            throw new ApiProblem(400, 'bad_request');
        }

        $data = array_merge((array) $this->normalizer->normalize($current), $patch);

        try {
            $input = $this->denormalizer->denormalize($data, $current::class, null, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $e) {
            throw new ApiProblem(422, 'validation_failed', violations: array_map(
                static fn(NotNormalizableValueException $error) => [
                    'propertyPath' => (string) $error->getPath(),
                    'message'      => sprintf('This value should be of type %s.', implode('|', $error->getExpectedTypes() ?? ['?'])),
                    'code'         => 'type_error',
                ],
                $e->getNotNormalizableValueErrors(),
            ));
        }

        $violations = $this->validator->validate($input);
        if (count($violations) > 0) {
            throw new ValidationFailedException($input, $violations);
        }

        return [$input, array_keys($patch)];
    }
}
