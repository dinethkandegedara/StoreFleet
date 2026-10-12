<?php

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    /**
     * Allowed MIME types for uploaded product images.
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Target thumbnail dimensions in pixels.
     */
    public const TARGET_SIZE = 256;

    /**
     * Maximum allowed upload size in bytes (5 MB).
     */
    protected const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Maximum image dimensions (width or height) in pixels.
     */
    protected const MAX_DIMENSION = 5000;

    /**
     * Minimum image dimensions (width or height) in pixels.
     */
    protected const MIN_DIMENSION = 20;

    /**
     * Process an uploaded image: validate, auto-orient, square-crop, resize to 256x256,
     * compress to WebP (or fallback format), strip metadata, store safely, and delete replaced image.
     *
     * @param  UploadedFile  $file  Uploaded file instance.
     * @param  string  $productName  Product name for slug generation.
     * @param  string|null  $oldImagePath  Previous image path to remove if replacing.
     * @return string Stored relative image path (e.g. "products/name-abc123.webp").
     *
     * @throws ImageProcessingException
     */
    public function processAndStore(UploadedFile $file, string $productName, ?string $oldImagePath = null): string
    {
        // 1. Validate file health and raw characteristics
        $this->validateUpload($file);

        $tempOutput = null;

        try {
            // 2. Select target extension based on environment WebP capability
            $extension = $this->supportsWebp() ? 'webp' : 'jpg';

            // 3. Generate secure safe filename
            $sanitizedSlug = Str::slug($productName);
            if (empty($sanitizedSlug)) {
                $sanitizedSlug = 'product';
            }
            $slugPrefix = substr($sanitizedSlug, 0, 40);
            $uniqueId = bin2hex(random_bytes(6));
            $targetRelativePath = "products/{$slugPrefix}-{$uniqueId}.{$extension}";

            // 4. Create temporary destination file
            $tempOutput = tempnam(sys_get_temp_dir(), 'storefleet_img_').'.'.$extension;

            // 5. Execute transformation pipeline using best available driver
            $inputRealPath = $file->getRealPath();
            $this->transformImage($inputRealPath, $tempOutput, $extension);

            if (! file_exists($tempOutput) || filesize($tempOutput) === 0) {
                throw new ImageProcessingException('Processed image output is empty or failed to generate.');
            }

            // 6. Verify processed output dimensions and valid image structure
            $processedInfo = @getimagesize($tempOutput);
            if (! $processedInfo || $processedInfo[0] !== self::TARGET_SIZE || $processedInfo[1] !== self::TARGET_SIZE) {
                throw new ImageProcessingException('Processed image does not match the configured 256x256 dimensions.');
            }

            // 7. Store processed output to public storage disk
            $stream = fopen($tempOutput, 'r');
            Storage::disk('public')->put($targetRelativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // 8. Safely cleanup replaced image file if provided
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }

            return $targetRelativePath;
        } finally {
            // 9. Reliably cleanup temporary processing files
            if ($tempOutput && file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }
    }

    /**
     * Delete an existing product image safely without path traversal.
     */
    public function deleteImage(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Prevent path traversal and unauthorized directory deletion
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '..') || ! str_starts_with($normalized, 'products/')) {
            return false;
        }

        if (Storage::disk('public')->exists($normalized)) {
            return Storage::disk('public')->delete($normalized);
        }

        return false;
    }

    /**
     * Validate upload integrity, true MIME type, file size, and pixel dimensions.
     *
     * @throws ImageProcessingException
     */
    protected function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new ImageProcessingException('Uploaded file upload failed or was corrupted.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new ImageProcessingException('File size exceeds maximum allowed limit of 5MB.');
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new ImageProcessingException('Cannot read uploaded file from server temporary path.');
        }

        // Server-side real MIME detection via finfo (never trust client MIME)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $realPath);
        finfo_close($finfo);

        if (! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new ImageProcessingException('Invalid image format. Supported formats: JPEG, PNG, and WebP.');
        }

        // Verify valid image header and dimensions via getimagesize
        $dimensions = @getimagesize($realPath);
        if (! $dimensions || ! isset($dimensions[0], $dimensions[1])) {
            throw new ImageProcessingException('The uploaded file does not contain valid image bitmap data.');
        }

        [$width, $height] = $dimensions;

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw new ImageProcessingException('Image dimensions too small. Minimum required: '.self::MIN_DIMENSION.'x'.self::MIN_DIMENSION.' pixels.');
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new ImageProcessingException('Image dimensions too large. Maximum allowed: '.self::MAX_DIMENSION.'x'.self::MAX_DIMENSION.' pixels.');
        }
    }

    /**
     * Check if WebP output format is supported by the server environment.
     */
    public function supportsWebp(): bool
    {
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            return true;
        }

        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick;
                $formats = $im->queryFormats('WEBP');

                return ! empty($formats);
            } catch (\Throwable) {
                // fall through
            }
        }

        // Check if ImageMagick CLI is executable
        $binary = $this->getImageMagickBinary();
        if ($binary) {
            return true;
        }

        return false;
    }

    /**
     * Transform image (orientation, square crop, resize to 256x256, metadata strip).
     *
     * @throws ImageProcessingException
     */
    protected function transformImage(string $inputPath, string $outputPath, string $targetFormat): void
    {
        if (extension_loaded('gd')) {
            $this->transformWithGd($inputPath, $outputPath, $targetFormat);

            return;
        }

        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $this->transformWithImagickExtension($inputPath, $outputPath, $targetFormat);

            return;
        }

        $binary = $this->getImageMagickBinary();
        if ($binary) {
            $this->transformWithMagickBinary($binary, $inputPath, $outputPath, $targetFormat);

            return;
        }

        throw new ImageProcessingException('No supported image processing extension or binary found (GD, Imagick, or ImageMagick CLI is required).');
    }

    /**
     * Transform using PHP GD extension (standard on shared hosting).
     *
     * @throws ImageProcessingException
     */
    protected function transformWithGd(string $inputPath, string $outputPath, string $targetFormat): void
    {
        $rawContent = file_get_contents($inputPath);
        if ($rawContent === false) {
            throw new ImageProcessingException('Failed to read image data.');
        }

        $sourceImage = @imagecreatefromstring($rawContent);
        if (! $sourceImage) {
            throw new ImageProcessingException('GD failed to decode image data.');
        }

        // Auto-orient based on EXIF if available (JPEG/TIFF)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($inputPath);
            if (! empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $rotated = imagerotate($sourceImage, 180, 0);
                        if ($rotated) {
                            imagedestroy($sourceImage);
                            $sourceImage = $rotated;
                        }
                        break;
                    case 6:
                        $rotated = imagerotate($sourceImage, -90, 0);
                        if ($rotated) {
                            imagedestroy($sourceImage);
                            $sourceImage = $rotated;
                        }
                        break;
                    case 8:
                        $rotated = imagerotate($sourceImage, 90, 0);
                        if ($rotated) {
                            imagedestroy($sourceImage);
                            $sourceImage = $rotated;
                        }
                        break;
                }
            }
        }

        $origW = imagesx($sourceImage);
        $origH = imagesy($sourceImage);

        // Center square crop coordinates
        $cropSize = min($origW, $origH);
        $cropX = (int) (($origW - $cropSize) / 2);
        $cropY = (int) (($origH - $cropSize) / 2);

        $target = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);
        if (! $target) {
            imagedestroy($sourceImage);
            throw new ImageProcessingException('GD failed to allocate canvas memory.');
        }

        // Preserve alpha transparency for WebP/PNG
        imagealphablending($target, false);
        imagesavealpha($target, true);

        // High quality resample
        imagecopyresampled(
            $target,
            $sourceImage,
            0,
            0,
            $cropX,
            $cropY,
            self::TARGET_SIZE,
            self::TARGET_SIZE,
            $cropSize,
            $cropSize
        );

        // Save output and strip metadata (GD creates clean images with zero EXIF)
        $saved = false;
        if ($targetFormat === 'webp' && function_exists('imagewebp')) {
            $saved = imagewebp($target, $outputPath, 85);
        } else {
            $saved = imagejpeg($target, $outputPath, 85);
        }

        imagedestroy($sourceImage);
        imagedestroy($target);

        if (! $saved) {
            throw new ImageProcessingException('GD failed to encode and save transformed image.');
        }
    }

    /**
     * Transform using PHP Imagick extension.
     *
     * @throws ImageProcessingException
     */
    protected function transformWithImagickExtension(string $inputPath, string $outputPath, string $targetFormat): void
    {
        try {
            $imagick = new \Imagick($inputPath);
            $imagick->autoOrient();
            $imagick->cropThumbnailImage(self::TARGET_SIZE, self::TARGET_SIZE);
            $imagick->stripImage(); // Remove EXIF and metadata
            $imagick->setImageFormat($targetFormat);
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
        } catch (\Throwable $e) {
            throw new ImageProcessingException('Imagick failed to process image: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Transform using ImageMagick CLI binary (`magick` or `convert`).
     *
     * @throws ImageProcessingException
     */
    protected function transformWithMagickBinary(string $binary, string $inputPath, string $outputPath, string $targetFormat): void
    {
        $escapedBinary = escapeshellcmd($binary);
        $escapedInput = escapeshellarg($inputPath);
        $escapedOutput = escapeshellarg($outputPath);

        // Auto-orient, center square crop, resize to 256x256, strip metadata, quality 85
        $command = "{$escapedBinary} {$escapedInput} -auto-orient -gravity center -crop 1:1 +repage -resize ".self::TARGET_SIZE.'x'.self::TARGET_SIZE." -strip -quality 85 {$escapedOutput} 2>&1";

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            $errorDetail = implode("\n", $output);
            throw new ImageProcessingException("ImageMagick binary transformation failed (exit code {$exitCode}): {$errorDetail}");
        }
    }

    /**
     * Detect installed ImageMagick CLI binary.
     */
    protected function getImageMagickBinary(): ?string
    {
        $candidates = ['/usr/bin/magick', 'magick', '/usr/bin/convert', 'convert'];

        foreach ($candidates as $candidate) {
            $test = escapeshellcmd($candidate);
            $output = [];
            $code = 1;
            exec("{$test} -version 2>/dev/null", $output, $code);
            if ($code === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
