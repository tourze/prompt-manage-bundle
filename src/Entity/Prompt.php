<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\CreatedByAware;
use Tourze\PromptManageBundle\Repository\PromptRepository;

#[ORM\Entity(repositoryClass: PromptRepository::class)]
#[ORM\Table(name: 'prompt', options: ['comment' => 'AI提示词主表'])]
class Prompt implements \Stringable
{
    use TimestampableAware;
    use CreatedByAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '提示词名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '当前版本号', 'default' => 1])]
    #[Assert\PositiveOrZero]
    private int $currentVersion = 1;

    /**
     * @var Collection<int, PromptVersion>
     */
    #[ORM\OneToMany(targetEntity: PromptVersion::class, mappedBy: 'prompt', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(value: ['version' => 'DESC'])]
    private Collection $versions;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'prompts')]
    #[ORM\JoinTable(
        name: 'prompt_tags',
        joinColumns: [new ORM\JoinColumn(name: 'prompt_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    )]
    private Collection $tags;

    #[ORM\Column(length: 30, options: [' comment' => '可见性策略', 'default' =>'private'])]
    #[Assert\Choice(['public', 'private'])]
    private string $visibility = 'private';

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): void
    {
        $this->project = $project;
    }

    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }

    public function setCurrentVersion(int $currentVersion): void
    {
        $this->currentVersion = $currentVersion;
    }

    /**
     * @return Collection<int, PromptVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(PromptVersion $version): void
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setPrompt($this);
        }
    }

    public function removeVersion(PromptVersion $version): void
    {
        if ($this->versions->removeElement($version)) {
            if ($version->getPrompt() === $this) {
                $version->setPrompt(null);
            }
        }
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): void
    {
        if (!in_array($visibility, ['public', 'private'], true)) {
            throw new InvalidArgumentException(sprintf('不支持的可见性: %s', $visibility));
        }

        $this->visibility = $visibility;
    }

    /**
     * 获取当前版本的内容
     */
    public function getCurrentVersionContent(): ?string
    {
        $targetVersion = $this->currentVersion;
        /** @var PromptVersion|false $currentVersion */
        $currentVersion = $this->versions->filter(
            static fn (PromptVersion $version): bool => $version->getVersion() === $targetVersion
        )->first();

        return false !== $currentVersion ? $currentVersion->getContent() : null;
    }
}
