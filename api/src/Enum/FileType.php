<?php

namespace App\Enum;

enum FileType: string
{
    case IMAGE = 'IMAGE';
    case VIDEO = 'VIDEO';
    case AUDIO = 'AUDIO';
    case DOCUMENT = 'DOCUMENT';
    case OTHER = 'OTHER';

    /**
     * @param MimeType $mimeType
     * @return FileType
     */
    public static function determineFileType(MimeType $mimeType): FileType
    {
        $value = $mimeType->value;

        return match(true) {
            str_starts_with($value, 'image/') => self::IMAGE,
            str_starts_with($value, 'video/') => self::VIDEO,
            str_starts_with($value, 'audio/') => self::AUDIO,
            preg_match('/(application\/pdf|word|excel|presentation)/', $value) => self::DOCUMENT,
            default => self::OTHER,
        };
    }
}