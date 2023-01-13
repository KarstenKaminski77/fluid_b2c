<?php

namespace App\Entity;

use App\Repository\TrackingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrackingRepository::class)
 */
class Tracking
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
    private $name;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=Distributors::class, mappedBy="tracking")
     */
    private $distributors;

    public function __construct()
    {
        $this->distributors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getModified(): ?\DateTimeInterface
    {
        return $this->modified;
    }

    public function setModified(\DateTimeInterface $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return Collection<int, Distributors>
     */
    public function getDistributors(): Collection
    {
        return $this->distributors;
    }

    public function addDistributor(Distributors $distributor): self
    {
        if (!$this->distributors->contains($distributor)) {
            $this->distributors[] = $distributor;
            $distributor->setTracking($this);
        }

        return $this;
    }

    public function removeDistributor(Distributors $distributor): self
    {
        if ($this->distributors->removeElement($distributor)) {
            // set the owning side to null (unless already changed)
            if ($distributor->getTracking() === $this) {
                $distributor->setTracking(null);
            }
        }

        return $this;
    }
}
