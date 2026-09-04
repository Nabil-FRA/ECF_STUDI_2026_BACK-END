<?php

namespace App\Controller\Api;

use App\Entity\Allergene;
use App\Entity\Commande;
use App\Entity\Horaire;
use App\Entity\Menu;
use App\Entity\MenuImage;
use App\Entity\Plat;
use App\Repository\HoraireRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use App\Service\MongoDbService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employe')]
#[IsGranted('ROLE_EMPLOYE')]
#[OA\Tag(name: 'Employé')]
class EmployeApiController extends AbstractController
{
    // =========================================================
    // COMMANDES
    // =========================================================

    #[Route('/commandes', name: 'api_employe_commandes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des commandes',
        description: 'Retourne toutes les commandes avec filtres optionnels par statut et client.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filtrer par statut', schema: new OA\Schema(type: 'string', enum: ['en cours', 'accepté', 'en préparation', 'en cours de livraison', 'livré', 'en attente du retour de matériel', 'terminée', 'annulée'])),
            new OA\Parameter(name: 'client', in: 'query', required: false, description: 'Recherche par email, nom ou prénom du client', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des commandes')
        ]
    )]
    public function commandes(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->getRepository(Commande::class)->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->orderBy('c.date_commande', 'DESC');

        $statut = trim($request->query->get('statut', ''));
        if ($statut !== '') {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        $client = trim($request->query->get('client', ''));
        if ($client !== '') {
            $qb->andWhere(
                'u.email LIKE :client OR u.nom LIKE :client OR u.prenom LIKE :client'
            )->setParameter('client', '%' . $client . '%');
        }

        $commandes = $qb->getQuery()->getResult();
        $result = [];

        foreach ($commandes as $c) {
            $result[] = [
                'id'                  => $c->getId(),
                'numero_commande'     => $c->getNumeroCommande(),
                'date_commande'       => $c->getDateCommande()?->format('Y-m-d H:i'),
                'date_prestation'     => $c->getDatePrestation()?->format('Y-m-d'),
                'lieu_prestation'     => $c->getLieuPrestation() ?? '',
                'nombre_personne'     => $c->getNombrePersonne(),
                'prix_total'          => $c->getPrixTotal(),
                'statut'              => $c->getStatut(),
                'pret_materiel'       => $c->isPretMateriel(),
                'restitution_materiel'=> $c->isRestitutionMateriel(),
                'client_email'        => $c->getUtilisateur() ? $c->getUtilisateur()->getEmail() : '',
                'menu_titre'          => $c->getMenu() ? $c->getMenu()->getTitre() : '',
            ];
        }

        return $this->json(['success' => true, 'commandes' => $result]);
    }

    #[Route('/commandes/{id}/statut', name: 'api_employe_commande_statut', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Changer le statut d\'une commande',
        description: 'Met à jour le statut d\'une commande. Règles métier : contact client obligatoire avant annulation, matériel restitué avant clôture.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(property: 'statut', type: 'string', enum: ['en cours', 'accepté', 'en préparation', 'en cours de livraison', 'livré', 'en attente du retour de matériel', 'terminée', 'annulée']),
                    new OA\Property(property: 'mode_contact_client', type: 'string', description: 'Obligatoire si statut = annulée', example: 'téléphone'),
                    new OA\Property(property: 'motif_annulation', type: 'string', description: 'Obligatoire si statut = annulée', example: 'Client injoignable')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 400, description: 'Statut invalide / règles métier non respectées')
        ]
    )]
    public function updateStatut(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $nouveauStatut = trim($data['statut'] ?? '');

        $statutsValides = [
            'en cours', 'accepté', 'en préparation', 'en cours de livraison',
            'livré', 'en attente du retour de matériel', 'terminée', 'annulée'
        ];

        if (!in_array($nouveauStatut, $statutsValides)) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        if ($nouveauStatut === 'annulée') {
            $modeContact = $data['mode_contact_client'] ?? '';
            $motif = $data['motif_annulation'] ?? '';
            if (empty($modeContact) || empty($motif)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Mode de contact et motif obligatoires pour annulation.'
                ], 400);
            }
            $commande->setModeContactClient(strip_tags($modeContact));
            $commande->setMotifAnnulation(strip_tags($motif));
        }

        if ($nouveauStatut === 'en attente du retour de matériel' && !$commande->isPretMateriel()) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun matériel prêté pour cette commande.'
            ], 400);
        }

        if ($nouveauStatut === 'terminée' && $commande->isPretMateriel() && !$commande->isRestitutionMateriel()) {
            if ($commande->getStatut() === 'en attente du retour de matériel') {
                $commande->setRestitutionMateriel(true);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Le matériel doit être restitué avant de terminer.'
                ], 400);
            }
        }

        $commande->setStatut($nouveauStatut);
        $em->flush();

        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre' => $commande->getMenu() ? $commande->getMenu()->getTitre() : '',
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixTotal(),
            'date_commande' => $commande->getDateCommande()?->format('Y-m-d') ?? date('Y-m-d'),
            'statut' => $nouveauStatut,
            'client_email' => $commande->getUtilisateur()?->getEmail() ?? '',
        ]);
        $mongoDbService->ajouterSuivi($commande->getNumeroCommande(), $nouveauStatut);

        if ($nouveauStatut === 'terminée' && $commande->getUtilisateur()) {
            try {
                $email = (new Email())
                    ->from('maxnabil2ait@gmail.com')
                    ->to($commande->getUtilisateur()->getEmail())
                    ->subject('Commande ' . $commande->getNumeroCommande() . ' terminée')
                    ->html(
                        '<h2>Commande terminée !</h2>' .
                        '<p>Bonjour ' . htmlspecialchars($commande->getUtilisateur()->getPrenom()) . ',</p>' .
                        '<p>Votre commande <strong>' . $commande->getNumeroCommande() . '</strong> est terminée.</p>' .
                        '<p>Connectez-vous à votre espace client pour nous laisser un avis !</p>'
                    );
                $mailer->send($email);
            } catch (\Exception $e) {}
        }

        if ($nouveauStatut === 'en attente du retour de matériel' && $commande->getUtilisateur()) {
            try {
                $email = (new Email())
                    ->from('maxnabil2ait@gmail.com')
                    ->to($commande->getUtilisateur()->getEmail())
                    ->subject('Retour de matériel requis — ' . $commande->getNumeroCommande())
                    ->html(
                        '<h2>Retour de matériel</h2>' .
                        '<p>Bonjour ' . htmlspecialchars($commande->getUtilisateur()->getPrenom()) . ',</p>' .
                        '<p>Du matériel vous a été prêté dans le cadre de votre commande <strong>' .
                        $commande->getNumeroCommande() . '</strong>.</p>' .
                        '<p><strong>Vous disposez de 10 jours ouvrés pour le restituer.</strong></p>' .
                        '<p>Sans retour dans ce délai, des frais de <strong>600 €</strong> vous seront facturés ' .
                        'conformément aux conditions générales de vente.</p>' .
                        '<p>Pour organiser la restitution, contactez-nous par mail à ' .
                        '<a href="mailto:contact@viteetgourmand.fr">contact@viteetgourmand.fr</a>.</p>'
                    );
                $mailer->send($email);
            } catch (\Exception $e) {}
        }

        return $this->json(['success' => true, 'message' => 'Statut mis à jour.']);
    }

    #[Route('/commandes/{id}', name: 'api_employe_commande_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier une commande',
        description: 'Modifie les détails d\'une commande. Uniquement si statut : en cours, accepté ou en préparation.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nombre_personne', type: 'integer'),
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date'),
                    new OA\Property(property: 'lieu_prestation', type: 'string'),
                    new OA\Property(property: 'heure_livraison', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Commande mise à jour'),
            new OA\Response(response: 400, description: 'Modification impossible / validation échouée')
        ]
    )]
    public function updateCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $statutsModifiables = ['en cours', 'accepté', 'en préparation'];

        if (!in_array($commande->getStatut(), $statutsModifiables)) {
            return $this->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être modifiée (statut : ' . $commande->getStatut() . ').'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);
        $erreurs = [];

        if (isset($data['nombre_personne'])) {
            $nb = (int) $data['nombre_personne'];
            if ($nb < 1) {
                $erreurs[] = 'Le nombre de personnes doit être au moins 1.';
            } else {
                $commande->setNombrePersonne($nb);
            }
        }

        if (!empty($data['date_prestation'])) {
            try {
                $date = new \DateTime($data['date_prestation']);
                if ($date < new \DateTime('today')) {
                    $erreurs[] = 'La date de prestation doit être dans le futur.';
                } else {
                    $commande->setDatePrestation($date);
                }
            } catch (\Exception $e) {
                $erreurs[] = 'Date de prestation invalide.';
            }
        }

        if (isset($data['lieu_prestation']) && !empty(trim($data['lieu_prestation']))) {
            $commande->setLieuPrestation(strip_tags(trim($data['lieu_prestation'])));
        }

        if (isset($data['heure_livraison'])) {
            $commande->setHeureLivraison(strip_tags(trim($data['heure_livraison'])) ?: null);
        }

        if (!empty($erreurs)) {
            return $this->json(['success' => false, 'message' => implode(' ', $erreurs)], 400);
        }

        $em->flush();

        return $this->json([
            'success'          => true,
            'message'          => 'Commande mise à jour.',
            'nombre_personne'  => $commande->getNombrePersonne(),
            'date_prestation'  => $commande->getDatePrestation()?->format('Y-m-d'),
            'lieu_prestation'  => $commande->getLieuPrestation(),
            'heure_livraison'  => $commande->getHeureLivraison(),
        ]);
    }

    // =========================================================
    // HORAIRES
    // =========================================================

    #[Route('/horaires', name: 'api_employe_horaires_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier les horaires d\'ouverture',
        description: 'Met à jour les horaires d\'ouverture pour chaque jour. Envoyer heure_ouverture/heure_fermeture à null pour « Fermé ».',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'jour', type: 'string', enum: ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']),
                        new OA\Property(property: 'heure_ouverture', type: 'string', nullable: true, example: '08:00'),
                        new OA\Property(property: 'heure_fermeture', type: 'string', nullable: true, example: '19:00')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Horaires mis à jour'),
            new OA\Response(response: 400, description: 'Format invalide')
        ]
    )]
    public function updateHoraires(
        Request $request,
        HoraireRepository $horaireRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Format invalide.'], 400);
        }

        $joursValides = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        foreach ($data as $item) {
            $jour = strtolower(trim($item['jour'] ?? ''));
            if (!in_array($jour, $joursValides)) continue;

            $horaire = $horaireRepository->findOneBy(['jour' => $jour]);
            if (!$horaire) {
                $horaire = new Horaire();
                $horaire->setJour($jour);
                $em->persist($horaire);
            }

            $horaire->setHeureOuverture($item['heure_ouverture'] ?: null);
            $horaire->setHeureFermeture($item['heure_fermeture'] ?: null);
        }

        $em->flush();

        return $this->json(['success' => true, 'message' => 'Horaires mis à jour.']);
    }

    // =========================================================
    // AVIS
    // =========================================================

    #[Route('/avis', name: 'api_employe_avis', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des avis',
        description: 'Retourne tous les avis (tous statuts confondus), triés par ID décroissant.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis')
        ]
    )]
    public function avisList(EntityManagerInterface $em): JsonResponse
    {
        $avisRepo = $em->getRepository(\App\Entity\Avis::class);
        $avisList = $avisRepo->findBy([], ['id' => 'DESC']);

        $result = [];
        foreach ($avisList as $a) {
            $result[] = [
                'id'          => $a->getId(),
                'note'        => $a->getNote(),
                'description' => $a->getDescription() ?? '',
                'statut'      => $a->getStatut(),
                'client'      => $a->getUtilisateur()
                    ? $a->getUtilisateur()->getPrenom() . ' ' . $a->getUtilisateur()->getNom()
                    : '',
                'commande'    => $a->getCommande() ? $a->getCommande()->getNumeroCommande() : '',
                'menu_titre'  => ($a->getCommande() && $a->getCommande()->getMenu())
                    ? $a->getCommande()->getMenu()->getTitre()
                    : '',
            ];
        }

        return $this->json(['success' => true, 'avis' => $result]);
    }

    #[Route('/avis/{id}/statut', name: 'api_employe_avis_statut', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Valider ou refuser un avis',
        description: 'Change le statut d\'un avis en « validé » ou « refusé ».',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(property: 'statut', type: 'string', enum: ['validé', 'refusé'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis mis à jour'),
            new OA\Response(response: 400, description: 'Statut invalide')
        ]
    )]
    public function updateAvisStatut(
        \App\Entity\Avis $avis,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $statut = trim($data['statut'] ?? '');

        if (!in_array($statut, ['validé', 'refusé'])) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $avis->setStatut($statut);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Avis ' . $statut . '.']);
    }

    // =========================================================
    // GESTION DES MENUS
    // =========================================================

    #[Route('/menus', name: 'api_employe_menu_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un menu',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre', 'prix_par_personne'],
                properties: [
                    new OA\Property(property: 'titre', type: 'string', example: 'Menu Prestige'),
                    new OA\Property(property: 'prix_par_personne', type: 'number', example: 35.50),
                    new OA\Property(property: 'nombre_personne_minimum', type: 'integer', example: 10),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'conditions', type: 'string'),
                    new OA\Property(property: 'quantite_restante', type: 'integer'),
                    new OA\Property(property: 'theme', type: 'string', description: 'Libellé du thème', example: 'Mariage'),
                    new OA\Property(property: 'regime', type: 'string', description: 'Libellé du régime', example: 'Végétarien')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Menu créé'),
            new OA\Response(response: 400, description: 'Titre ou prix manquant')
        ]
    )]
    public function createMenu(
        Request $request,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        return $this->saveMenuEmp(null, $data, $em, $themeRepo, $regimeRepo);
    }

    #[Route('/menus/{id}', name: 'api_employe_menu_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'titre', type: 'string'),
                    new OA\Property(property: 'prix_par_personne', type: 'number'),
                    new OA\Property(property: 'nombre_personne_minimum', type: 'integer'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'conditions', type: 'string'),
                    new OA\Property(property: 'quantite_restante', type: 'integer'),
                    new OA\Property(property: 'theme', type: 'string'),
                    new OA\Property(property: 'regime', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Menu modifié'),
            new OA\Response(response: 400, description: 'Validation échouée'),
            new OA\Response(response: 404, description: 'Menu introuvable')
        ]
    )]
    public function updateMenu(
        Menu $menu,
        Request $request,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        return $this->saveMenuEmp($menu, $data, $em, $themeRepo, $regimeRepo);
    }

    #[Route('/menus/{id}', name: 'api_employe_menu_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un menu',
        description: 'Supprime un menu. Impossible si des commandes y sont associées.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu supprimé'),
            new OA\Response(response: 400, description: 'Commandes associées empêchent la suppression')
        ]
    )]
    public function deleteMenu(Menu $menu, EntityManagerInterface $em): JsonResponse
    {
        if ($menu->getCommandes()->count() > 0) {
            return $this->json(['success' => false, 'message' => 'Ce menu a des commandes associées.'], 400);
        }
        $em->remove($menu);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Menu supprimé.']);
    }

    #[Route('/menus/{id}/images', name: 'api_employe_menu_image_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter une image à un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['url_image'],
                properties: [
                    new OA\Property(property: 'url_image', type: 'string', format: 'url', example: 'https://example.com/image.jpg')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Image ajoutée'),
            new OA\Response(response: 400, description: 'URL invalide')
        ]
    )]
    public function addMenuImage(
        Menu $menu,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $url = strip_tags(trim($data['url_image'] ?? ''));

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['success' => false, 'message' => 'URL invalide.'], 400);
        }

        $image = new MenuImage();
        $image->setUrlImage($url);
        $image->setMenu($menu);

        $em->persist($image);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Image ajoutée.',
            'image' => ['id' => $image->getId(), 'url_image' => $image->getUrlImage()]
        ], 201);
    }

    #[Route('/menus/{menuId}/images/{imageId}', name: 'api_employe_menu_image_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer une image d\'un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'imageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image supprimée'),
            new OA\Response(response: 404, description: 'Image introuvable')
        ]
    )]
    public function deleteMenuImage(int $menuId, int $imageId, EntityManagerInterface $em): JsonResponse
    {
        $image = $em->getRepository(MenuImage::class)->find($imageId);
        if (!$image || $image->getMenu()->getId() !== $menuId) {
            return $this->json(['success' => false, 'message' => 'Image introuvable.'], 404);
        }
        $em->remove($image);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Image supprimée.']);
    }

    #[Route('/menus/{id}/plats', name: 'api_employe_menu_plat_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter un plat à un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre_plat'],
                properties: [
                    new OA\Property(property: 'titre_plat', type: 'string', maxLength: 150, example: 'Filet de boeuf sauce bordelaise'),
                    new OA\Property(property: 'allergenes', type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs des allergènes', example: [1, 3])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plat ajouté'),
            new OA\Response(response: 400, description: 'Titre manquant ou trop long (max 150 car.)')
        ]
    )]
    public function addMenuPlat(Menu $menu, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $titre = strip_tags(trim($data['titre_plat'] ?? ''));

        if (empty($titre)) {
            return $this->json(['success' => false, 'message' => 'Titre du plat obligatoire.'], 400);
        }

        if (strlen($titre) > 150) {
            return $this->json(['success' => false, 'message' => 'Le nom du plat ne doit pas dépasser 150 caractères.'], 400);
        }

        $plat = new Plat();
        $plat->setTitrePlat($titre);

        if (!empty($data['allergenes']) && is_array($data['allergenes'])) {
            foreach ($data['allergenes'] as $allergeneId) {
                $allergene = $em->getRepository(Allergene::class)->find((int) $allergeneId);
                if ($allergene) {
                    $plat->addAllergene($allergene);
                }
            }
        }

        $em->persist($plat);
        $menu->addPlat($plat);
        $em->flush();

        $allergenesList = [];
        foreach ($plat->getAllergenes() as $a) {
            $allergenesList[] = ['id' => $a->getId(), 'libelle' => $a->getLIBELLE()];
        }

        return $this->json([
            'success' => true,
            'plat' => ['id' => $plat->getId(), 'titre_plat' => $plat->getTitrePlat(), 'allergenes' => $allergenesList]
        ], 201);
    }

    #[Route('/menus/{menuId}/plats/{platId}', name: 'api_employe_menu_plat_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Retirer un plat d\'un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'platId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plat retiré'),
            new OA\Response(response: 404, description: 'Menu ou plat introuvable')
        ]
    )]
    public function deleteMenuPlat(int $menuId, int $platId, EntityManagerInterface $em): JsonResponse
    {
        $menu = $em->getRepository(Menu::class)->find($menuId);
        $plat = $em->getRepository(Plat::class)->find($platId);

        if (!$menu || !$plat) {
            return $this->json(['success' => false, 'message' => 'Menu ou plat introuvable.'], 404);
        }

        $menu->removePlat($plat);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Plat retiré du menu.']);
    }

    #[Route('/menus/{menuId}/plats/{platId}', name: 'api_employe_menu_plat_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier un plat',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'platId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'titre_plat', type: 'string', maxLength: 150),
                    new OA\Property(property: 'allergenes', type: 'array', items: new OA\Items(type: 'integer'), description: 'Remplace tous les allergènes')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Plat modifié'),
            new OA\Response(response: 400, description: 'Titre trop long'),
            new OA\Response(response: 404, description: 'Plat introuvable')
        ]
    )]
    public function updateMenuPlat(int $menuId, int $platId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $plat = $em->getRepository(Plat::class)->find($platId);
        if (!$plat) {
            return $this->json(['success' => false, 'message' => 'Plat introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['titre_plat'])) {
            $titre = strip_tags(trim($data['titre_plat']));
            if (strlen($titre) > 150) {
                return $this->json(['success' => false, 'message' => 'Le nom du plat ne doit pas dépasser 150 caractères.'], 400);
            }
            $plat->setTitrePlat($titre);
        }

        if (isset($data['allergenes']) && is_array($data['allergenes'])) {
            foreach ($plat->getAllergenes() as $a) {
                $plat->removeAllergene($a);
            }
            foreach ($data['allergenes'] as $allergeneId) {
                $allergene = $em->getRepository(Allergene::class)->find((int) $allergeneId);
                if ($allergene) {
                    $plat->addAllergene($allergene);
                }
            }
        }

        $em->flush();

        $allergenesList = [];
        foreach ($plat->getAllergenes() as $a) {
            $allergenesList[] = ['id' => $a->getId(), 'libelle' => $a->getLIBELLE()];
        }

        return $this->json([
            'success' => true,
            'plat' => ['id' => $plat->getId(), 'titre_plat' => $plat->getTitrePlat(), 'allergenes' => $allergenesList]
        ]);
    }

    private function saveMenuEmp(
        ?Menu $menu,
        array $data,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $titre = strip_tags(trim($data['titre'] ?? ''));
        $prix  = isset($data['prix_par_personne']) ? (float) $data['prix_par_personne'] : null;

        if (empty($titre) || $prix === null || $prix <= 0) {
            return $this->json(['success' => false, 'message' => 'Titre et prix obligatoires.'], 400);
        }

        if ($menu === null) {
            $menu = new Menu();
        }

        $menu->setTitre($titre);
        $menu->setPrixParPersonne($prix);

        if (!empty($data['nombre_personne_minimum'])) {
            $menu->setNombrePersonneMinimum((int) $data['nombre_personne_minimum']);
        }

        if (isset($data['description'])) {
            $menu->setDescription(strip_tags(trim($data['description'])));
        }

        if (isset($data['conditions'])) {
            $menu->setConditions(strip_tags(trim($data['conditions'])));
        }

        if (isset($data['quantite_restante'])) {
            $menu->setQuantiteRestante((int) $data['quantite_restante']);
        }

        if (!empty($data['theme'])) {
            $theme = $themeRepo->findOneBy(['libelle' => $data['theme']]);
            if ($theme) $menu->setTheme($theme);
        }

        if (!empty($data['regime'])) {
            $regime = $regimeRepo->findOneBy(['libelle' => $data['regime']]);
            if ($regime) $menu->setRegime($regime);
        }

        $em->persist($menu);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Menu enregistré.', 'id' => $menu->getId()]);
    }
}
