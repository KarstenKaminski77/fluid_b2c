<?php

namespace App\Entity;

use App\Repository\OrderItemsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderItemsRepository::class)
 */
class OrderItems
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Orders::class, inversedBy="orderItems")
     */
    private $orders;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $distributorId;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantity;

    /**
     * @ORM\Column(type="float")
     */
    private $unitPrice;

    /**
     * @ORM\Column(type="float")
     */
    private $total;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $poNumber;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comments;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $expiryDate;

    /**
     * @ORM\Column(type="integer")
     */
    private $isAccepted;

    /**
     * @ORM\Column(type="integer")
     */
    private $isRenegotiate;

    /**
     * @ORM\Column(type="integer")
     */
    private $isCancelled;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $isConfirmedDistributor;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantityDelivered;

    /**
     * @ORM\Column(type="integer")
     */
    private $isQuantityCorrect;

    /**
     * @ORM\Column(type="integer")
     */
    private $isQuantityIncorrect;

    /**
     * @ORM\Column(type="integer")
     */
    private $isAcceptedOnDelivery;

    /**
     * @ORM\Column(type="integer")
     */
    private $isRejectedOnDelivery;

    /**
     * @ORM\Column(type="integer")
     */
    private $isQuantityAdjust;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $rejectReason;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $orderReceivedBy;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $orderPlacedBy;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $itemId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $productId;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        if ($this->getModified() == null) {
            $this->setModified(new \DateTime());
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrders(): ?Orders
    {
        return $this->orders;
    }

    public function setOrders(?Orders $orders): self
    {
        $this->orders = $orders;

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

    public function getDistributorId(): ?int
    {
        return $this->distributorId;
    }

    public function setDistributorId(?int $distributorId): self
    {
        $this->distributorId = $distributorId;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): self
    {
        $this->total = $total;

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

    public function getPoNumber(): ?string
    {
        return $this->poNumber;
    }

    public function setPoNumber(string $poNumber): self
    {
        $this->poNumber = $poNumber;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getIsAccepted(): ?int
    {
        return $this->isAccepted;
    }

    public function setIsAccepted(int $isAccepted): self
    {
        $this->isAccepted = $isAccepted;

        return $this;
    }

    public function getIsRenegotiate(): ?int
    {
        return $this->isRenegotiate;
    }

    public function setIsRenegotiate(int $isRenegotiate): self
    {
        $this->isRenegotiate = $isRenegotiate;

        return $this;
    }

    public function getIsCancelled(): ?int
    {
        return $this->isCancelled;
    }

    public function setIsCancelled(int $isCancelled): self
    {
        $this->isCancelled = $isCancelled;

        return $this;
    }

    public function getIsConfirmedDistributor(): ?int
    {
        return $this->isConfirmedDistributor;
    }

    public function setIsConfirmedDistributor(?int $isConfirmedDistributor): self
    {
        $this->isConfirmedDistributor = $isConfirmedDistributor;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getQuantityDelivered(): ?int
    {
        return $this->quantityDelivered;
    }

    public function setQuantityDelivered(?int $quantityDelivered): self
    {
        $this->quantityDelivered = $quantityDelivered;

        return $this;
    }

    public function getIsQuantityCorrect(): ?int
    {
        return $this->isQuantityCorrect;
    }

    public function setIsQuantityCorrect(int $isQuantityCorrect): self
    {
        $this->isQuantityCorrect = $isQuantityCorrect;

        return $this;
    }

    public function getIsQuantityIncorrect(): ?int
    {
        return $this->isQuantityIncorrect;
    }

    public function setIsQuantityIncorrect(int $isQuantityIncorrect): self
    {
        $this->isQuantityIncorrect = $isQuantityIncorrect;

        return $this;
    }

    public function getIsAcceptedOnDelivery(): ?int
    {
        return $this->isAcceptedOnDelivery;
    }

    public function setIsAcceptedOnDelivery(int $isAcceptedOnDelivery): self
    {
        $this->isAcceptedOnDelivery = $isAcceptedOnDelivery;

        return $this;
    }

    public function getIsRejectedOnDelivery(): ?int
    {
        return $this->isRejectedOnDelivery;
    }

    public function setIsRejectedOnDelivery(int $isRejectedOnDelivery): self
    {
        $this->isRejectedOnDelivery = $isRejectedOnDelivery;

        return $this;
    }

    public function getIsQuantityAdjust(): ?int
    {
        return $this->isQuantityAdjust;
    }

    public function setIsQuantityAdjust(int $isQuantityAdjust): self
    {
        $this->isQuantityAdjust = $isQuantityAdjust;

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function setRejectReason(?string $rejectReason): self
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    public function getOrderReceivedBy(): ?string
    {
        return $this->orderReceivedBy;
    }

    public function setOrderReceivedBy(?string $orderReceivedBy): self
    {
        $this->orderReceivedBy = $orderReceivedBy;

        return $this;
    }

    public function getOrderPlacedBy(): ?string
    {
        return $this->orderPlacedBy;
    }

    public function setOrderPlacedBy(?string $orderPlacedBy): self
    {
        $this->orderPlacedBy = $orderPlacedBy;

        return $this;
    }

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }
}
