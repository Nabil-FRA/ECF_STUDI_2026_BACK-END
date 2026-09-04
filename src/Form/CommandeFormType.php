<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Service\TarificationService;
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
                'attr' => ['placeholder' => 'Ex: 12 Rue de la Paix, 33000 Bordeaux'],
                'help' => 'Indiquez le code postal : il détermine la zone de livraison.',
            ])
            ->add('distance_km', IntegerType::class, [
                'label' => 'Distance depuis Bordeaux (km)',
                'required' => false,
                'help' => 'À renseigner pour toute livraison hors Bordeaux.',
                'attr' => [
                    'min' => 0,
                    'max' => TarificationService::DISTANCE_MAX_KM,
                    'placeholder' => '0',
                ],
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
                // Sur une modification de commande, le menu est figé : le champ
                // est désactivé pour que la valeur soumise soit ignorée.
                'disabled' => $options['menu_verrouille'],
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
            'menu_verrouille' => false,
        ]);

        $resolver->setAllowedTypes('menu_verrouille', 'bool');
    }
}
