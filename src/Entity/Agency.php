<?php

namespace App\Entity;

use App\Repository\AgencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Event\PreUpdateEventArgs;

#[ORM\Entity(repositoryClass: AgencyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Agency 
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(length: 64)]
    private ?string $timezone = null;

    #[ORM\Column(nullable: true)]
    private ?array $totalsPolicy = null;

    #[ORM\Column(nullable: true)]
    private ?array $settings = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, AgencyRubric>
     */
    #[ORM\OneToMany(targetEntity: AgencyRubric::class, mappedBy: 'agency', cascade: ['remove'])]
    private Collection $agencyRubrics;

    public function __construct()
    {
        $this->active = true;
        $this->timezone = 'Europe/Paris';
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->agencyRubrics = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? $this->code ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTotalsPolicy(): ?array
    {
        return $this->totalsPolicy;
    }

    public function setTotalsPolicy(?array $totalsPolicy): static
    {
        $this->totalsPolicy = $totalsPolicy;

        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, AgencyRubric>
     */
    public function getAgencyRubrics(): Collection
    {
        return $this->agencyRubrics;
    }

    public function addAgencyRubric(AgencyRubric $agencyRubric): static
    {
        if (!$this->agencyRubrics->contains($agencyRubric)) {
            $this->agencyRubrics->add($agencyRubric);
            $agencyRubric->setAgency($this);
        }

        return $this;
    }

    public function removeAgencyRubric(AgencyRubric $agencyRubric): static
    {
        if ($this->agencyRubrics->removeElement($agencyRubric)) {
            // set the owning side to null (unless already changed)
            if ($agencyRubric->getAgency() === $this) {
                $agencyRubric->setAgency(null);
            }
        }

        return $this;
    }
}
