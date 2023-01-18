<?php

namespace App\Entity;

use App\Repository\ClinicsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClinicsRepository::class)
 */
class Clinics
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
    private $clinicName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $telephone;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=Addresses::class, mappedBy="retail")
     */
    private $addresses;

    /**
     * @ORM\OneToMany(targetEntity=Distributors::class, mappedBy="clinic")
     */
    private $distributors;

    /**
     * @ORM\OneToMany(targetEntity=Baskets::class, mappedBy="clinic")
     */
    private $baskets;

    /**
     * @ORM\OneToMany(targetEntity=EventLog::class, mappedBy="clinic")
     */
    private $eventLogs;

    /**
     * @ORM\OneToMany(targetEntity=Notifications::class, mappedBy="clinic")
     */
    private $notifications;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $isoCode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $intlCode;

    /**
     * @ORM\ManyToOne(targetEntity=Countries::class, inversedBy="clinics")
     */
    private $country;

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

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $logo;

    /**
     * @ORM\OneToMany(targetEntity=ClinicRetailUsers::class, mappedBy="clinic")
     */
    private $clinicRetailUsers;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $PoNumberPrefix;

    /**
     * @ORM\Column(type="text", nullable=true)
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
     * @ORM\Column(type="text", nullable=true)
     */
    private $shippingPolicy;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }

        $this->addresses = new ArrayCollection();
        $this->baskets = new ArrayCollection();
        $this->distributors = new ArrayCollection();
        $this->clinicCommunicationMethods = new ArrayCollection();
        $this->clinicUsers = new ArrayCollection();
        $this->distributorClinicPrices = new ArrayCollection();
        $this->eventLogs = new ArrayCollection();
        $this->productNotes = new ArrayCollection();
        $this->clinicProducts = new ArrayCollection();
        $this->productReviewComments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->productFavourites = new ArrayCollection();
        $this->chatParticipants = new ArrayCollection();
        $this->clinicUserPermissions = new ArrayCollection();
        $this->distributorClinics = new ArrayCollection();
        $this->productRetails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClinicName(): ?string
    {
        return $this->clinicName;
    }

    public function setClinicName(string $clinicName): self
    {
        $this->clinicName = $clinicName;

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
     * @return Collection|Addresses[]
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Addresses $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses[] = $address;
            $address->setClinic($this);
        }

        return $this;
    }

    public function removeAddress(Addresses $address): self
    {
        if ($this->addresses->removeElement($address)) {
            // set the owning side to null (unless already changed)
            if ($address->getClinic() === $this) {
                $address->setClinic(null);
            }
        }

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
            $basket->setClinic($this);
        }

        return $this;
    }

    public function removeBasket(Baskets $basket): self
    {
        if ($this->baskets->removeElement($basket)) {
            // set the owning side to null (unless already changed)
            if ($basket->getClinic() === $this) {
                $basket->setClinic(null);
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
            $eventLog->setClinic($this);
        }

        return $this;
    }

    public function removeEventLog(EventLog $eventLog): self
    {
        if ($this->eventLogs->removeElement($eventLog)) {
            // set the owning side to null (unless already changed)
            if ($eventLog->getClinic() === $this) {
                $eventLog->setClinic(null);
            }
        }

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
            $notification->setClinic($this);
        }

        return $this;
    }

    public function removeNotification(Notifications $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getClinic() === $this) {
                $notification->setClinic(null);
            }
        }

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

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function setCountry(?Countries $country): self
    {
        $this->country = $country;

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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function getPoNumberPrefix(): ?string
    {
        return $this->PoNumberPrefix;
    }

    public function setPoNumberPrefix(?string $PoNumberPrefix): self
    {
        $this->PoNumberPrefix = $PoNumberPrefix;

        return $this;
    }

    public function getAbout(): ?string
    {
        return $this->about;
    }

    public function setAbout(?string $about): self
    {
        $this->about = $about;

        return $this;
    }

    public function getOperatingHours(): ?string
    {
        return $this->operatingHours;
    }

    public function setOperatingHours(?string $operatingHours): self
    {
        $this->operatingHours = $operatingHours;

        return $this;
    }

    public function getRefundPolicy(): ?string
    {
        return $this->refundPolicy;
    }

    public function setRefundPolicy(?string $refundPolicy): self
    {
        $this->refundPolicy = $refundPolicy;

        return $this;
    }

    public function getSalesTaxPolicy(): ?string
    {
        return $this->salesTaxPolicy;
    }

    public function setSalesTaxPolicy(?string $salesTaxPolicy): self
    {
        $this->salesTaxPolicy = $salesTaxPolicy;

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
}
