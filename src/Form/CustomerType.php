<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Firma / Name'])
            ->add('contactName', TextType::class, ['label' => 'Ansprechpartner', 'required' => false])
            ->add('street', TextType::class, ['label' => 'Straße & Hausnummer'])
            ->add('postalCode', TextType::class, ['label' => 'PLZ'])
            ->add('city', TextType::class, ['label' => 'Ort'])
            ->add('country', TextType::class, ['label' => 'Land'])
            ->add('email', TextType::class, ['label' => 'E-Mail', 'required' => false])
            ->add('vatId', TextType::class, ['label' => 'USt-IdNr.', 'required' => false])
            ->add('leitwegId', TextType::class, ['label' => 'Leitweg-ID (nur B2G)', 'required' => false])
            ->add('buyerReference', TextType::class, ['label' => 'Buyer Reference (BT-10)', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Notizen', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Customer::class]);
    }
}
