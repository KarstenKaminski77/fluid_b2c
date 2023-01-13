<?php

namespace App\Entity;

use App\Repository\ApiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ApiRepository::class)
 */
class Api
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
     * @ORM\OneToMany(targetEntity=ApiDetails::class, mappedBy="api")
     */
    private $apiDetails;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }

        $this->apiDetails = new ArrayCollection();
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
     * @return Collection<int, ApiDetails>
     */
    public function getApiDetails(): Collection
    {
        return $this->apiDetails;
    }

    public function addApiDetail(ApiDetails $apiDetail): self
    {
        if (!$this->apiDetails->contains($apiDetail)) {
            $this->apiDetails[] = $apiDetail;
            $apiDetail->setApi($this);
        }

        return $this;
    }

    public function removeApiDetail(ApiDetails $apiDetail): self
    {
        if ($this->apiDetails->removeElement($apiDetail)) {
            // set the owning side to null (unless already changed)
            if ($apiDetail->getApi() === $this) {
                $apiDetail->setApi(null);
            }
        }

        return $this;
    }
}
