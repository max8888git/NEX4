<?php

namespace App\Support;

/**
 * Profile photos, one file per user under storage/app/private/avatars.
 *
 * The browser crops and shrinks photos to 256x256 before uploading, so the
 * server only checks that what arrives really is a small JPEG, PNG or WebP
 * image (no image library needed) and stores it as-is.
 */
class ProfilePhoto
{
    public const MAX_BYTES = 512 * 1024;

    public const MAX_SIDE = 1024;

    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MIME = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private static function dir(): string
    {
        return storage_path('app/private/avatars');
    }

    /** Absolute path of the user's photo, or null when they have none. */
    public static function path(int $userId): ?string
    {
        foreach (self::MIME as $ext => $mime) {
            $file = self::dir().DIRECTORY_SEPARATOR.$userId.'.'.$ext;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    public static function mime(string $path): string
    {
        return self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /** URL for the photo (changes when the photo does, so browsers refetch), or null. */
    public static function url(int $userId): ?string
    {
        $path = self::path($userId);

        return $path ? url('api/avatar/u-'.$userId).'?v='.filemtime($path) : null;
    }

    /**
     * Store a photo sent as a data URL ("data:image/jpeg;base64,...").
     *
     * @throws \InvalidArgumentException with a message for the user
     */
    public static function store(int $userId, string $dataUrl): void
    {
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $m)) {
            throw new \InvalidArgumentException('Upload a JPG, PNG, or WebP photo.');
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException('That photo could not be read. Try another one.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('That photo is too large. Try a smaller one.');
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! isset(self::TYPES[$info[2]])) {
            throw new \InvalidArgumentException('That file is not a JPG, PNG, or WebP photo.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            throw new \InvalidArgumentException('That photo is too large. Try a smaller one.');
        }

        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        self::delete($userId);
        $file = self::dir().DIRECTORY_SEPARATOR.$userId.'.'.self::TYPES[$info[2]];
        if (file_put_contents($file, $bytes, LOCK_EX) === false) {
            throw new \InvalidArgumentException('The photo could not be saved. Try again.');
        }
    }

    public static function delete(int $userId): void
    {
        foreach (array_keys(self::MIME) as $ext) {
            $file = self::dir().DIRECTORY_SEPARATOR.$userId.'.'.$ext;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
