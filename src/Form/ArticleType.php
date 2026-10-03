<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sku', TextType::class, ['label' => 'Artikelnummer'])
            ->add('name', TextType::class, ['label' => 'Bezeichnung'])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'required' => false])
            ->add('unitPrice', MoneyType::class, ['label' => 'Einzelpreis', 'currency' => 'EUR', 'divisor' => 1, 'scale' => 2])
            ->add('unit', ChoiceType::class, [
                'label' => 'Einheit',
                'choices' => [
                    'Stück (C62)' => 'C62',
                    'Stunde (HUR)' => 'HUR',
                    'Tag (DAY)' => 'DAY',
                    'Pauschal (LS)' => 'LS',
                    'Kilogramm (KGM)' => 'KGM',
                    'Meter (MTR)' => 'MTR',
                    'Liter (LTR)' => 'LTR',
                    'Monat (MON)' => 'MON',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}
