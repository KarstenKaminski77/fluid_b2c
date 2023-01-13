<?php

namespace App\Entity;

use App\Repository\ClinicRetailUsersRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClinicRetailUsersRepository::class)
 */
class ClinicRetailUsers
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Clinics::class, inversedBy="clinicRetailUsers")
     */
    private $clinic;

    /**
     * @ORM\ManyToOne(targetEntity=RetailUsers::class, inversedBy="clinicRetailUsers")
     */
    private $retailUser;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $isApproved;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $isIgnored;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $created;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null)
        {
            $this->setCreated(new \DateTime());
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClinic(): ?Clinics
    {
        return $this->clinic;
    }

    public function setClinic(?Clinics $clinic): self
    {
        $this->clinic = $clinic;

        return $this;
    }

    public function getRetailUser(): ?RetailUsers
    {
        return $this->retailUser;
    }

    public function setRetailUser(?RetailUsers $retailUser): self
    {
        $this->retailUser = $retailUser;

        return $this;
    }

    public function getIsApproved(): ?int
    {
        return $this->isApproved;
    }

    public function setIsApproved(?int $isApproved): self
    {
        $this->isApproved = $isApproved;

        return $this;
    }

    public function getIsIgnored(): ?int
    {
        return $this->isIgnored;
    }

    public function setIsIgnored(?int $isIgnored): self
    {
        $this->isIgnored = $isIgnored;

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

    public function setCreated(?\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }
}
