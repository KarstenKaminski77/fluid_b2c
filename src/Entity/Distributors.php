<?php

namespace App\Entity;

use App\Repository\DistributorsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DistributorsRepository::class)
 */
class Distributors
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
    private $distributorName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $logo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $telephone;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $website;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $about;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $operatingHours;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $refundPolicy;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $salesTaxPolicy;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isManufaturer;

    /**
     * @ORM\Column(type="integer", nullable=true, nullable=true)
     */
    private $themeId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=Baskets::class, mappedBy="distributor")
     */
    private $baskets;

    /**
     * @ORM\OneToMany(targetEntity=DistributorProducts::class, mappedBy="distributor")
     */
    private $distributorProducts;

    /**
     * @ORM\OneToMany(targetEntity=EventLog::class, mappedBy="distributor")
     */
    private $eventLogs;

    /**
     * @ORM\OneToMany(targetEntity=ListItems::class, mappedBy="distributor")
     */
    private $listItems;

    /**
     * @ORM\Column(type="string", length=3, nullable=true)
     */
    private $poNumberPrefix;

    /**
     * @ORM\OneToMany(targetEntity=Notifications::class, mappedBy="distributor")
     */
    private $notifications;

    /**
     * @ORM\OneToMany(targetEntity=OrderStatus::class, mappedBy="distributor")
     */
    private $orderStatuses;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $addressStreet;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $addressCity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $addressPostalCode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $addressState;

    /**
     * @ORM\ManyToOne(targetEntity=Countries::class, inversedBy="distributors")
     */
    private $addressCountry;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $shippingPolicy;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $isoCode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $intlCode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $hashedEmail;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $domainName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $isApproved;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $managerFirstName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $managerLastName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $tradeLicense;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $tradeLicenseNo;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $tradeLicenseExpDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $managerIdNo;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $managerIdExpDate;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }

        $this->distributors = new ArrayCollection();
        $this->baskets = new ArrayCollection();
        $this->distributorClinicPrices = new ArrayCollection();
        $this->distributorProducts = new ArrayCollection();
        $this->distributorUsers = new ArrayCollection();
        $this->eventLogs = new ArrayCollection();
        $this->listItems = new ArrayCollection();
        $this->clinicProducts = new ArrayCollection();
        $this->chatParticipants = new ArrayCollection();
        $this->chatMessages = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->orderStatuses = new ArrayCollection();
        $this->distributorUserPermissions = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
        $this->distributorClinics = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDistributorName(): ?string
    {
        return $this->distributorName;
    }

    public function setDistributorName(string $distributorName): self
    {
        $this->distributorName = $distributorName;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getAbout(): ?string
    {
        return $this->about;
    }

    public function setAbout(string $about): self
    {
        $this->about = $about;

        return $this;
    }

    public function getOperatingHours(): ?string
    {
        return $this->operatingHours;
    }

    public function setOperatingHours(string $operatingHours): self
    {
        $this->operatingHours = $operatingHours;

        return $this;
    }

    public function getRefundPolicy(): ?string
    {
        return $this->refundPolicy;
    }

    public function setRefundPolicy(string $refundPolicy): self
    {
        $this->refundPolicy = $refundPolicy;

        return $this;
    }

    public function getSalesTaxPolicy(): ?string
    {
        return $this->salesTaxPolicy;
    }

    public function setSalesTaxPolicy(string $salesTaxPolicy): self
    {
        $this->salesTaxPolicy = $salesTaxPolicy;

        return $this;
    }

    public function getIsManufaturer(): ?bool
    {
        return $this->isManufaturer;
    }

    public function setIsManufaturer(bool $isManufaturer): self
    {
        $this->isManufaturer = $isManufaturer;

        return $this;
    }

    public function getThemeId(): ?int
    {
        return $this->themeId;
    }

    public function setThemeId(?int $themeId): self
    {
        $this->themeId = $themeId;

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
     * @return Collection|Baskets[]
     */
    public function getBaskets(): Collection
    {
        return $this->baskets;
    }

    public function addBasket(Baskets $basket): self
    {
        if (!$this->baskets->contains($basket)) {
            $this->baskets[] = $basket;
            $basket->setDistributor($this);
        }

        return $this;
    }

    public function removeBasket(Baskets $basket): self
    {
        if ($this->baskets->removeElement($basket)) {
            // set the owning side to null (unless already changed)
            if ($basket->getDistributor() === $this) {
                $basket->setDistributor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DistributorProducts[]
     */
    public function getDistributorProducts(): Collection
    {
        return $this->distributorProducts;
    }

    public function addDistributorProduct(DistributorProducts $distributorProduct): self
    {
        if (!$this->distributorProducts->contains($distributorProduct)) {
            $this->distributorProducts[] = $distributorProduct;
            $distributorProduct->setDistributor($this);
        }

        return $this;
    }

    public function removeDistributorProduct(DistributorProducts $distributorProduct): self
    {
        if ($this->distributorProducts->removeElement($distributorProduct)) {
            // set the owning side to null (unless already changed)
            if ($distributorProduct->getDistributor() === $this) {
                $distributorProduct->setDistributor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|EventLog[]
     */
    public function getEventLogs(): Collection
    {
        return $this->eventLogs;
    }

    public function addEventLog(EventLog $eventLog): self
    {
        if (!$this->eventLogs->contains($eventLog)) {
            $this->eventLogs[] = $eventLog;
            $eventLog->setDistributor($this);
        }

        return $this;
    }

    public function removeEventLog(EventLog $eventLog): self
    {
        if ($this->eventLogs->removeElement($eventLog)) {
            // set the owning side to null (unless already changed)
            if ($eventLog->getDistributor() === $this) {
                $eventLog->setDistributor(null);
            }
        }

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
            $listItem->setDistributor($this);
        }

        return $this;
    }

    public function removeListItem(ListItems $listItem): self
    {
        if ($this->listItems->removeElement($listItem)) {
            // set the owning side to null (unless already changed)
            if ($listItem->getDistributor() === $this) {
                $listItem->setDistributor(null);
            }
        }

        return $this;
    }

    public function getPoNumberPrefix(): ?string
    {
        return $this->poNumberPrefix;
    }

    public function setPoNumberPrefix(?string $poNumberPrefix): self
    {
        $this->poNumberPrefix = $poNumberPrefix;

        return $this;
    }

    /**
     * @return Collection<int, Notifications>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notifications $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications[] = $notification;
            $notification->setDistributors($this);
        }

        return $this;
    }

    public function removeNotification(Notifications $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getDistributors() === $this) {
                $notification->setDistributors(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderStatus>
     */
    public function getOrderStatuses(): Collection
    {
        return $this->orderStatuses;
    }

    public function addOrderStatus(OrderStatus $orderStatus): self
    {
        if (!$this->orderStatuses->contains($orderStatus)) {
            $this->orderStatuses[] = $orderStatus;
            $orderStatus->setDistributor($this);
        }

        return $this;
    }

    public function removeOrderStatus(OrderStatus $orderStatus): self
    {
        if ($this->orderStatuses->removeElement($orderStatus)) {
            // set the owning side to null (unless already changed)
            if ($orderStatus->getDistributor() === $this) {
                $orderStatus->setDistributor(null);
            }
        }

        return $this;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function setAddressStreet(?string $addressStreet): self
    {
        $this->addressStreet = $addressStreet;

        return $this;
    }

    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    public function setAddressCity(?string $addressCity): self
    {
        $this->addressCity = $addressCity;

        return $this;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function setAddressPostalCode(?string $addressPostalCode): self
    {
        $this->addressPostalCode = $addressPostalCode;

        return $this;
    }

    public function getAddressState(): ?string
    {
        return $this->addressState;
    }

    public function setAddressState(?string $addressState): self
    {
        $this->addressState = $addressState;

        return $this;
    }

    public function getAddressCountry(): ?Countries
    {
        return $this->addressCountry;
    }

    public function setAddressCountry(?Countries $addressCountry): self
    {
        $this->addressCountry = $addressCountry;

        return $this;
    }

    public function getShippingPolicy(): ?string
    {
        return $this->shippingPolicy;
    }

    public function setShippingPolicy(?string $shippingPolicy): self
    {
        $this->shippingPolicy = $shippingPolicy;

        return $this;
    }

    public function getIsoCode(): ?string
    {
        return $this->isoCode;
    }

    public function setIsoCode(?string $isoCode): self
    {
        $this->isoCode = $isoCode;

        return $this;
    }

    public function getIntlCode(): ?string
    {
        return $this->intlCode;
    }

    public function setIntlCode(?string $intlCode): self
    {
        $this->intlCode = $intlCode;

        return $this;
    }

    public function getHashedEmail(): ?string
    {
        return $this->hashedEmail;
    }

    public function setHashedEmail(?string $hashedEmail): self
    {
        $this->hashedEmail = $hashedEmail;

        return $this;
    }

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function setDomainName(?string $domainName): self
    {
        $this->domainName = $domainName;

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

    public function getManagerFirstName(): ?string
    {
        return $this->managerFirstName;
    }

    public function setManagerFirstName(?string $managerFirstName): self
    {
        $this->managerFirstName = $managerFirstName;

        return $this;
    }

    public function getManagerLastName(): ?string
    {
        return $this->managerLastName;
    }

    public function setManagerLastName(?string $managerLastName): self
    {
        $this->managerLastName = $managerLastName;

        return $this;
    }

    public function getTradeLicense(): ?string
    {
        return $this->tradeLicense;
    }

    public function setTradeLicense(?string $tradeLicense): self
    {
        $this->tradeLicense = $tradeLicense;

        return $this;
    }

    public function getTradeLicenseNo(): ?string
    {
        return $this->tradeLicenseNo;
    }

    public function setTradeLicenseNo(?string $tradeLicenseNo): self
    {
        $this->tradeLicenseNo = $tradeLicenseNo;

        return $this;
    }

    public function getTradeLicenseExpDate(): ?\DateTimeInterface
    {
        return $this->tradeLicenseExpDate;
    }

    public function setTradeLicenseExpDate(?\DateTimeInterface $tradeLicenseExpDate): self
    {
        $this->tradeLicenseExpDate = $tradeLicenseExpDate;

        return $this;
    }

    public function getManagerIdNo(): ?string
    {
        return $this->managerIdNo;
    }

    public function setManagerIdNo(?string $managerIdNo): self
    {
        $this->managerIdNo = $managerIdNo;

        return $this;
    }

    public function getManagerIdExpDate(): ?\DateTimeInterface
    {
        return $this->managerIdExpDate;
    }

    public function setManagerIdExpDate(?\DateTimeInterface $managerIdExpDate): self
    {
        $this->managerIdExpDate = $managerIdExpDate;

        return $this;
    }
}
