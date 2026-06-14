<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationCheckResult;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;

class AnnouncementModerationPrecheckService
{
    private const RESULT_PASS = 'pass';
    private const RESULT_FAIL = 'fail';
    private const SOURCE_TYPE = 'rules';

    /**
     * @return array{passed: bool, checks: array<int, AnnouncementModerationCheckResult>}
     */
    public function evaluate(AnnouncementModerationContext $context): array
    {
        $checks = [];

        $price = $this->normalizePrice($context->getPrice());
        $checks[] = $this->buildCheck(
            'price_min',
            'Prix minimum',
            $price >= $this->getMinPrice(),
            sprintf('Le prix doit etre superieur ou egal a %s.', $this->formatNumber($this->getMinPrice())),
            $context->getPrice()
        );

        $title = $context->getTitle();
        $checks[] = $this->buildCheck(
            'has_title',
            'Titre renseigne',
            $this->hasMeaningfulText($title),
            'Le titre est obligatoire.',
            $title
        );
        $checks[] = $this->buildCheck(
            'title_min_length',
            'Longueur minimale du titre',
            $this->textLength($title) >= $this->getTitleMinLength(),
            sprintf('Le titre doit contenir au moins %d caracteres.', $this->getTitleMinLength()),
            $title
        );

        $description = $context->getDescription();
        $checks[] = $this->buildCheck(
            'has_description',
            'Description renseignee',
            $this->hasMeaningfulText($description),
            'La description est obligatoire.',
            $description
        );
        $checks[] = $this->buildCheck(
            'description_min_length',
            'Longueur minimale de la description',
            $this->textLength($description) >= $this->getDescriptionMinLength(),
            sprintf('La description doit contenir au moins %d caracteres.', $this->getDescriptionMinLength()),
            $description
        );

        $checks[] = $this->buildCheck(
            'has_main_photo',
            'Presence de photo',
            $context->hasMainPhoto(),
            'Au moins une photo est requise.',
            $context->getGalleryIds()
        );

        $passed = true;
        foreach ($checks as $check) {
            if (!$check->isPass()) {
                $passed = false;
                break;
            }
        }

        return [
            'passed' => $passed,
            'checks' => $checks,
        ];
    }

    /**
     * @param mixed $rawValue
     */
    private function buildCheck(
        string $code,
        string $label,
        bool $isPass,
        string $failureReason,
        $rawValue
    ): AnnouncementModerationCheckResult {
        return new AnnouncementModerationCheckResult(
            $code,
            $label,
            self::SOURCE_TYPE,
            $isPass ? self::RESULT_PASS : self::RESULT_FAIL,
            null,
            $isPass ? 'OK' : $failureReason,
            $rawValue
        );
    }

    private function hasMeaningfulText(string $value): bool
    {
        $normalized = trim($value);

        return $normalized !== '' && $normalized !== '--';
    }

    private function normalizePrice(string $rawPrice): float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($rawPrice));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function getMinPrice(): float
    {
        return $this->readFloatEnv('ANNOUNCEMENT_AI_MIN_PRICE_DEFAULT', 50.0);
    }

    private function getTitleMinLength(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_TITLE_MIN_LENGTH', 30);
    }

    private function getDescriptionMinLength(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_DESCRIPTION_MIN_LENGTH', 300);
    }

    private function readIntEnv(string $name, int $default): int
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }

    private function readFloatEnv(string $name, float $default): float
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return is_numeric((string) $value) ? (float) $value : $default;
    }

    private function formatNumber(float $value): string
    {
        if ((int) $value === $value) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value);
        }

        return strlen($value);
    }
}
