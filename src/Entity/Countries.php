<?php

namespace App\Entity;

use App\Repository\CountriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CountriesRepository::class)
 */
class Countries
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $phone;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity=Distributors::class, mappedBy="addressCountry")
     */
    private $distributors;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $isActive;

    /**
     * @ORM\OneToMany(targetEntity=Clinics::class, mappedBy="country")
     */
    private $clinics;

    /**
     * @ORM\Column(type="string", length=45, nullable=true)
     */
    private $currency;

    /**
     * @ORM\OneToMany(targetEntity=RetailUsers::class, mappedBy="country", orphanRemoval=true)
     */
    private $retailUsers;

    public function __construct()
    {
        $this->distributors = new ArrayCollection();
        $this->clinics = new ArrayCollection();
        $this->retailUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): ?int
    {
        return $this->phone;
    }

    public function setPhone(int $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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
            $distributor->setAddressCountry($this);
        }

        return $this;
    }

    public function removeDistributor(Distributors $distributor): self
    {
        if ($this->distributors->removeElement($distributor)) {
            // set the owning side to null (unless already changed)
            if ($distributor->getAddressCountry() === $this) {
                $distributor->setAddressCountry(null);
            }
        }

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

    public function getIsActive(): ?int
    {
        return $this->isActive;
    }

    public function setIsActive(?int $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Clinics>
     */
    public function getClinics(): Collection
    {
        return $this->clinics;
    }

    public function addClinic(Clinics $clinic): self
    {
        if (!$this->clinics->contains($clinic)) {
            $this->clinics[] = $clinic;
            $clinic->setCountry($this);
        }

        return $this;
    }

    public function removeClinic(Clinics $clinic): self
    {
        if ($this->clinics->removeElement($clinic)) {
            // set the owning side to null (unless already changed)
            if ($clinic->getCountry() === $this) {
                $clinic->setCountry(null);
            }
        }

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, RetailUsers>
     */
    public function getRetailUsers(): Collection
    {
        return $this->retailUsers;
    }

    public function addRetailUser(RetailUsers $retailUser): self
    {
        if (!$this->retailUsers->contains($retailUser)) {
            $this->retailUsers[] = $retailUser;
            $retailUser->setCountry($this);
        }

        return $this;
    }

    public function removeRetailUser(RetailUsers $retailUser): self
    {
        if ($this->retailUsers->removeElement($retailUser)) {
            // set the owning side to null (unless already changed)
            if ($retailUser->getCountry() === $this) {
                $retailUser->setCountry(null);
            }
        }

        return $this;
    }
}
