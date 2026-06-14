<?php

namespace App\Service\AnnouncementModeration\Dto;

use App\Entity\User;
use App\Entity\WpPosts;

class AnnouncementModerationContext
{
    /**
     * @param array<string, mixed> $aggregateData
     * @param array<string, string> $meta
     */
    public function __construct(
        private WpPosts $announcement,
        private ?User $owner,
        private array $aggregateData,
        private array $meta
    ) {
    }

    public function getAnnouncement(): WpPosts
    {
        return $this->announcement;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getAnnouncementId(): int
    {
        return (int) $this->announcement->getId();
    }

    public function getUserId(): int
    {
        return (int) $this->announcement->getPostAuthor();
    }

    public function getStatus(): string
    {
        return (string) $this->announcement->getPostStatus();
    }

    public function getTitle(): string
    {
        return trim((string) ($this->announcement->getPostTitle() ?? ''));
    }

    public function getDescription(): string
    {
        return trim((string) ($this->announcement->getPostContent() ?? ''));
    }

    public function getMeta(string $key): string
    {
        return trim((string) ($this->meta[$key] ?? ''));
    }

    public function getPrice(): string
    {
        $price = $this->getMeta('_price');
        if ($price !== '') {
            return $price;
        }

        return trim((string) ($this->aggregateData['prix'] ?? ''));
    }

    public function hasCategory(): bool
    {
        $categoryId = (int) ($this->aggregateData['IdSousCategorie'] ?? 0);
        if ($categoryId > 0) {
            return true;
        }

        return (int) ($this->aggregateData['idCat'] ?? 0) > 0;
    }

    public function hasMainPhoto(): bool
    {
        return $this->getGalleryIds() !== [];
    }

    /**
     * @return string[]
     */
    public function getGalleryIds(): array
    {
        $values = [
            $this->getMeta('_product_image_gallery'),
            $this->getMeta('images_annonces'),
            trim((string) ($this->aggregateData['gallery'] ?? '')),
        ];

        $ids = [];

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }

            foreach (explode(',', $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $ids[] = $part;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function hasLocationCoreFields(): bool
    {
        return $this->getCountry() !== ''
            && $this->getCity() !== ''
            && $this->getAddress() !== '';
    }

    public function getCountry(): string
    {
        $country = $this->getMeta('_product_country');
        if ($country !== '') {
            return $country;
        }

        return trim((string) ($this->aggregateData['pays'] ?? ''));
    }

    public function getCity(): string
    {
        $city = $this->getMeta('_product_city');
        if ($city !== '') {
            return $city;
        }

        return trim((string) ($this->aggregateData['ville'] ?? ''));
    }

    public function getAddress(): string
    {
        $address = $this->getMeta('_product_adress');
        if ($address !== '') {
            return $address;
        }

        return trim((string) ($this->aggregateData['etat'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'announcement' => [
                'id' => $this->getAnnouncementId(),
                'user_id' => $this->getUserId(),
                'status' => $this->getStatus(),
                'title' => $this->getTitle(),
                'description' => $this->getDescription(),
                'post_type' => (string) $this->announcement->getPostType(),
                'slug' => (string) $this->announcement->getPostName(),
            ],
            'owner' => [
                'id' => $this->owner?->getId(),
                'email' => $this->owner?->getEmailCanonical(),
                'display_name' => $this->owner?->getDisplayName(),
            ],
            'aggregate_data' => $this->aggregateData,
            'meta' => $this->meta,
        ];
    }
}
