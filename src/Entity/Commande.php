<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $numero_commande = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_commande = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_prestation = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $heure_livraison = null;

    #[ORM\Column(length: 255)]
    private ?string $lieu_prestation = null;

    #[ORM\Column]
    private ?float $prix_menu = null;

    #[ORM\Column]
    private ?int $nombre_personne = null;

    #[ORM\Column]
    private ?float $prix_livraison = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?bool $pret_materiel = null;

    #[ORM\Column]
    private ?bool $restitution_materiel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif_annulation = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $mode_contact_client = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'commande')]
    private Collection $avis;

    public function __construct()
    {
        $this->avis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroCommande(): ?string
    {
        return $this->numero_commande;
    }

    public function setNumeroCommande(string $numero_commande): static
    {
        $this->numero_commande = $numero_commande;

        return $this;
    }

    public function getDateCommande(): ?\DateTime
    {
        return $this->date_commande;
    }

    public function setDateCommande(\DateTime $date_commande): static
    {
        $this->date_commande = $date_commande;

        return $this;
    }

    public function getDatePrestation(): ?\DateTime
    {
        return $this->date_prestation;
    }

    public function setDatePrestation(\DateTime $date_prestation): static
    {
        $this->date_prestation = $date_prestation;

        return $this;
    }

    public function getHeureLivraison(): ?string
    {
        return $this->heure_livraison;
    }

    public function setHeureLivraison(?string $heure_livraison): static
    {
        $this->heure_livraison = $heure_livraison;

        return $this;
    }

    public function getLieuPrestation(): ?string
    {
        return $this->lieu_prestation;
    }

    public function setLieuPrestation(string $lieu_prestation): static
    {
        $this->lieu_prestation = $lieu_prestation;

        return $this;
    }

    public function getPrixMenu(): ?float
    {
        return $this->prix_menu;
    }

    public function setPrixMenu(float $prix_menu): static
    {
        $this->prix_menu = $prix_menu;

        return $this;
    }

    public function getNombrePersonne(): ?int
    {
        return $this->nombre_personne;
    }

    public function setNombrePersonne(int $nombre_personne): static
    {
        $this->nombre_personne = $nombre_personne;

        return $this;
    }

    public function getPrixLivraison(): ?float
    {
        return $this->prix_livraison;
    }

    public function setPrixLivraison(float $prix_livraison): static
    {
        $this->prix_livraison = $prix_livraison;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPretMateriel(): ?bool
    {
        return $this->pret_materiel;
    }

    public function setPretMateriel(bool $pret_materiel): static
    {
        $this->pret_materiel = $pret_materiel;

        return $this;
    }

    public function isRestitutionMateriel(): ?bool
    {
        return $this->restitution_materiel;
    }

    public function setRestitutionMateriel(bool $restitution_materiel): static
    {
        $this->restitution_materiel = $restitution_materiel;

        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motif_annulation;
    }

    public function setMotifAnnulation(?string $motif_annulation): static
    {
        $this->motif_annulation = $motif_annulation;

        return $this;
    }

    public function getModeContactClient(): ?string
    {
        return $this->mode_contact_client;
    }

    public function setModeContactClient(?string $mode_contact_client): static
    {
        $this->mode_contact_client = $mode_contact_client;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        return $this->avis;
    }

    public function addAvi(Avis $avi): static
    {
        if (!$this->avis->contains($avi)) {
            $this->avis->add($avi);
            $avi->setCommande($this);
        }

        return $this;
    }

    public function removeAvi(Avis $avi): static
    {
        if ($this->avis->removeElement($avi)) {
            // set the owning side to null (unless already changed)
            if ($avi->getCommande() === $this) {
                $avi->setCommande(null);
            }
        }

        return $this;
    }
}
