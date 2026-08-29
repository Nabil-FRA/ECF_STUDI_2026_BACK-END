<?php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label' => 'Votre note',
                'choices' => [
                    '⭐ 1 - Très insatisfait' => 1,
                    '⭐⭐ 2 - Insatisfait' => 2,
                    '⭐⭐⭐ 3 - Correct' => 3,
                    '⭐⭐⭐⭐ 4 - Satisfait' => 4,
                    '⭐⭐⭐⭐⭐ 5 - Très satisfait' => 5,
                ],
                'placeholder' => 'Choisissez une note',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Votre commentaire',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Décrivez votre expérience...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
        ]);
    }
}
