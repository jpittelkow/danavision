<?php

/**
 * Extension-to-MIME-type mapping for file upload validation.
 * Used by FileManagerController to prevent extension spoofing attacks.
 */
return [
    // Documents
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xls' => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ppt' => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'odt' => ['application/vnd.oasis.opendocument.text'],
    'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    'odp' => ['application/vnd.oasis.opendocument.presentation'],
    'rtf' => ['application/rtf', 'text/rtf'],
    'txt' => ['text/plain'],
    'csv' => ['text/csv', 'text/plain', 'application/csv'],
    // Images
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'bmp' => ['image/bmp', 'image/x-bmp'],
    'webp' => ['image/webp'],
    'svg' => ['image/svg+xml'],
    'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
    'tiff' => ['image/tiff'],
    'tif' => ['image/tiff'],
    // Audio
    'mp3' => ['audio/mpeg', 'audio/mp3'],
    'wav' => ['audio/wav', 'audio/x-wav'],
    'ogg' => ['audio/ogg', 'application/ogg'],
    'flac' => ['audio/flac', 'audio/x-flac'],
    'aac' => ['audio/aac', 'audio/x-aac'],
    'm4a' => ['audio/mp4', 'audio/x-m4a'],
    // Video
    'mp4' => ['video/mp4'],
    'webm' => ['video/webm'],
    'avi' => ['video/x-msvideo', 'video/avi'],
    'mov' => ['video/quicktime'],
    'mkv' => ['video/x-matroska'],
    'wmv' => ['video/x-ms-wmv'],
    // Archives
    'zip' => ['application/zip', 'application/x-zip-compressed'],
    'rar' => ['application/x-rar-compressed', 'application/vnd.rar'],
    '7z' => ['application/x-7z-compressed'],
    'tar' => ['application/x-tar'],
    'gz' => ['application/gzip', 'application/x-gzip'],
    // Other
    'json' => ['application/json', 'text/json'],
    'xml' => ['application/xml', 'text/xml'],
    'yaml' => ['text/yaml', 'application/x-yaml', 'text/plain'],
    'yml' => ['text/yaml', 'application/x-yaml', 'text/plain'],
    'md' => ['text/markdown', 'text/plain'],
];
