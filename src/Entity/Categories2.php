<?php

namespace App\Entity;

use App\Repository\Categories2Repository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=Categories2Repository::class)
 */
class Categories2
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Categories1::class, inversedBy="categories2s")
     */
    private $category1;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $tags = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=Categories3::class, mappedBy="category2")
     */
    private $categories3;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $slug;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $tagsArray;

    /**
     * @ORM\OneToMany(targetEntity=Products::class, mappedBy="category2")
     */
    private $products;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $productCount;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        if ($this->getModified() == null) {
            $this->setModified(new \DateTime());
        }

        $this->categories3 = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory1(): ?Categories1
    {
        return $this->category1;
    }

    public function setCategory1(?Categories1 $category1): self
    {
        $this->category1 = $category1;

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

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;

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
     * @return Collection<int, Categories3>
     */
    public function getCategories3(): Collection
    {
        return $this->categories3;
    }

    public function addCategories3(Categories3 $categories3): self
    {
        if (!$this->categories3->contains($categories3)) {
            $this->categories3[] = $categories3;
            $categories3->setCategory2($this);
        }

        return $this;
    }

    public function removeCategories3(Categories3 $categories3): self
    {
        if ($this->categories3->removeElement($categories3)) {
            // set the owning side to null (unless already changed)
            if ($categories3->getCategory2() === $this) {
                $categories3->setCategory2(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTagsArray(): ?array
    {
        return $this->tagsArray;
    }

    public function setTagsArray(array $tagsArray): self
    {
        $this->tagsArray = $tagsArray;

        return $this;
    }

    /**
     * @return Collection<int, Products>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Products $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setCategory2($this);
        }

        return $this;
    }

    public function removeProduct(Products $product): self
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getCategory2() === $this) {
                $product->setCategory2(null);
            }
        }

        return $this;
    }

    public function getProductCount(): ?int
    {
        return $this->productCount;
    }

    public function setProductCount(?int $productCount): self
    {
        $this->productCount = $productCount;

        return $this;
    }
}
