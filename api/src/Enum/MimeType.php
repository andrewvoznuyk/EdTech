<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum MimeType: string
{
    // Images
    case JPEG = 'image/jpeg';
    case PNG = 'image/png';
    case GIF = 'image/gif';
    case WEBP = 'image/webp';
    case SVG = 'image/svg+xml';
    case BMP = 'image/bmp';
    case TIFF = 'image/tiff';

    // Documents
    case PDF = 'application/pdf';
    case TEXT = 'text/plain';
    case CSV = 'text/csv';
    case JSON = 'application/json';
    case XML = 'application/xml';

    // Archives
    case ZIP = 'application/zip';
    case RAR = 'application/vnd.rar';
    case SEVEN_Z = 'application/x-7z-compressed';
    case TAR = 'application/x-tar';
    case GZIP = 'application/gzip';

    // Audio
    case MP3 = 'audio/mpeg';
    case WAV = 'audio/wav';
    case OGG = 'audio/ogg';
    case AAC = 'audio/aac';

    // Video
    case MP4 = 'video/mp4';
    case WEBM = 'video/webm';
    case AVI = 'video/x-msvideo';
    case MOV = 'video/quicktime';

    /**
     * @return MimeType[]
     */
    public static function images(): array
    {
        return [
            self::JPEG,
            self::PNG,
            self::GIF,
            self::WEBP,
            self::SVG,
            self::BMP,
            self::TIFF,
        ];
    }

    /**
     * @return MimeType[]
     */
    public static function documents(): array
    {
        return [
            self::PDF,
            self::TEXT,
            self::CSV,
            self::JSON,
            self::XML,
        ];
    }
}
