<?php

namespace App\Entity;

use App\Repository\OrderStatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderStatusRepository::class)
 */
class OrderStatus
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

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
    private $ordersId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $distributorId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $poFile;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class, inversedBy="orderStatuses")
     */
    private $status;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrdersId(): ?int
    {
        return $this->ordersId;
    }

    public function setOrdersId(?int $ordersId): self
    {
        $this->ordersId = $ordersId;

        return $this;
    }

    public function getDistributorId(): ?int
    {
        return $this->distributorId;
    }

    public function setDistributorId(?int $distributorId): self
    {
        $this->distributorId = $distributorId;

        return $this;
    }

    public function getPoFile(): ?string
    {
        return $this->poFile;
    }

    public function setPoFile(?string $poFile): self
    {
        $this->poFile = $poFile;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): self
    {
        $this->status = $status;

        return $this;
    }
}
