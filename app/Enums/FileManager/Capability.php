<?php

namespace App\Enums\FileManager;

/**
 * Capability flags for module maximums and folder configuration.
 *
 * Effective capability = Module maximum ∩ Folder configuration ∩ User auth ∩ Ownership.
 */
enum Capability: string
{
    case Browse = 'browse';
    case CreateFolder = 'create_folder';
    case Upload = 'upload';
    case Download = 'download';
    case Rename = 'rename';
    case Move = 'move';
    case Attach = 'attach';
    case Delete = 'delete';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
