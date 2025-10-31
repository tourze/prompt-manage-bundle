<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\PromptManageBundle\Repository\ProjectRepository;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project', options: ['comment' => '项目表'])]
#[UniqueEntity(fields: ['name'], message: '项目名称已存在')]
class Project implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, options: ['comment' => '项目名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '项目描述'])]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    /**
     * @var Collection<int, Prompt>
     */
    #[ORM\OneToMany(targetEntity: Prompt::class, mappedBy: 'project')]
    private Collection $prompts;

    public function __construct()
    {
        $this->prompts = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function getPrompts(): Collection
    {
        return $this->prompts;
    }

    public function addPrompt(Prompt $prompt): void
    {
        if (!$this->prompts->contains($prompt)) {
            $this->prompts->add($prompt);
            $prompt->setProject($this);
        }
    }

    public function removePrompt(Prompt $prompt): void
    {
        if ($this->prompts->removeElement($prompt)) {
            if ($prompt->getProject() === $this) {
                $prompt->setProject(null);
            }
        }
    }

    /**
     * 获取项目下的提示词数量（虚拟字段，用于EasyAdmin显示）
     */
    public function getPromptCount(): int
    {
        return $this->prompts->count();
    }
}
