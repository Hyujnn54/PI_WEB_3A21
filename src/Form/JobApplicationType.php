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

class JobApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => true,
            ])
            ->add('cover_letter', TextareaType::class, [
                'label' => 'Cover Letter',
                'required' => true,
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
}
