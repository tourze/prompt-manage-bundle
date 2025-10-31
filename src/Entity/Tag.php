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
use Tourze\PromptManageBundle\Repository\TagRepository;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag', options: ['comment' => '标签表'])]
#[UniqueEntity(fields: ['name'], message: '标签名称已存在')]
class Tag implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 30, unique: true, options: ['comment' => '标签名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $name = '';

    /**
     * @var Collection<int, Prompt>
     */
    #[ORM\ManyToMany(targetEntity: Prompt::class, mappedBy: 'tags')]
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

    /**
     * @return Collection<int, Prompt>
     */
    public function getPrompts(): Collection
    {
        return $this->prompts;
    }

    public function getPromptCount(): int
    {
        return $this->prompts->count();
    }

    public function addPrompt(Prompt $prompt): void
    {
        if (!$this->prompts->contains($prompt)) {
            $this->prompts->add($prompt);
            $prompt->addTag($this);
        }
    }

    public function removePrompt(Prompt $prompt): void
    {
        if ($this->prompts->removeElement($prompt)) {
            $prompt->removeTag($this);
        }
    }
}
