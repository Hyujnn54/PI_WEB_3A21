<?php

namespace App\Form;

use App\Entity\Candidate;
use App\Entity\Recruiter;
use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'] ?? null;

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => ['placeholder' => 'Enter your first name']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['placeholder' => 'Enter your last name']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['placeholder' => 'example@mail.com']
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['placeholder' => '+216 -- --- ---']
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'New Password',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Leave blank to keep current password'
                ],
            ])
        ;

        if ($data instanceof Recruiter) {
            $builder
                ->add('companyName', TextType::class, [
                    'label' => 'Company Name',
                    'required' => true,
                    'attr' => ['placeholder' => 'e.g. Talent Bridge Inc.'],
                ])
                ->add('companyLocation', TextType::class, [
                    'label' => 'Company Location',
                    'required' => false,
                    'attr' => ['placeholder' => 'e.g. Tunis, Tunisia'],
                ]);
        }

        if ($data instanceof Candidate) {
            $builder
                ->add('location', TextType::class, [
                    'label' => 'City / Location',
                    'required' => false,
                    'attr' => ['placeholder' => 'e.g. Sousse, Tunisia'],
                ])
                ->add('educationLevel', TextType::class, [
                    'label' => 'Education Level',
                    'required' => false,
                    'attr' => ['placeholder' => 'e.g. Bachelor in CS'],
                ])
                ->add('experienceYears', IntegerType::class, [
                    'label' => 'Experience (Years)',
                    'required' => false,
                    'attr' => ['min' => 1, 'max' => 60],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Users::class,
        ]);
    }
}