<?php

namespace App\Form\Filter;

use DateTimeImmutable;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterOperands;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Spiriit\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobOfferFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', Filters\TextFilterType::class, [
                'required' => false,
                'mapped' => false,
                'label' => false,
                'condition_pattern' => FilterOperands::STRING_CONTAINS,
                'apply_filter' => static function (QueryInterface $filterQuery, $field, $values) {
                    $value = trim((string) ($values['value'] ?? ''));
                    if ($value === '') {
                        return null;
                    }

                    $qb = $filterQuery->getQueryBuilder();
                    $alias = $qb->getRootAliases()[0] ?? 'jo';
                    $expr = $filterQuery->getExpr();
                    $paramName = 'p_search_bundle';
                    $normalized = '%' . mb_strtolower($value) . '%';

                    $orExpression = $expr->orX(
                        $expr->like('LOWER(' . $alias . '.title)', ':' . $paramName),
                        $expr->like('LOWER(' . $alias . '.description)', ':' . $paramName),
                        $expr->like('LOWER(' . $alias . '.location)', ':' . $paramName),
                        $expr->like('LOWER(' . $alias . '.contract_type)', ':' . $paramName),
                        $expr->like('LOWER(' . $alias . '.status)', ':' . $paramName)
                    );

                    return $filterQuery->createCondition($orExpression, [$paramName => $normalized]);
                },
            ])
            ->add('contract_type', Filters\ChoiceFilterType::class, [
                'required' => false,
                'label' => false,
                'choices' => $this->toChoiceMap((array) $options['contract_types']),
            ])
            ->add('status', Filters\ChoiceFilterType::class, [
                'required' => false,
                'label' => false,
                'choices' => $this->toChoiceMap((array) $options['job_statuses']),
            ])
            ->add('deadline', DateType::class, [
                'required' => false,
                'label' => false,
                'widget' => 'single_text',
                'input' => 'string',
                'apply_filter' => static function (QueryInterface $filterQuery, $field, $values) {
                    $value = trim((string) ($values['value'] ?? ''));
                    if ($value === '') {
                        return null;
                    }

                    try {
                        $dateStart = new DateTimeImmutable($value . ' 00:00:00');
                        $dateEnd = $dateStart->modify('+1 day');
                    } catch (\Throwable) {
                        return null;
                    }

                    $qb = $filterQuery->getQueryBuilder();
                    $alias = $qb->getRootAliases()[0] ?? 'jo';
                    $expr = $filterQuery->getExpr();

                    return $filterQuery->createCondition(
                        $expr->andX(
                            $expr->gte($alias . '.deadline', ':p_deadline_start'),
                            $expr->lt($alias . '.deadline', ':p_deadline_end')
                        ),
                        [
                            'p_deadline_start' => $dateStart,
                            'p_deadline_end' => $dateEnd,
                        ]
                    );
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'contract_types' => [],
            'job_statuses' => [],
        ]);

        $resolver->setAllowedTypes('contract_types', 'array');
        $resolver->setAllowedTypes('job_statuses', 'array');
    }

    /**
     * @param array<int, string> $items
     * @return array<string, string>
     */
    private function toChoiceMap(array $items): array
    {
        $choices = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $choices[$value] = $value;
        }

        return $choices;
    }
}
