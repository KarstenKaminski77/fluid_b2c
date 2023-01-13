<?php

namespace App\Entity;

use App\Repository\ManufacturersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ManufacturersRepository::class)
 */
class Manufacturers
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
     * @ORM\OneToMany(targetEntity=ProductManufacturers::class, mappedBy="manufacturers")
     */
    private $productManufacturers;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $firstName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $lastName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $hashedEmail;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $telephone;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $isoCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $intlCode;

    /**
     * @ORM\OneToMany(targetEntity=ManufacturerUsers::class, mappedBy="manufacturer")
     */
    private $manufacturerUsers;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $logo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $domainName;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        if ($this->getModified() == null) {
            $this->setModified(new \DateTime());
        }
        $this->productManufacturers = new ArrayCollection();
        $this->manufacturerUsers = new ArrayCollection();
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
     * @return Collection|Products[]
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProducts(Products $products): self
    {
        if (!$this->products->contains($products)) {
            $this->products[] = $products;
        }

        return $this;
    }

    public function removeProducts(Products $products): self
    {
        $this->products->removeElement($products);

        return $this;
    }

    /**
     * @return string
     */
    public function __toString(){

        return $this->getName();
    }

    /**
     * @return Collection<int, ProductManufacturers>
     */
    public function getProductManufacturers(): Collection
    {
        return $this->productManufacturers;
    }

    public function addProductManufacturer(ProductManufacturers $productManufacturer): self
    {
        if (!$this->productManufacturers->contains($productManufacturer)) {
            $this->productManufacturers[] = $productManufacturer;
            $productManufacturer->setManufacturers($this);
        }

        return $this;
    }

    public function removeProductManufacturer(ProductManufacturers $productManufacturer): self
    {
        if ($this->productManufacturers->removeElement($productManufacturer)) {
            // set the owning side to null (unless already changed)
            if ($productManufacturer->getManufacturers() === $this) {
                $productManufacturer->setManufacturers(null);
            }
        }

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

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

    public function getHashedEmail(): ?string
    {
        return $this->hashedEmail;
    }

    public function setHashedEmail(string $hashedEmail): self
    {
        $this->hashedEmail = $hashedEmail;

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

    public function getIsoCode(): ?string
    {
        return $this->isoCode;
    }

    public function setIsoCode(string $isoCode): self
    {
        $this->isoCode = $isoCode;

        return $this;
    }

    public function getIntlCode(): ?string
    {
        return $this->intlCode;
    }

    public function setIntlCode(string $intlCode): self
    {
        $this->intlCode = $intlCode;

        return $this;
    }

    /**
     * @return Collection<int, ManufacturerUsers>
     */
    public function getManufacturerUsers(): Collection
    {
        return $this->manufacturerUsers;
    }

    public function addManufacturerUser(ManufacturerUsers $manufacturerUser): self
    {
        if (!$this->manufacturerUsers->contains($manufacturerUser)) {
            $this->manufacturerUsers[] = $manufacturerUser;
            $manufacturerUser->setManufacturer($this);
        }

        return $this;
    }

    public function removeManufacturerUser(ManufacturerUsers $manufacturerUser): self
    {
        if ($this->manufacturerUsers->removeElement($manufacturerUser)) {
            // set the owning side to null (unless already changed)
            if ($manufacturerUser->getManufacturer() === $this) {
                $manufacturerUser->setManufacturer(null);
            }
        }

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

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function setDomainName(?string $domainName): self
    {
        $this->domainName = $domainName;

        return $this;
    }
}
