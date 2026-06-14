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
        return $this->normalizeTextValue($this->meta[$key] ?? '');
    }

    public function getPrice(): string
    {
        $price = $this->getMeta('_price');
        if ($price !== '') {
            return $price;
        }

        return $this->normalizeTextValue($this->aggregateData['prix'] ?? '');
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
        $ids = [];
        $ids = array_merge($ids, $this->extractCommaSeparatedIds($this->meta['_product_image_gallery'] ?? ''));
        $ids = array_merge($ids, $this->extractCommaSeparatedIds($this->meta['images_annonces'] ?? ''));
        $ids = array_merge($ids, $this->extractCommaSeparatedIds($this->aggregateData['gallery'] ?? ''));

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

        return $this->normalizeTextValue($this->aggregateData['pays'] ?? '');
    }

    public function getCity(): string
    {
        $city = $this->getMeta('_product_city');
        if ($city !== '') {
            return $city;
        }

        return $this->normalizeTextValue($this->aggregateData['ville'] ?? '');
    }

    public function getAddress(): string
    {
        $address = $this->getMeta('_product_adress');
        if ($address !== '') {
            return $address;
        }

        return $this->normalizeTextValue($this->aggregateData['etat'] ?? '');
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

    /**
     * @param mixed $value
     */
    private function normalizeTextValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $parts[] = $item;
                    }
                }
            }

            return implode(',', $parts);
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function extractCommaSeparatedIds($value): array
    {
        $normalized = $this->normalizeTextValue($value);
        if ($normalized === '') {
            return [];
        }

        $ids = [];

        foreach (explode(',', $normalized) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $ids[] = $part;
            }
        }

        return $ids;
    }
}
