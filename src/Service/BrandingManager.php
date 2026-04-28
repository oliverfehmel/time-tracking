<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\String\Slugger\SluggerInterface;

class BrandingManager
{
    public function __construct(
        private readonly string $projectDir,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function handleLogoUpload(UploadedFile $file, ?string $oldFilename = null): array
    {
        $filesystem = new Filesystem();

        $brandingDir = $this->projectDir . '/public/uploads/branding';
        $faviconDir = $this->projectDir . '/public/favicon';

        $filesystem->mkdir($brandingDir);
        $filesystem->mkdir($faviconDir);

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = (string) $this->slugger->slug($originalName);

        $mimeType = $file->getMimeType();
        $extension = $file->guessExtension() ?: 'bin';
        if ($mimeType === 'image/svg+xml') {
            $extension = 'svg';
        }

        $filename = sprintf(
            'logo-%s-%s.%s',
            strtolower($safeName),
            uniqid(),
            $extension
        );

        $sourcePath = $brandingDir . '/' . $filename;
        if ($mimeType === 'image/svg+xml') {
            $this->storeSanitizedSvg($file, $sourcePath);
            $filesystem->copy($sourcePath, $faviconDir . '/favicon.svg', true);
            $this->writeManifest($faviconDir);
            $this->deleteOldLogo($brandingDir, $oldFilename);

            return [
                'logoFilename' => $filename,
                'faviconVersion' => (string) time(),
            ];
        }

        $file->move($brandingDir, $filename);

        if (!$mimeType && is_file($sourcePath)) {
            $mimeType = (new MimeTypes())->guessMimeType($sourcePath);
        }

        $sourceImage = $this->createImageResource($sourcePath, $mimeType);

        if (!$sourceImage) {
            throw new \RuntimeException(sprintf(
                'Das Logo konnte nicht verarbeitet werden. MIME-Type: %s',
                $mimeType ?? 'unbekannt'
            ));
        }

        $sizes = [
            'favicon-16x16.png' => 16,
            'favicon-32x32.png' => 32,
            'apple-touch-icon.png' => 180,
            'android-chrome-192x192.png' => 192,
            'android-chrome-512x512.png' => 512,
        ];

        foreach ($sizes as $targetFilename => $size) {
            $this->resizeToPng(
                $sourceImage,
                $faviconDir . '/' . $targetFilename,
                $size,
                $size
            );
        }

        imagedestroy($sourceImage);

        $this->writeManifest($faviconDir);
        $this->deleteOldLogo($brandingDir, $oldFilename);

        return [
            'logoFilename' => $filename,
            'faviconVersion' => (string) time(),
        ];
    }

    private function createImageResource(string $path, ?string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/png' => imagecreatefrompng($path),
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/webp' => imagecreatefromwebp($path),
            default => null,
        };
    }

    private function resizeToPng(mixed $sourceImage, string $targetPath, int $targetWidth, int $targetHeight): void
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);

        $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $newWidth = (int) round($sourceWidth * $ratio);
        $newHeight = (int) round($sourceHeight * $ratio);

        $dstX = (int) round(($targetWidth - $newWidth) / 2);
        $dstY = (int) round(($targetHeight - $newHeight) / 2);

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            $dstX,
            $dstY,
            0,
            0,
            $newWidth,
            $newHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagepng($targetImage, $targetPath);
        imagedestroy($targetImage);
    }

    private function writeManifest(string $faviconDir): void
    {
        $manifest = [
            'name' => 'Time Tracking',
            'short_name' => 'Time',
            'icons' => [
                [
                    'src' => '/favicon/android-chrome-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/favicon/android-chrome-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
            'theme_color' => '#ffffff',
            'background_color' => '#ffffff',
            'display' => 'standalone',
        ];

        file_put_contents(
            $faviconDir . '/site.webmanifest',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function storeSanitizedSvg(UploadedFile $file, string $targetPath): void
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            throw new \RuntimeException('Das SVG konnte nicht gelesen werden.');
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (!$loaded || $dom->documentElement?->tagName !== 'svg') {
            throw new \RuntimeException('Bitte lade eine gueltige SVG-Datei hoch.');
        }

        foreach (['script', 'foreignObject', 'iframe', 'object', 'embed'] as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node?->parentNode?->removeChild($node);
            }
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $element->attributes->item($i);
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                $name = strtolower($attribute->name);
                $value = strtolower(trim($attribute->value));
                if (str_starts_with($name, 'on') || str_starts_with($value, 'javascript:')) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        if (file_put_contents($targetPath, $dom->saveXML()) === false) {
            throw new \RuntimeException('Das SVG konnte nicht gespeichert werden.');
        }
    }

    private function deleteOldLogo(string $brandingDir, ?string $oldFilename): void
    {
        if (!$oldFilename) {
            return;
        }

        $oldPath = $brandingDir . '/' . $oldFilename;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
}
