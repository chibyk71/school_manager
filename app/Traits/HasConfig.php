<?php

namespace App\Traits;

use App\Models\Configuration\Config;
use App\Models\School;
use Illuminate\Support\Facades\Log;

trait HasConfig
{
    /* ------------------------------------------------------------------ */
    /* Must be implemented by the model that uses the trait               */
    /* ------------------------------------------------------------------ */

    /** @return array<string> */
    abstract public function getConfigurableProperties(): array;

    /* ------------------------------------------------------------------ */
    /* Helper – add / update a shared config definition                   */
    /* ------------------------------------------------------------------ */

    public function addConfig(
        string $name,
        string $label,
        array $options = [],
    ): Config {
        try {
            $school = GetSchoolModel() ?? $this;  // Assume $this is School if no global
            
            return Config::updateOrCreate(
                [
                    'name'       => $name,
                    'applies_to' => $this::class,
                    'school'   => $school?->id,
                ],
                [
                    'label'   => $label,
                    'options' => $options,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to add config '{$name}': " . $e->getMessage());
            throw $e;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Fetch visible configs for this model (school-scoped or system)    */
    /* ------------------------------------------------------------------ */

    public function getVisibleConfigs()
    {
        $school = GetSchoolModel() ?? $this;  // Assume $this is School

        return Config::visibleToSchool($school?->id)
            ->forModel($this::class)
            ->whereIn('name', $this->getConfigurableProperties())
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Store the *selected* value on the model itself                    */
    /* ------------------------------------------------------------------ */

    public function setConfigValue(string $name, $value): void
    {
        if (!in_array($name, $this->getConfigurableProperties())) {
            throw new \Exception("Invalid configurable property: {$name}");
        }

        $this->forceFill([$name => $value])->save();
    }

    public function getConfigValue(string $name, $default = null)
    {
        return $this->getAttribute($name) ?? $default;
    }
}