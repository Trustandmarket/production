<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AvatarManager
{
    private const MAX_FILE_SIZE = 1048576;
    private const TARGET_SIZE = 300;
    private const WEBP_QUALITY = 85;
    private const JPG_QUALITY = 85;

    private ServiceManager $serviceManager;
    private ParameterBagInterface $parameterBag;
    private string $local;

    public function __construct(ServiceManager $serviceManager, ParameterBagInterface $parameterBag)
    {
        $this->serviceManager = $serviceManager;
        $this->parameterBag = $parameterBag;
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }

    public function saveCroppedAvatar(int $userId, ?string $cropImage): ?string
    {
        if (!$cropImage) {
            return null;
        }

        $decodedImage = $this->decodeCropImage($cropImage);
        if ($decodedImage === null) {
            return null;
        }

        $sourceImage = imagecreatefromstring($decodedImage);
        if ($sourceImage === false) {
            return null;
        }

        $destinationDirectory = rtrim((string) $this->parameterBag->get('avatar_directory'), '/\\');

        if (!is_dir($destinationDirectory)) {
            mkdir($destinationDirectory, 0777, true);
        }

        [$fileName, $saved] = $this->saveResizedAvatar($userId, $sourceImage, $destinationDirectory);
        imagedestroy($sourceImage);

        if (!$saved || !$fileName) {
            return null;
        }

        $avatarUrl = $this->local . '/avatars/' . $fileName;
        $this->storeAvatarMeta($userId, $avatarUrl);

        return $avatarUrl;
    }

    public function saveUploadedAvatar(int $userId, ?UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        if ($uploadedFile->getSize() !== null && $uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            return null;
        }

        $sourceImage = $this->createImageFromUploadedFile($uploadedFile);
        if ($sourceImage === false) {
            return null;
        }

        $destinationDirectory = rtrim((string) $this->parameterBag->get('avatar_directory'), '/\\');

        if (!is_dir($destinationDirectory)) {
            mkdir($destinationDirectory, 0777, true);
        }

        [$fileName, $saved] = $this->saveResizedAvatar($userId, $sourceImage, $destinationDirectory);
        imagedestroy($sourceImage);

        if (!$saved || !$fileName) {
            return null;
        }

        $avatarUrl = $this->local . '/avatars/' . $fileName;
        $this->storeAvatarMeta($userId, $avatarUrl);

        return $avatarUrl;
    }

    private function decodeCropImage(string $cropImage): ?string
    {
        $parts = explode(',', $cropImage, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $header = $parts[0];
        if (
            strpos($header, 'data:image/jpeg;base64') !== 0 &&
            strpos($header, 'data:image/jpg;base64') !== 0 &&
            strpos($header, 'data:image/png;base64') !== 0 &&
            strpos($header, 'data:image/webp;base64') !== 0
        ) {
            return null;
        }

        $decodedImage = base64_decode($parts[1], true);

        return $decodedImage === false ? null : $decodedImage;
    }

    private function createDestinationCanvas(string $extension)
    {
        $image = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);

        if ($extension === 'webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $background = imagecolorallocatealpha($image, 255, 255, 255, 127);
        } else {
            $background = imagecolorallocate($image, 255, 255, 255);
        }

        imagefilledrectangle($image, 0, 0, self::TARGET_SIZE, self::TARGET_SIZE, $background);

        return $image;
    }

    private function createImageFromUploadedFile(UploadedFile $uploadedFile)
    {
        $mimeType = (string) $uploadedFile->getMimeType();
        $pathname = $uploadedFile->getPathname();

        if (in_array($mimeType, ['image/jpeg', 'image/jpg'], true)) {
            return imagecreatefromjpeg($pathname);
        }

        if ($mimeType === 'image/png') {
            return imagecreatefrompng($pathname);
        }

        if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            return imagecreatefromwebp($pathname);
        }

        return false;
    }

    private function saveResizedAvatar(int $userId, $sourceImage, string $destinationDirectory): array
    {
        if (function_exists('imagewebp')) {
            $webpFileName = sprintf('avatar_%d_%s.webp', $userId, uniqid('', true));
            $webpCanvas = $this->createDestinationCanvas('webp');
            $this->copyToCanvas($sourceImage, $webpCanvas);

            $saved = imagewebp(
                $webpCanvas,
                $destinationDirectory . DIRECTORY_SEPARATOR . $webpFileName,
                self::WEBP_QUALITY
            );
            imagedestroy($webpCanvas);

            if ($saved) {
                return [$webpFileName, true];
            }
        }

        $jpgFileName = sprintf('avatar_%d_%s.jpg', $userId, uniqid('', true));
        $jpgCanvas = $this->createDestinationCanvas('jpg');
        $this->copyToCanvas($sourceImage, $jpgCanvas);

        $saved = imagejpeg(
            $jpgCanvas,
            $destinationDirectory . DIRECTORY_SEPARATOR . $jpgFileName,
            self::JPG_QUALITY
        );
        imagedestroy($jpgCanvas);

        return [$jpgFileName, $saved];
    }

    private function copyToCanvas($sourceImage, $destinationImage): void
    {
        imagecopyresampled(
            $destinationImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            self::TARGET_SIZE,
            self::TARGET_SIZE,
            imagesx($sourceImage),
            imagesy($sourceImage)
        );
    }

    private function storeAvatarMeta(int $userId, string $avatarUrl): void
    {
        $avatarsMeta = $this->serviceManager->readUserMeta($userId, 'basic_user_avatar');
        $avatars = [];

        if ($avatarsMeta && $avatarsMeta->getMetaValue()) {
            $avatars = unserialize($avatarsMeta->getMetaValue(), ['allowed_classes' => false]);
            if (!is_array($avatars)) {
                $avatars = [];
            }
        }

        $avatars[] = $avatarUrl;
        $this->serviceManager->updateUserMeta($userId, 'basic_user_avatar', serialize($avatars));
    }
}
