<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageUploadHelper
{
    /**
     * Common alias lists for standard driver and application documents.
     */
    public const LICENSE_FRONT_ALIASES = [
        'license_copy_front',
        'license_copy_front_file',
        'license_front',
        'license_front_image',
        'license_front_file',
        'license_front_doc',
        'license_front_copy',
        'license_front_photo',
        'license_photo_front',
        'license_image_front',
        'driving_license_front',
        'driver_license_front',
        'driving_licence_front',
        'licence_front',
        'licence_copy_front',
        'licenseFront',
        'licenseFrontImage',
        'licenseCopyFront',
        'front_license',
        'front_license_copy',
        'front_license_image',
        'front_image',
        'front_doc',
        'license_doc_front',
        'license_front_picture',
        'driving_license_copy_front',
        'driving_licence_copy_front',
        'license_copy_front_path',
        'license_copy',
        'license_image',
        'driving_license',
        'documents.license_front',
        'documents.license_copy_front',
        'license.front',
        'license.license_copy_front',
    ];

    public const LICENSE_BACK_ALIASES = [
        'license_copy_back',
        'license_copy_back_file',
        'license_back',
        'license_back_image',
        'license_back_file',
        'license_back_doc',
        'license_back_copy',
        'license_back_photo',
        'license_photo_back',
        'license_image_back',
        'driving_license_back',
        'driver_license_back',
        'driving_licence_back',
        'licence_back',
        'licence_copy_back',
        'licenseBack',
        'licenseBackImage',
        'licenseCopyBack',
        'back_license',
        'back_license_copy',
        'back_license_image',
        'back_image',
        'back_doc',
        'license_doc_back',
        'license_back_picture',
        'driving_license_copy_back',
        'driving_licence_copy_back',
        'license_copy_back_path',
        'documents.license_back',
        'documents.license_copy_back',
        'license.back',
        'license.license_copy_back',
    ];

    public const VEHICLE_REG_ALIASES = [
        'vehicle_reg_image',
        'vehicle_reg_image_file',
        'vehicle_registration_image',
        'vehicle_reg_doc',
        'vehicle_doc',
        'vehicle_image',
        'vehicle_image_file',
        'vehicle_file',
        'rego_image',
        'rego_doc',
        'vehicle_reg_copy',
        'vehicle_registration_copy',
        'vehicle_reg_photo',
        'vehicle_rego_doc',
        'vehicle_rego_image',
        'vehicle_rego',
        'vehicle_registration',
        'vehicleRegImage',
        'vehicleRegistrationImage',
        'vehicleRegDoc',
        'regoDoc',
        'documents.vehicle_reg_image',
        'documents.vehicle_reg_doc',
        'vehicle.reg_image',
        'vehicle.image',
    ];

    public const PROFILE_IMAGE_ALIASES = [
        'profile_image',
        'profile_image_file',
        'profile_photo',
        'avatar',
        'photo',
        'image',
        'profileImage',
        'driver_image',
        'driver_photo',
        'file',
        'customer_image',
        'user_image',
    ];

    /**
     * Universal file upload, Base64 parser, and path resolver.
     *
     * @param Request|array $input
     * @param array|string $fieldNames
     * @param string $prefix
     * @param string $uploadFolder
     * @param string|null $existingPath
     * @return string|null
     */
    public static function save($input, $fieldNames, string $prefix, string $uploadFolder = 'uploads', ?string $existingPath = null): ?string
    {
        if (!is_array($fieldNames)) {
            $fieldNames = [$fieldNames];
        }

        $uploadsDir = public_path($uploadFolder);
        if (!File::exists($uploadsDir)) {
            File::makeDirectory($uploadsDir, 0777, true, true);
        }

        // 1. Check for Direct File Upload (multipart/form-data)
        $file = self::extractUploadedFile($input, $fieldNames);
        if ($file instanceof UploadedFile && $file->isValid()) {
            if ($existingPath && File::exists(public_path($existingPath))) {
                @File::delete(public_path($existingPath));
            }
            $ext = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'jpg');
            $fileName = $prefix . '_' . time() . '_' . Str::random(8) . '.' . strtolower($ext);
            $file->move($uploadsDir, $fileName);
            return $uploadFolder . '/' . $fileName;
        }

        // 2. Check for Array-based files (e.g. license_copy[0], license_images[0])
        if ($input instanceof Request) {
            $allFiles = $input->allFiles();
            foreach ($fieldNames as $fn) {
                if (str_contains($fn, '[')) {
                    $parts = explode('[', str_replace(']', '', $fn));
                    $baseKey = $parts[0];
                    $subIdx = $parts[1] ?? 0;
                    if (isset($allFiles[$baseKey]) && is_array($allFiles[$baseKey]) && isset($allFiles[$baseKey][$subIdx])) {
                        $f = $allFiles[$baseKey][$subIdx];
                        if ($f instanceof UploadedFile && $f->isValid()) {
                            if ($existingPath && File::exists(public_path($existingPath))) {
                                @File::delete(public_path($existingPath));
                            }
                            $ext = $f->getClientOriginalExtension() ?: ($f->guessExtension() ?: 'jpg');
                            $fileName = $prefix . '_' . time() . '_' . Str::random(8) . '.' . strtolower($ext);
                            $f->move($uploadsDir, $fileName);
                            return $uploadFolder . '/' . $fileName;
                        }
                    }
                }
            }
        }

        // 3. Check for Base64 Strings, JSON strings, or Existing Path References
        foreach ($fieldNames as $fn) {
            $rawVal = self::extractStringValue($input, $fn);
            if ($rawVal === null) {
                continue;
            }

            $str = trim((string)$rawVal);
            if ($str === '' || $str === 'null' || $str === 'undefined') {
                continue;
            }

            // A. Data URI scheme: data:image/png;base64,... or data:application/pdf;base64,...
            if (str_contains($str, ';base64,')) {
                $saved = self::saveDataUriBase64($str, $prefix, $uploadFolder, $existingPath);
                if ($saved) {
                    return $saved;
                }
            }

            // B. Existing relative/absolute path or URL pointing to public uploads
            if (str_contains($str, 'uploads/') || str_contains($str, 'uploads\\')) {
                return self::normalizeExistingUploadPath($str);
            }

            // C. Raw Base64 string without data URI scheme (e.g. /9j/4AAQ... for JPEG, iVBORw... for PNG)
            if (strlen($str) > 50 && !str_starts_with($str, 'http://') && !str_starts_with($str, 'https://')) {
                $saved = self::saveRawBase64($str, $prefix, $uploadFolder, $existingPath);
                if ($saved) {
                    return $saved;
                }
            }
        }

        return $existingPath;
    }

    /**
     * Extract UploadedFile instance from Request or array.
     */
    protected static function extractUploadedFile($input, array $fieldNames): ?UploadedFile
    {
        if ($input instanceof Request) {
            foreach ($fieldNames as $fn) {
                if ($input->hasFile($fn)) {
                    $file = $input->file($fn);
                    if (is_array($file)) {
                        foreach ($file as $f) {
                            if ($f instanceof UploadedFile && $f->isValid()) {
                                return $f;
                            }
                        }
                    } elseif ($file instanceof UploadedFile && $file->isValid()) {
                        return $file;
                    }
                }
            }
        } elseif (is_array($input)) {
            foreach ($fieldNames as $fn) {
                if (isset($input[$fn])) {
                    $file = $input[$fn];
                    if (is_array($file)) {
                        foreach ($file as $f) {
                            if ($f instanceof UploadedFile && $f->isValid()) {
                                return $f;
                            }
                        }
                    } elseif ($file instanceof UploadedFile && $file->isValid()) {
                        return $file;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract string value for a key from Request or array.
     */
    protected static function extractStringValue($input, string $key): ?string
    {
        if ($input instanceof Request) {
            if ($input->has($key)) {
                $v = $input->input($key);
                if (is_string($v)) return $v;
                if (is_array($v) && isset($v[0]) && is_string($v[0])) return $v[0];
            }
            // Check nested dot notation
            $v = data_get($input->all(), $key);
            if (is_string($v)) return $v;
            if (is_array($v) && isset($v[0]) && is_string($v[0])) return $v[0];
        } elseif (is_array($input)) {
            $v = data_get($input, $key);
            if (is_string($v)) return $v;
            if (is_array($v) && isset($v[0]) && is_string($v[0])) return $v[0];
        } elseif (is_object($input)) {
            if (isset($input->{$key}) && is_string($input->{$key})) {
                return $input->{$key};
            }
            $v = data_get($input, $key);
            if (is_string($v)) return $v;
        }

        return null;
    }

    /**
     * Save Data URI Base64 string to file.
     */
    protected static function saveDataUriBase64(string $str, string $prefix, string $uploadFolder, ?string $existingPath = null): ?string
    {
        $parts = explode(';base64,', $str);
        if (count($parts) < 2) {
            return null;
        }

        $header = strtolower($parts[0]);
        $base64Data = str_replace(' ', '+', trim($parts[1]));

        $ext = 'jpg';
        if (str_contains($header, 'png')) $ext = 'png';
        elseif (str_contains($header, 'webp')) $ext = 'webp';
        elseif (str_contains($header, 'gif')) $ext = 'gif';
        elseif (str_contains($header, 'pdf')) $ext = 'pdf';
        elseif (str_contains($header, 'jpeg')) $ext = 'jpg';

        $decoded = base64_decode($base64Data);
        if ($decoded === false || strlen($decoded) < 10) {
            return null;
        }

        return self::writeDecodedBinary($decoded, $ext, $prefix, $uploadFolder, $existingPath);
    }

    /**
     * Save raw Base64 string to file.
     */
    protected static function saveRawBase64(string $str, string $prefix, string $uploadFolder, ?string $existingPath = null): ?string
    {
        $sanitized = str_replace(' ', '+', $str);
        $decoded = base64_decode($sanitized, true);

        if ($decoded === false || strlen($decoded) < 20) {
            return null;
        }

        // Detect image extension from magic bytes
        $ext = self::detectExtensionFromBinary($decoded);

        return self::writeDecodedBinary($decoded, $ext, $prefix, $uploadFolder, $existingPath);
    }

    /**
     * Detect file extension from binary magic bytes.
     */
    public static function detectExtensionFromBinary(string $binary): string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
            return 'gif';
        }
        if (str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 8, 8), 'WEBP')) {
            return 'webp';
        }
        if (str_starts_with($binary, '%PDF')) {
            return 'pdf';
        }
        if (str_starts_with($binary, 'BM')) {
            return 'bmp';
        }

        return 'jpg';
    }

    /**
     * Write binary data to disk in public upload directory.
     */
    protected static function writeDecodedBinary(string $decoded, string $ext, string $prefix, string $uploadFolder, ?string $existingPath = null): string
    {
        if ($existingPath && File::exists(public_path($existingPath))) {
            @File::delete(public_path($existingPath));
        }

        $uploadsDir = public_path($uploadFolder);
        if (!File::exists($uploadsDir)) {
            File::makeDirectory($uploadsDir, 0777, true, true);
        }

        $fileName = $prefix . '_' . time() . '_' . Str::random(8) . '.' . strtolower($ext);
        File::put($uploadsDir . '/' . $fileName, $decoded);

        return $uploadFolder . '/' . $fileName;
    }

    /**
     * Normalize upload path string (strips domain, leading slashes, public/ prefixes).
     */
    public static function normalizeExistingUploadPath(string $path): string
    {
        $clean = str_replace('\\', '/', trim($path));
        if (str_contains($clean, 'uploads/')) {
            $parts = explode('uploads/', $clean);
            return 'uploads/' . end($parts);
        }
        return ltrim($clean, '/');
    }

    /**
     * Helper for Driver License Front
     */
    public static function saveLicenseFront($input, string $prefix = 'license_front', ?string $existingPath = null): ?string
    {
        return self::save($input, self::LICENSE_FRONT_ALIASES, $prefix, 'uploads/licenses', $existingPath);
    }

    /**
     * Helper for Driver License Back
     */
    public static function saveLicenseBack($input, string $prefix = 'license_back', ?string $existingPath = null): ?string
    {
        return self::save($input, self::LICENSE_BACK_ALIASES, $prefix, 'uploads/licenses', $existingPath);
    }

    /**
     * Helper for Driver Vehicle Registration Document
     */
    public static function saveVehicleReg($input, string $prefix = 'vehicle_reg', ?string $existingPath = null): ?string
    {
        return self::save($input, self::VEHICLE_REG_ALIASES, $prefix, 'uploads/vehicles', $existingPath);
    }

    /**
     * Helper for Profile Image
     */
    public static function saveProfileImage($input, string $prefix = 'profile', ?string $existingPath = null): ?string
    {
        return self::save($input, self::PROFILE_IMAGE_ALIASES, $prefix, 'uploads/profiles', $existingPath);
    }
}
