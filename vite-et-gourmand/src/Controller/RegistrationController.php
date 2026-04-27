<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        RoleRepository $roleRepository,
        MailerInterface $mailer
    ): Response {
        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $roleClient = $roleRepository->findOneBy(['libelle' => 'utilisateur']);
            $user->setRole($roleClient);

            $entityManager->persist($user);
            $entityManager->flush();

            // Mail de bienvenue
            $email = (new Email())
                ->from('noreply@viteetgourmand.fr')
                ->to($user->getEmail())
                ->subject('Bienvenue chez Vite & Gourmand !')
                ->html(
                    '<h2>Bienvenue ' . htmlspecialchars($user->getPrenom()) . ' !</h2>' .
                    '<p>Votre compte a bien été créé chez <strong>Vite & Gourmand</strong>.</p>' .
                    '<p>Vous pouvez dès maintenant consulter nos menus et passer commande.</p>' .
                    '<p>À très bientôt !</p>' .
                    '<p><em>L\'équipe Vite & Gourmand</em></p>'
                );

            $mailer->send($email);

            $security->login($user, 'form_login', 'main');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
