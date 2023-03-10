<?php

namespace App\Entity;

use App\Repository\ListsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ListsRepository::class)
 */
class Lists
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
    @ORM\Column(type="integer", nullable=true)
     */
    private $clinicId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $listType;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="integer")
     */
    private $itemCount;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=ListItems::class, mappedBy="list")
     */
    private $listItems;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isProtected;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        if ($this->getModified() == null) {
            $this->setModified(new \DateTime());
        }
        $this->listItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClinicId(): ?int
    {
        return $this->clinicId;
    }

    public function setClinicId(?int $clinicId): self
    {
        $this->clinicId = $clinicId;

        return $this;
    }

    public function getListType(): ?string
    {
        return $this->listType;
    }

    public function setListType(string $listType): self
    {
        $this->listType = $listType;

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

    public function getItemCount(): ?int
    {
        return $this->itemCount;
    }

    public function setItemCount(int $itemCount): self
    {
        $this->itemCount = $itemCount;

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
            $listItem->setList($this);
        }

        return $this;
    }

    public function removeListItem(ListItems $listItem): self
    {
        if ($this->listItems->removeElement($listItem)) {
            // set the owning side to null (unless already changed)
            if ($listItem->getList() === $this) {
                $listItem->setList(null);
            }
        }

        return $this;
    }

    public function getIsProtected(): ?bool
    {
        return $this->isProtected;
    }

    public function setIsProtected(?bool $isProtected): self
    {
        $this->isProtected = $isProtected;

        return $this;
    }
}
