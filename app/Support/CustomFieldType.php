<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * CustomFieldType
 *
 * Single source of truth for all supported custom field types in the system.
 *
 * Responsibilities:
 *   - Define all allowed values for the 'field_type' column
 *   - Provide metadata for each type (PrimeVue component, icon, behavior flags)
 *   - Offer helper methods for validation, rendering, and constraints
 *   - Prevent invalid types from being saved (via isValid() / assertValid())
 *   - Exportable to frontend (toJson() or via Ziggy/Inertia shared data)
 *
 * Usage examples:
 *   CustomFieldType::TEXT->value                  → 'text'
 *   CustomFieldType::FILE->getComponent()         → 'FileUpload'
 *   CustomFieldType::SELECT->hasOptions()         → true
 *   CustomFieldType::from('date')                 → CustomFieldType::DATE
 *   CustomFieldType::isFileType('image')          → true
 *
 * Benefits:
 *   - No more magic strings in controllers, validation, or frontend
 *   - Easy to add new types in one place
 *   - Consistent icons/components across backend + frontend
 *   - Better IDE autocompletion and refactoring safety
 */
enum CustomFieldType: string
{
    // ─── Text-based ─────────────────────────────────────────────────────────────
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case EMAIL = 'email';
    case TEL = 'tel';
    case URL = 'url';
    case PASSWORD = 'password';

    // ─── Date & Time ────────────────────────────────────────────────────────────
    case DATE = 'date';
    case DATETIME = 'datetime';

    // ─── Selection ──────────────────────────────────────────────────────────────
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
    case RADIO = 'radio';
    case CHECKBOX = 'checkbox';

    // ─── Boolean / Toggle ───────────────────────────────────────────────────────
    case BOOLEAN = 'boolean';

    // ─── File & Media ───────────────────────────────────────────────────────────
    case FILE = 'file';
    case IMAGE = 'image';

    // ─── Visual / Advanced ──────────────────────────────────────────────────────
    case COLOR = 'color';

    // Future-ready (uncomment when implemented)
    // case SIGNATURE = 'signature';
    // case RATING    = 'rating';
    // case COUNTRY   = 'country';

