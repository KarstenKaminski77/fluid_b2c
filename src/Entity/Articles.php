<?php

namespace App\Entity;

use App\Repository\ArticlesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ArticlesRepository::class)
 */
class Articles
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $icon;

    /**
     * @ORM\Column(type="integer")
     */
    private $articleCount;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\OneToMany(targetEntity=ArticleDetails::class, mappedBy="article")
     */
    private $articleDetails;

    /**
     * @ORM\ManyToOne(targetEntity=Pages::class, inversedBy="articles")
     */
    private $page;

    /**
     * @ORM\Column(type="integer")
     */
    private $isMulti;

    public function __construct()
    {
        $this->setModified(new \DateTime());
        if ($this->getCreated() == null) {
            $this->setCreated(new \DateTime());
        }
        $this->articleDetails = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getArticleCount(): ?int
    {
        return $this->articleCount;
    }

    public function setArticleCount(int $articleCount): self
    {
        $this->articleCount = $articleCount;

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
     * @return Collection<int, ArticleDetails>
     */
    public function getArticleDetails(): Collection
    {
        return $this->articleDetails;
    }

    public function addArticleDetail(ArticleDetails $articleDetail): self
    {
        if (!$this->articleDetails->contains($articleDetail)) {
            $this->articleDetails[] = $articleDetail;
            $articleDetail->setArticle($this);
        }

        return $this;
    }

    public function removeArticleDetail(ArticleDetails $articleDetail): self
    {
        if ($this->articleDetails->removeElement($articleDetail)) {
            // set the owning side to null (unless already changed)
            if ($articleDetail->getArticle() === $this) {
                $articleDetail->setArticle(null);
            }
        }

        return $this;
    }

    public function getPage(): ?Pages
    {
        return $this->page;
    }

    public function setPage(?Pages $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getIsMulti(): ?int
    {
        return $this->isMulti;
    }

    public function setIsMulti(int $isMulti): self
    {
        $this->isMulti = $isMulti;

        return $this;
    }
}
