<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\CreatedByAware;
use Tourze\PromptManageBundle\Repository\PromptVersionRepository;

#[ORM\Entity(repositoryClass: PromptVersionRepository::class)]
#[ORM\Table(name: 'prompt_version', options: ['comment' => '提示词版本表'])]
#[ORM\UniqueConstraint(name: 'uk_prompt_version', columns: ['prompt_id', 'version'])]
#[UniqueEntity(fields: ['prompt', 'version'], message: '同一提示词的版本号不能重复')]
class PromptVersion implements \Stringable
{
    use TimestampableAware;
    use CreatedByAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prompt::class, inversedBy: 'versions', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'prompt_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    private ?Prompt $prompt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '版本号（从1开始）'])]
    #[Assert\Positive]
    private int $version = 1;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '内容模板'])]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '变更说明'])]
    #[Assert\Length(max: 255)]
    private ?string $changeNote = null;

    public function __toString(): string
    {
        return sprintf('v%d - %s', $this->version, $this->prompt?->getName() ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrompt(): ?Prompt
    {
        return $this->prompt;
    }

    public function setPrompt(?Prompt $prompt): void
    {
        $this->prompt = $prompt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getChangeNote(): ?string
    {
        return $this->changeNote;
    }

    public function setChangeNote(?string $changeNote): void
    {
        $this->changeNote = $changeNote;
    }
}
