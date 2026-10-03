<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, ['label' => 'Firma'])
            ->add('contactName', TextType::class, ['label' => 'Ansprechpartner', 'required' => false])
            ->add('street', TextType::class, ['label' => 'Straße & Hausnummer'])
            ->add('postalCode', TextType::class, ['label' => 'PLZ'])
            ->add('city', TextType::class, ['label' => 'Ort'])
            ->add('country', TextType::class, ['label' => 'Land (ISO 3166-1 alpha-2)'])
            ->add('email', TextType::class, ['label' => 'E-Mail', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Telefon', 'required' => false])
            ->add('taxNumber', TextType::class, ['label' => 'Steuernummer', 'required' => false])
            ->add('vatId', TextType::class, ['label' => 'USt-IdNr. (falls vorhanden)', 'required' => false])
            ->add('iban', TextType::class, ['label' => 'IBAN', 'required' => false])
            ->add('bic', TextType::class, ['label' => 'BIC', 'required' => false])
            ->add('bankName', TextType::class, ['label' => 'Bank', 'required' => false])
            ->add('kleinunternehmer', CheckboxType::class, [
                'label' => 'Kleinunternehmer nach § 19 UStG (keine USt)',
                'required' => false,
            ])
            ->add('kleinunternehmerNote', TextareaType::class, [
                'label' => 'Pflichthinweis auf der Rechnung',
                'attr' => ['rows' => 2],
            ])
            ->add('invoiceNumberPrefix', TextType::class, ['label' => 'Rechnungsnummer-Präfix'])
            ->add('nextInvoiceNumber', IntegerType::class, ['label' => 'Nächster Zähler'])
            ->add('defaultPaymentTermDays', IntegerType::class, ['label' => 'Standard-Zahlungsziel (Tage)']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Settings::class]);
    }
}
