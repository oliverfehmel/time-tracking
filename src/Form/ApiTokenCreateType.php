<?php

namespace App\Form;

use App\Entity\ApiToken;
use App\Security\ApiTokenManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form to create a personal API token (not mapped to the entity: the token is created by ApiTokenManager).
 */
class ApiTokenCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'api_token.name',
                'help' => 'api_token.name_help',
                'constraints' => [new NotBlank(), new Length(max: 100)],
            ])
            ->add('scope', ChoiceType::class, [
                'label' => 'api_token.scope',
                'choices' => [
                    'api_token.scope_read' => ApiToken::SCOPE_READ,
                    'api_token.scope_read_write' => ApiToken::SCOPE_READ_WRITE,
                ],
                'data' => ApiToken::SCOPE_READ,
                'constraints' => [new NotBlank(), new Choice(choices: ApiToken::SCOPES)],
            ])
            ->add('validity', ChoiceType::class, [
                'label' => 'api_token.validity',
                'choices' => [
                    'api_token.validity_30' => '30',
                    'api_token.validity_90' => '90',
                    'api_token.validity_365' => '365',
                    'api_token.validity_never' => 'never',
                ],
                'data' => '90',
                'constraints' => [new NotBlank(), new Choice(choices: ['30', '90', '365', 'never'])],
            ]);
    }

    /**
     * Converts the submitted validity choice to days (null = never expires).
     */
    public static function validityDays(string $choice): ?int
    {
        $days = $choice === 'never' ? null : (int) $choice;
        assert(in_array($days, ApiTokenManager::VALIDITY_DAYS, true));

        return $days;
    }
}
