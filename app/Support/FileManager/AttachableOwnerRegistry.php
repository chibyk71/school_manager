<?php

namespace App\Support\FileManager;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\School;
use App\Models\Student\Student;
use App\Models\User;
use InvalidArgumentException;

/**
 * Explicit allow-list of polymorphic owner types for File Manager attachments.
 *
 * Never trust arbitrary client-supplied class names.
 */
final class AttachableOwnerRegistry
{
    /**
     * Map of short purpose-friendly aliases → fully-qualified class names.
     *
     * @var array<string, class-string>
     */
    private static array $aliases = [
        'user' => User::class,
        'profile' => Profile::class,
        'school' => School::class,
        'student' => Student::class,
        'guardian' => Guardian::class,
    ];

    /**
     * @return array<string, class-string>
     */
    public static function aliases(): array
    {
        return self::$aliases;
    }

    /**
     * @return list<class-string>
     */
    public static function allowedClasses(): array
    {
        return array_values(array_unique(array_values(self::$aliases)));
    }

    public static function isAllowedClass(string $class): bool
    {
        return in_array($class, self::allowedClasses(), true);
    }

    /**
     * Resolve a client-supplied type (alias or FQCN) to an allowed FQCN.
     *
     * @throws InvalidArgumentException when the type is not allow-listed
     */
    public static function resolve(string $type): string
    {
        $normalized = ltrim($type, '\\');

        if (isset(self::$aliases[$normalized])) {
            return self::$aliases[$normalized];
        }

        // Accept FQCN only when it is on the allow-list.
        if (self::isAllowedClass($normalized)) {
            return $normalized;
        }

        // Case-insensitive alias match
        $lower = strtolower($normalized);
        foreach (self::$aliases as $alias => $class) {
            if (strtolower($alias) === $lower) {
                return $class;
            }
        }

        throw new InvalidArgumentException(
            "Owner type '{$type}' is not an allowed File Manager attachment owner."
        );
    }
}
