<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CountryRepository::class)
 */
class Country
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=2)
     */
    private $twoLetters;

    /**
     * @ORM\Column(type="string", length=3)
     */
    private $threeLetters;

    /**
     * @ORM\OneToMany(targetEntity=Utilisateur::class, mappedBy="country")
     */
    private $utilisateurs;

    public function __toString()
    {
        return $this->getLabel();
    }
    public function __construct()
    {
        $this->utilisateurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getTwoLetters(): ?string
    {
        return $this->twoLetters;
    }

    public function setTwoLetters(string $twoLetters): self
    {
        $this->twoLetters = $twoLetters;

        return $this;
    }

    public function getThreeLetters(): ?string
    {
        return $this->threeLetters;
    }

    public function setThreeLetters(string $threeLetters): self
    {
        $this->threeLetters = $threeLetters;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs[] = $utilisateur;
            $utilisateur->setCountry($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self
    {
        if ($this->utilisateurs->removeElement($utilisateur)) {
            // set the owning side to null (unless already changed)
            if ($utilisateur->getCountry() === $this) {
                $utilisateur->setCountry(null);
            }
        }

        return $this;
    }
}
