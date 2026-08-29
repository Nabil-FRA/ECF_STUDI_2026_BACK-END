<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Menu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lieu_prestation', TextType::class, [
                'label' => 'Adresse de livraison',
                'attr' => ['placeholder' => 'Ex: 12 Rue de la Paix, Bordeaux'],
            ])
            ->add('date_prestation', DateType::class, [
                'label' => 'Date de la prestation',
                'widget' => 'single_text',
                'attr' => ['min' => (new \DateTime('+1 day'))->format('Y-m-d')],
            ])
            ->add('heure_livraison', TextType::class, [
                'label' => 'Heure souhaitée de livraison',
                'attr' => ['placeholder' => 'Ex: 12h30'],
            ])
            ->add('menu', EntityType::class, [
                'class' => Menu::class,
                'choice_label' => 'titre',
                'label' => 'Menu choisi',
            ])
            ->add('nombre_personne', IntegerType::class, [
                'label' => 'Nombre de personnes',
                'attr' => ['min' => 1],
            ])
            ->add('pret_materiel', CheckboxType::class, [
                'label' => 'Prêt de matériel (assiettes, couverts...)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
