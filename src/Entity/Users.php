<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType("JOINED")]
#[ORM\DiscriminatorColumn(name: "discr", type: "string")]
#[ORM\DiscriminatorMap(["admin" => Admin::class, "candidate" => Candidate::class, "recruiter" => Recruiter::class])]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    protected ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    protected ?string $email = null;

    #[ORM\Column(type: "json")]
    protected array $roles = [];

    #[ORM\Column(length: 255)]
    protected ?string $password = null;

    #[ORM\Column(name: "first_name", length: 100, nullable: true)]
    protected ?string $firstName = null;

    #[ORM\Column(name: "last_name", length: 100, nullable: true)]
    protected ?string $lastName = null;

    #[ORM\Column(length: 30, nullable: true)]
    protected ?string $phone = null;

    #[ORM\Column(name: "is_active")]
    protected bool $isActive = true;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE)]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "forget_code", length: 10, nullable: true)]
    protected ?string $forgetCode = null;

    #[ORM\Column(name: "forget_code_expires", type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $forgetCodeExpires = null;

    #[ORM\Column(name: "face_person_id", length: 128, nullable: true)]
    protected ?string $facePersonId = null;

    #[ORM\Column(name: "face_enabled")]
    protected bool $faceEnabled = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    /**
     * @see UserInterface
     */
    public function getRoles(): array 
    { 
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles); 
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getForgetCode(): ?string { return $this->forgetCode; }
    public function setForgetCode(?string $forgetCode): self { $this->forgetCode = $forgetCode; return $this; }

    public function getForgetCodeExpires(): ?\DateTimeInterface { return $this->forgetCodeExpires; }
    public function setForgetCodeExpires(?\DateTimeInterface $forgetCodeExpires): self { $this->forgetCodeExpires = $forgetCodeExpires; return $this; }

    public function getFacePersonId(): ?string { return $this->facePersonId; }
    public function setFacePersonId(?string $facePersonId): self { $this->facePersonId = $facePersonId; return $this; }

    public function isFaceEnabled(): bool { return $this->faceEnabled; }
    public function setFaceEnabled(bool $faceEnabled): self { $this->faceEnabled = $faceEnabled; return $this; }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void {}

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string { return (string) $this->email; }
}