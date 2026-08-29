<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $submitted = false;

        if ($request->isMethod('POST')) {
            $titre = $request->request->get('titre');
            $description = $request->request->get('description');
            $emailVisiteur = $request->request->get('email');

            // Validation
            if (empty($titre) || empty($description) || empty($emailVisiteur)) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_contact');
            }

            if (!filter_var($emailVisiteur, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez entrer une adresse email valide.');
                return $this->redirectToRoute('app_contact');
            }

            // Envoi du mail à l'entreprise
            $email = (new Email())
                ->from('maxnabil2ait@gmail.com')
                ->to('contact@viteetgourmand.fr')
                ->replyTo($emailVisiteur)
                ->subject('[Contact] ' . $titre)
                ->html(
                    '<h2>Nouveau message de contact</h2>' .
                    '<p><strong>De :</strong> ' . htmlspecialchars($emailVisiteur) . '</p>' .
                    '<p><strong>Sujet :</strong> ' . htmlspecialchars($titre) . '</p>' .
                    '<hr>' .
                    '<p>' . nl2br(htmlspecialchars($description)) . '</p>'
                );

            $mailer->send($email);

            $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.');
            $submitted = true;
        }

        $userEmail = '';
        if ($this->getUser()) {
            $userEmail = $this->getUser()->getEmail();
        }

        return $this->render('contact/index.html.twig', [
            'submitted' => $submitted,
            'userEmail' => $userEmail,
        ]);
    }
}
