<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Entity\OutgoingInvoice;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OutgoingInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'label' => 'Kunde',
                'placeholder' => '– Kunde wählen –',
            ])
            ->add('issueDate', DateType::class, [
                'label' => 'Rechnungsdatum',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Fällig am',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('servicePeriodStart', DateType::class, [
                'label' => 'Leistungszeitraum von',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('servicePeriodEnd', DateType::class, [
                'label' => 'bis',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('buyerReference', TextType::class, [
                'label' => 'Buyer Reference (BT-10)',
                'required' => false,
                'help' => 'Pflicht nach XRechnung – z. B. Bestellnummer, Kostenstelle, Leitweg-ID.',
            ])
            ->add('leitwegId', TextType::class, [
                'label' => 'Leitweg-ID (B2G)',
                'required' => false,
            ])
            ->add('introText', TextareaType::class, [
                'label' => 'Einleitungstext',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('outroText', TextareaType::class, [
                'label' => 'Schlusstext',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => OutgoingInvoiceLineType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'entry_options' => ['label' => false],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OutgoingInvoice::class]);
    }
}
