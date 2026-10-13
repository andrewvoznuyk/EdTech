<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum FileType: string
{
    use EnumValuesTrait;

    case IMAGE = 'IMAGE';
    case VIDEO = 'VIDEO';
    case AUDIO = 'AUDIO';
    case DOCUMENT = 'DOCUMENT';
    case OTHER = 'OTHER';

    /**
     * @param MimeType|null $mimeType
     * @return FileType
     */
    public static function determineFileType(?MimeType $mimeType): FileType
    {
        if (null === $mimeType) {
            return self::OTHER;
        }

        $value = $mimeType->value;

        return match(true) {
            str_starts_with($value, 'image/') => self::IMAGE,
            str_starts_with($value, 'video/') => self::VIDEO,
            str_starts_with($value, 'audio/') => self::AUDIO,
            1 === preg_match('/(application\/pdf|word|excel|presentation)/', $value) => self::DOCUMENT,
            default => self::OTHER,
        };
    }
}
