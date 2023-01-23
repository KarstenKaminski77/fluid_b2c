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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $clinicId;

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

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $retailUserId;

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

    public function getClinicId(): ?int
    {
        return $this->clinicId;
    }

    public function setClinic(?int $clinicId): self
    {
        $this->clinicId = $clinicId;

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

    public function getRetailUserId(): ?int
    {
        return $this->retailUserId;
    }

    public function setRetailUserId(?int $retailUserId): self
    {
        $this->retailUserId = $retailUserId;

        return $this;
    }
}
