<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    /**
     * Page "Mot de passe oublié" — l'utilisateur saisit son email
     */
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UtilisateurRepository $userRepository,
        MailerInterface $mailer
    ): Response {
        $submitted = false;

        if ($request->isMethod('POST')) {
            $emailInput = $request->request->get('email');
            $user = $userRepository->findOneBy(['email' => $emailInput]);

            if ($user) {
                // Générer un token signé (valide 1 heure)
                $expiry = time() + 3600;
                $token = base64_encode($user->getEmail() . '|' . $expiry . '|' . hash('sha256', $user->getEmail() . $expiry . $this->getParameter('kernel.secret')));

                // Générer le lien de réinitialisation
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                // Envoyer le mail
                $email = (new Email())
                    ->from('noreply@viteetgourmand.fr')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe - Vite & Gourmand')
                    ->html(
                        '<h2>Réinitialisation de mot de passe</h2>' .
                        '<p>Bonjour ' . htmlspecialchars($user->getPrenom()) . ',</p>' .
                        '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>' .
                        '<p><a href="' . $resetUrl . '" style="display: inline-block; padding: 12px 25px; background: #007bff; color: #fff; text-decoration: none; border-radius: 6px;">Réinitialiser mon mot de passe</a></p>' .
                        '<p>Ce lien est valable <strong>1 heure</strong>.</p>' .
                        '<p>Si vous n\'avez pas fait cette demande, ignorez cet email.</p>' .
                        '<p><em>L\'équipe Vite & Gourmand</em></p>'
                    );

                $mailer->send($email);
            }

            // On affiche toujours le même message (sécurité : ne pas révéler si l'email existe)
            $submitted = true;
        }

        return $this->render('security/forgot_password.html.twig', [
            'submitted' => $submitted,
        ]);
    }

    /**
     * Page de réinitialisation — l'utilisateur clique sur le lien et saisit un nouveau mdp
     */
    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UtilisateurRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        // Décoder et vérifier le token
        $decoded = base64_decode($token);
        $parts = explode('|', $decoded);

        if (count($parts) !== 3) {
            $this->addFlash('error', 'Lien invalide.');
            return $this->redirectToRoute('app_forgot_password');
        }

        [$email, $expiry, $hash] = $parts;

        // Vérifier l'expiration
        if (time() > (int) $expiry) {
            $this->addFlash('error', 'Ce lien a expiré. Veuillez refaire une demande.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Vérifier la signature
        $expectedHash = hash('sha256', $email . $expiry . $this->getParameter('kernel.secret'));
        if (!hash_equals($expectedHash, $hash)) {
            $this->addFlash('error', 'Lien invalide.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Trouver l'utilisateur
        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Traiter le formulaire de nouveau mot de passe
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if (strlen($newPassword) < 10 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $newPassword)) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
