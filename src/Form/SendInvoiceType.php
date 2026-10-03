<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class SendInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('to', EmailType::class, ['label' => 'An', 'required' => true])
            ->add('cc', TextType::class, ['label' => 'CC (optional, mehrere mit Komma trennen)', 'required' => false])
            ->add('subject', TextType::class, ['label' => 'Betreff'])
            ->add('body', TextareaType::class, ['label' => 'Nachricht', 'attr' => ['rows' => 10]])
            ->add('attachments', ChoiceType::class, [
                'label' => 'Anhänge',
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'ZUGFeRD-PDF (empfohlen – PDF/A-3 mit eingebettetem XML)' => 'zugferd',
                    'Reine XRechnung-XML' => 'xml',
                    'Nur Sicht-PDF (nicht EN 16931-konform)' => 'pdf',
                ],
                'data' => ['zugferd'],
            ]);
    }
}
