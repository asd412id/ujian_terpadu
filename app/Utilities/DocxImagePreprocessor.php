<?php

namespace App\Utilities;

use ZipArchive;

/**
 * Pre-process a DOCX file to handle unsupported image formats (EMF, WMF, TIFF).
 *
 * PHPWord throws InvalidImageException for EMF/WMF files because GD/getimagesize()
 * cannot read them. This preprocessor replaces unsupported images inside the DOCX zip
 * with a transparent PNG placeholder so the import can continue.
 *
 * Returns a list of replaced image paths for logging/warnings.
 */
class DocxImagePreprocessor
{
    /**
     * Image extensions that are NOT supported by PHP's getimagesize() / GD.
     */
    private const UNSUPPORTED_EXTENSIONS = ['emf', 'wmf', 'tiff', 'tif', 'svg'];

    /**
     * 1x1 transparent PNG placeholder (base64-decoded at runtime).
     */
    private const PLACEHOLDER_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private array $replacedImages = [];

    /**
     * Pre-process a DOCX file: replace unsupported images with PNG placeholders.
     *
     * Modifies the file in-place (caller should pass a temp copy).
     * Returns list of replaced image paths inside the DOCX.
     *
     * @return string[] List of replaced image paths (e.g. "word/media/image1.emf")
     */
    public function process(string $docxPath): array
    {
        $this->replacedImages = [];

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return [];
        }

        $placeholder = base64_decode(self::PLACEHOLDER_PNG_B64);
        $toReplace = [];

        // Scan for unsupported images in word/media/
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_starts_with($name, 'word/media/')) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, self::UNSUPPORTED_EXTENSIONS)) {
                $toReplace[] = $name;
            }
        }

        if (empty($toReplace)) {
            $zip->close();
            return [];
        }

        // Replace each unsupported image with a PNG placeholder
        foreach ($toReplace as $imagePath) {
            $newPath = preg_replace('/\.[^.]+$/', '.png', $imagePath);

            // Delete original
            $zip->deleteName($imagePath);

            // Add PNG placeholder with new name
            $zip->addFromString($newPath, $placeholder);

            $this->replacedImages[] = $imagePath;
        }

        // Update [Content_Types].xml to ensure .png is registered
        $this->updateContentTypes($zip, $toReplace);

        // Update word/_rels/document.xml.rels to point to new .png filenames
        $this->updateRelationships($zip, $toReplace);

        $zip->close();

        return $this->replacedImages;
    }

    /**
     * Update [Content_Types].xml: ensure .emf/.wmf extensions are replaced or .png is present.
     */
    private function updateContentTypes(ZipArchive $zip, array $replacedPaths): void
    {
        $xml = $zip->getFromName('[Content_Types].xml');
        if ($xml === false) return;

        $changed = false;

        // Ensure png Default extension exists
        if (!str_contains($xml, 'Extension="png"')) {
            $xml = str_replace(
                '<Types ',
                '<Types ',
                $xml
            );
            // Add png content type before closing </Types>
            $xml = str_replace(
                '</Types>',
                '<Default Extension="png" ContentType="image/png"/></Types>',
                $xml
            );
            $changed = true;
        }

        // Update Override entries for specific replaced images
        foreach ($replacedPaths as $oldPath) {
            $newPath = preg_replace('/\.[^.]+$/', '.png', $oldPath);
            $oldPartName = '/' . $oldPath;
            $newPartName = '/' . $newPath;

            if (str_contains($xml, $oldPartName)) {
                $xml = str_replace($oldPartName, $newPartName, $xml);
                // Also fix content type
                $xml = preg_replace(
                    '/PartName="' . preg_quote($newPartName, '/') . '"[^>]*ContentType="[^"]*"/',
                    'PartName="' . $newPartName . '" ContentType="image/png"',
                    $xml
                );
                $changed = true;
            }
        }

        if ($changed) {
            $zip->deleteName('[Content_Types].xml');
            $zip->addFromString('[Content_Types].xml', $xml);
        }
    }

    /**
     * Update relationship files to point .emf/.wmf targets to .png.
     */
    private function updateRelationships(ZipArchive $zip, array $replacedPaths): void
    {
        // Check all .rels files
        $relsFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.rels')) {
                $relsFiles[] = $name;
            }
        }

        foreach ($relsFiles as $relsFile) {
            $xml = $zip->getFromName($relsFile);
            if ($xml === false) continue;

            $changed = false;
            foreach ($replacedPaths as $oldPath) {
                // Relationship targets are relative: "media/image1.emf"
                $oldTarget = basename(dirname($oldPath)) . '/' . basename($oldPath);
                $newTarget = preg_replace('/\.[^.]+$/', '.png', $oldTarget);

                // Also try just the filename
                $oldBasename = basename($oldPath);
                $newBasename = preg_replace('/\.[^.]+$/', '.png', $oldBasename);

                if (str_contains($xml, $oldTarget)) {
                    $xml = str_replace($oldTarget, $newTarget, $xml);
                    $changed = true;
                } elseif (str_contains($xml, $oldBasename)) {
                    $xml = str_replace($oldBasename, $newBasename, $xml);
                    $changed = true;
                }
            }

            if ($changed) {
                $zip->deleteName($relsFile);
                $zip->addFromString($relsFile, $xml);
            }
        }
    }

    public function getReplacedImages(): array
    {
        return $this->replacedImages;
    }
}
