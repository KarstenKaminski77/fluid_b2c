<?php

namespace App\Entity;

use App\Repository\DistributorProductsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DistributorProductsRepository::class)
 */
class DistributorProducts
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Distributors::class, inversedBy="distributorProducts")
     */
    private $distributor;

    /**
     * @ORM\ManyToOne(targetEntity=Products::class, inversedBy="distributorProducts")
     */
    private $product;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $sku;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $unitPrice;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $stockCount;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $expiryDate;

    /**
     * @ORM\Column(type="boolean")
     */
    private $taxExempt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=ListItems::class, mappedBy="distributorProduct")
     */
    private $listItems;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isActive;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $itemId;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }

        $this->distributorClinicPrices = new ArrayCollection();
        $this->listItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDistributor(): ?Distributors
    {
        return $this->distributor;
    }

    public function setDistributor(?Distributors $distributor): self
    {
        $this->distributor = $distributor;

        return $this;
    }

    public function getProduct(): ?Products
    {
        return $this->product;
    }

    public function setProduct(?Products $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = $sku;

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

    public function getStockCount(): ?int
    {
        return $this->stockCount;
    }

    public function setStockCount(int $stockCount): self
    {
        $this->stockCount = $stockCount;

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getTaxExempt(): ?bool
    {
        return $this->taxExempt;
    }

    public function setTaxExempt(bool $taxExempt): self
    {
        $this->taxExempt = $taxExempt;

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
     * @return Collection<int, ListItems>
     */
    public function getListItems(): Collection
    {
        return $this->listItems;
    }

    public function addListItem(ListItems $listItem): self
    {
        if (!$this->listItems->contains($listItem)) {
            $this->listItems[] = $listItem;
            $listItem->setDistributorProduct($this);
        }

        return $this;
    }

    public function removeListItem(ListItems $listItem): self
    {
        if ($this->listItems->removeElement($listItem)) {
            // set the owning side to null (unless already changed)
            if ($listItem->getDistributorProduct() === $this) {
                $listItem->setDistributorProduct(null);
            }
        }

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;

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
}