    /**
     * Get all supported types as array (for validation rules, dropdowns, etc.)
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if the given string is a valid field type
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Assert that the type is valid – throws if not
     *
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $type): void
    {
        if (!self::isValid($type)) {
            throw new InvalidArgumentException("Unsupported custom field type: '{$type}'");
        }
    }

    /**
     * Get metadata for rendering and behavior
     *
     * Returns array with:
     *   - name:         Human-readable name
     *   - icon:         PrimeIcon class
     *   - component:    PrimeVue component name
     *   - has_options:  Needs options array (select, radio, etc.)
     *   - is_file:      Requires file upload handling
     *   - default_rules Default Laravel validation rules for this type
     */
    public function getMetadata(): array
    {
        return match ($this) {
            self::TEXT => [
                'name' => 'Text',
                'icon' => 'pi pi-pencil',
                'component' => 'InputText',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['string', 'max:255'],
            ],

            self::TEXTAREA => [
                'name' => 'Textarea',
                'icon' => 'pi pi-align-left',
                'component' => 'Textarea',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['string', 'max:2000'],
            ],

            self::NUMBER => [
                'name' => 'Number',
                'icon' => 'pi pi-sort-numeric-down',
                'component' => 'InputNumber',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['numeric'],
            ],

            self::EMAIL => [
                'name' => 'Email',
                'icon' => 'pi pi-envelope',
                'component' => 'InputText',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['email', 'max:255'],
            ],

            self::TEL => [
                'name' => 'Phone',
                'icon' => 'pi pi-phone',
                'component' => 'InputText',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['string', 'max:20'],
            ],

            self::URL => [
                'name' => 'URL',
                'icon' => 'pi pi-link',
                'component' => 'InputText',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['url', 'max:255'],
            ],

            self::PASSWORD => [
                'name' => 'Password',
                'icon' => 'pi pi-lock',
                'component' => 'Password',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['string', 'min:8'],
            ],

            self::DATE => [
                'name' => 'Date',
                'icon' => 'pi pi-calendar',
                'component' => 'Calendar',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['date'],
            ],

            self::DATETIME => [
                'name' => 'Date & Time',
                'icon' => 'pi pi-calendar-times',
                'component' => 'Calendar',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['date'],
            ],

            self::SELECT => [
                'name' => 'Select',
                'icon' => 'pi pi-list',
                'component' => 'Dropdown',
                'has_options' => true,
                'is_file' => false,
                'default_rules' => ['string'],
            ],

            self::MULTISELECT => [
                'name' => 'Multi-Select',
                'icon' => 'pi pi-list',
                'component' => 'MultiSelect',
                'has_options' => true,
                'is_file' => false,
                'default_rules' => ['array'],
            ],

            self::RADIO => [
                'name' => 'Radio',
                'icon' => 'pi pi-circle',
                'component' => 'RadioButton',
                'has_options' => true,
                'is_file' => false,
                'default_rules' => ['string'],
            ],

            self::CHECKBOX => [
                'name' => 'Checkbox',
                'icon' => 'pi pi-check-square',
                'component' => 'Checkbox',
                'has_options' => true,
                'is_file' => false,
                'default_rules' => ['boolean'],
            ],

            self::BOOLEAN => [
                'name' => 'Boolean / Toggle',
                'icon' => 'pi pi-power-off',
                'component' => 'ToggleSwitch',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['boolean'],
            ],

            self::FILE => [
                'name' => 'File Upload',
                'icon' => 'pi pi-file',
                'component' => 'FileUpload',
                'has_options' => false,
                'is_file' => true,
                'default_rules' => ['file'],
            ],

            self::IMAGE => [
                'name' => 'Image Upload',
                'icon' => 'pi pi-image',
                'component' => 'FileUpload',
                'has_options' => false,
                'is_file' => true,
                'default_rules' => ['image'],
            ],

            self::COLOR => [
                'name' => 'Color Picker',
                'icon' => 'pi pi-palette',
                'component' => 'ColorPicker',
                'has_options' => false,
                'is_file' => false,
                'default_rules' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ],
        };
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Convenience wrappers (most commonly used)
    // ────────────────────────────────────────────────────────────────────────────

    public function getComponent(): string
    {
        return $this->getMetadata()['component'];
    }

    public function getIcon(): string
    {
        return $this->getMetadata()['icon'];
    }

    public function hasOptions(): bool
    {
        return $this->getMetadata()['has_options'];
    }

    public function isFileType(): bool
    {
        return $this->getMetadata()['is_file'];
    }

    public function getDefaultRules(): array
    {
        return $this->getMetadata()['default_rules'];
    }

    /**
     * Get suggested Laravel validation rules including file constraints
     * (used when no custom rules are set)
     */
    public function getSuggestedRules(array $field = []): array
    {
        $rules = $this->getDefaultRules();

        if ($this->isFileType()) {
            $maxKb = $field['max_file_size_kb'] ?? 2048; // default 2MB
            $extensions = $field['allowed_extensions'] ?? [];

            $fileRule = 'file|max:' . $maxKb;

            if (!empty($extensions)) {
                $fileRule .= '|mimes:' . implode(',', $extensions);
            }

            if ($this === self::IMAGE) {
                $fileRule .= '|image';
            }

            $rules[] = $fileRule;
        }

        return $rules;
    }

    /**
     * Export all types as JSON array for frontend consumption
     * (can be shared via Inertia or Ziggy)
     */
    public static function toFrontendArray(): array
    {
        $result = [];

        foreach (self::cases() as $case) {
            $meta = $case->getMetadata();
            $result[$case->value] = [
                'name' => $meta['name'],
                'icon' => $meta['icon'],
                'component' => $meta['component'],
                'has_options' => $meta['has_options'],
                'is_file' => $meta['is_file'],
            ];
        }

        return $result;
    }
}
