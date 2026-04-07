<?php

namespace App\Form;

use App\Entity\Job_application;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class JobApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Phone number cannot be empty.',
                    ]),
                    new Callback([$this, 'validateTunisianPhone']),
                ],
            ])
            ->add('cover_letter', TextareaType::class, [
                'label' => 'Cover Letter',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Cover letter cannot be empty.',
                    ]),
                    new Callback([$this, 'validateCoverLetter']),
                ],
            ])
            ->add('use_profile_cv', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Use the CV from my Profile',
            ])
            ->add('cv_file', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Or upload a new CV (e.g., PDF)',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Job_application::class,
        ]);
    }

    public function validateTunisianPhone(?string $value, ExecutionContextInterface $context): void
    {
        if ($value === null || trim($value) === '') {
            return;
        }

        $cleaned = preg_replace('/[^0-9+]/', '', $value) ?? '';
        $localNumber = '';

        if (str_starts_with($cleaned, '+216') && strlen($cleaned) === 12) {
            $localNumber = substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '216') && strlen($cleaned) === 11) {
            $localNumber = substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '0') && strlen($cleaned) === 9) {
            $localNumber = substr($cleaned, 1);
        } elseif (strlen($cleaned) === 8 && ctype_digit($cleaned)) {
            $localNumber = $cleaned;
        }

        if (!preg_match('/^[259][0-9]{7}$/', $localNumber)) {
            $context->buildViolation('Please enter a valid Tunisian phone number (+216XXXXXXXX, 216XXXXXXXX, 0XXXXXXXX or XXXXXXXX).')
                ->addViolation();
        }
    }

    public function validateCoverLetter(?string $value, ExecutionContextInterface $context): void
    {
        if ($value === null || trim($value) === '') {
            return;
        }

        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 50) {
            $context->buildViolation('Cover letter must be at least 50 characters (current: ' . $length . ').')
                ->addViolation();
            return;
        }

        if ($length > 2000) {
            $context->buildViolation('Cover letter must not exceed 2000 characters (current: ' . $length . ').')
                ->addViolation();
        }
    }
}
