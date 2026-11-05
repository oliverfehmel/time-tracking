<?php

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RejectInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 2000)]
    public string $reason = '';
}
