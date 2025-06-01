<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Translatable\Translatable;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\WpUsermeta;
use App\Entity\Channel;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Gedmo\Mapping\Annotation as Gedmo;
use function array_merge;

#[ORM\Table(name: "`wp_users`")]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ["email_canonical"], message: "Cette adresse email est déjà utilisée.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct()
    {
        $this->userRegistered = new DateTime();
        $this->updatedAt = new DateTime();
        $this->sentMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
        $this->abonnements = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getDisplayName() . '-' . $this->getEmailCanonical();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $id;

    #[ORM\Column(name: "user_nicename", type: "string", length: 50, nullable: false)]
    private $userNicename = '';

    #[ORM\Column(name: "username_canonical", type: "string", length: 255, nullable: false)]
    private $usernameCanonical = '';

    #[ORM\Column(name: "user_registered", type: "datetime", nullable: false)]
    private $userRegistered;

    #[ORM\Column(name: "updated_at", type: "datetime", nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: "user_activation_key", type: "string", length: 255, nullable: false)]
    private $userActivationKey = '';

    #[ORM\Column(name: "display_name", type: "string", length: 250, nullable: false)]
    private $displayName = '';

    #[ORM\Column(name: "landing_page", type: "string", length: 250, nullable: true)]
    private $landingPage = '';

    #[ORM\Column(name: "user_status", type: "integer", nullable: false)]
    private $userStatus = '0';

    #[ORM\Column(name: "enabled", type: "integer", nullable: false)]
    private $enabled = '0';

    #[ORM\Column(name: "last_activity_at", type: "datetime", nullable: true)]
    private $lastActivityAt;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: "sender")]
    private $sentMessages;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: "addressee")]
    private $receivedMessages;

    #[ORM\OneToMany(targetEntity: Channel::class, mappedBy: "firstUser")]
    private $channels1;

    #[ORM\OneToMany(targetEntity: Channel::class, mappedBy: "secondUser")]
    private $channels2;

    #[ORM\Column(name: "email_canonical", type: "string", length: 180, unique: true)]
    #[Assert\Email(message: 'Adresse {{ value }} non valide.')]
    private $email_canonical;

    #[ORM\Column(name: "userEmail", type: "string", length: 180, unique: true)]
    private $userEmail;

    #[ORM\Column(type: "json")]
    private $roles;

    #[ORM\Column(name: "userPass", type: "string")]
    private $password;

    #[ORM\Column(name: "is_verified", type: "boolean")]
    private $isVerified = false;

    #[ORM\Column(name: "date_naissance", type: "string", length: 190, nullable: true)]
    private $dateNaissance;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserUniqueData $userUniqueData= null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Abonnement::class, orphanRemoval: true)]
    #[OrderBy(["id" => "DESC"])]
    private Collection $abonnements;

    #[ORM\OneToOne(mappedBy: 'profile', targetEntity: InformationsFacturationUtilisateur::class, cascade: ['persist', 'remove'])]
    private ?InformationsFacturationUtilisateur $informationsFacturationUtilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailCanonical(): ?string
    {
        return $this->email_canonical;
    }

    public function setEmailCanonical(string $email_canonical): static
    {
        $this->email_canonical = $email_canonical;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->displayName;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return (string) $this->email_canonical;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }


    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    //UsernameCanonical
    public function getUsernameCanonical(): ?string
    {
        return $this->usernameCanonical;
    }

    public function setUsernameCanonical(string $usernameCanonical): static
    {
        $this->usernameCanonical = $usernameCanonical;
        return $this;
    }

    //User created at
    public function getUserRegistered(): ?DateTime
    {
        return $this->userRegistered;
    }

    public function setUserRegistered(string $userRegistered): static
    {
        $this->userRegistered = $userRegistered;
        return $this;
    }

    //User updated at
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }


    //User activation key
    public function getUserActivationKey(): ?string
    {
        return $this->userActivationKey;
    }

    public function setUserActivationKey(string $userActivationKey): static
    {
        $this->userActivationKey = $userActivationKey;

        return $this;
    }

    //User Nicename
    public function getUserNicename(): ?string
    {
        return $this->userNicename;
    }

    public function setUserNicename(string $userNicename): static
    {
        $this->userNicename = $userNicename;

        return $this;
    }

    //User Display name
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    //User Display name
    public function getEnabled(): ?int
    {
        return $this->enabled;
    }

    public function setEnabled(int $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }


    //UserEmail
    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    //UserStatus
    public function getUserStatus(): ?string
    {
        return $this->userStatus;
    }

    public function setUserStatus(string $userStatus): static
    {
        $this->userStatus = $userStatus;

        return $this;
    }

    public function getDateNaissance(): ?string
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?string $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    /**
     * @param Datetime $lastActivityAt
     */
    public function setLastActivityAt(DateTime $lastActivityAt)
    {
        $this->lastActivityAt = $lastActivityAt;
    }

    /**
     * @return Datetime
     */
    public function getLastActivityAt(): ?Datetime
    {
        return $this->lastActivityAt;
    }

    /**
     * @return Bool Whether the user is active or not
     */
    public function isActiveNow()
    {
        // Delay during wich the user will be considered as still active
        $delay = new DateTime('2 minutes ago');

        return ($this->getLastActivityAt() > $delay);
    }

    /**
     * @return Collection|Message[]
     */
    public function getSentMessages(): Collection
    {
        return $this->sentMessages;
    }

    /**
     * @return Collection|Message[]
     */
    public function getReceivedMessages(): Collection
    {
        return $this->receivedMessages;
    }


    public function getUserUniqueData(): ?UserUniqueData
    {
        return $this->userUniqueData;
    }

    public function setUserUniqueData(UserUniqueData $userUniqueData): static
    {
        // set the owning side of the relation if necessary
        if ($userUniqueData->getUser() !== $this) {
            $userUniqueData->setUser($this);
        }

        $this->userUniqueData = $userUniqueData;

        return $this;
    }

    /**
     * @return Collection<int, Abonnement>
     */
    public function getAbonnements(): Collection
    {
        return $this->abonnements;
    }

    public function addAbonnement(Abonnement $abonnement): static
    {
        if (!$this->abonnements->contains($abonnement)) {
            $this->abonnements->add($abonnement);
            $abonnement->setUser($this);
        }

        return $this;
    }

    public function removeAbonnement(Abonnement $abonnement): static
    {
        if ($this->abonnements->removeElement($abonnement)) {
            // set the owning side to null (unless already changed)
            if ($abonnement->getUser() === $this) {
                $abonnement->setUser(null);
            }
        }

        return $this;
    }

    public function getInformationsFacturationUtilisateur(): ?InformationsFacturationUtilisateur
    {
        return $this->informationsFacturationUtilisateur;
    }

    public function setInformationsFacturationUtilisateur(InformationsFacturationUtilisateur $informationsFacturationUtilisateur): static
    {
        // set the owning side of the relation if necessary
        if ($informationsFacturationUtilisateur->getProfile() !== $this) {
            $informationsFacturationUtilisateur->setProfile($this);
        }

        $this->informationsFacturationUtilisateur = $informationsFacturationUtilisateur;

        return $this;
    }
}
