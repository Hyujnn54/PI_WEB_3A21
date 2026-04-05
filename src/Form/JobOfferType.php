<?php

namespace App\Form;

use App\Entity\Job_offer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Offer Title',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g., Senior Symfony Developer'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 5],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'attr' => ['class' => 'form-control', 'placeholder' => 'City or Remote'],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'attr' => ['class' => 'form-control', 'step' => 'any'],
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'attr' => ['class' => 'form-control', 'step' => 'any'],
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('contract_type', ChoiceType::class, [
                'label' => 'Contract Type',
                'choices' => [
                    'Full-time' => 'Full-time',
                    'Part-time' => 'Part-time',
                    'Contract' => 'Contract',
                    'Internship' => 'Internship',
                    'Freelance' => 'Freelance',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('deadline', DateTimeType::class, [
                'label' => 'Application Deadline',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Job_offer::class,
        ]);
    }
}
