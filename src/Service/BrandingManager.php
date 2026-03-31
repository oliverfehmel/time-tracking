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

        if ($oldFilename) {
            $oldPath = $brandingDir . '/' . $oldFilename;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = (string) $this->slugger->slug($originalName);

        // MIME und Extension VOR dem move() ermitteln
        $mimeType = $file->getMimeType();
        $extension = $file->guessExtension() ?: 'bin';

        $filename = sprintf(
            'logo-%s-%s.%s',
            strtolower($safeName),
            uniqid(),
            $extension
        );

        $file->move($brandingDir, $filename);

        $sourcePath = $brandingDir . '/' . $filename;

        // Falls du ganz sicher den MIME-Type anhand der verschobenen Datei brauchst:
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
}
