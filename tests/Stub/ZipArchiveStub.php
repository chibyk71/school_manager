<?php

/**
 * Minimal ZipArchive stub for environments without ext-zip (CI/sandbox).
 * Only constants referenced by config/backup.php are required for bootstrap.
 */
if (! class_exists('ZipArchive', false)) {
    class ZipArchive
    {
        public const CM_DEFAULT = -1;
        public const CM_STORE = 0;
        public const CM_DEFLATE = 8;
        public const CM_BZIP2 = 12;
        public const CM_XZ = 95;
    }
}
