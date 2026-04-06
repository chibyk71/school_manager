<?php

namespace App\Services\BulkActions;

use App\Contracts\BulkActions\BulkActionHandler;
use App\Actions\BulkActions\DeleteBulkAction;
use App\Actions\BulkActions\RestoreBulkAction;
use App\Actions\BulkActions\ForceDeleteBulkAction;
use Illuminate\Support\Collection;

/**
 * BulkActionRegistry.php
 *
 * Central registry for all bulk action handlers in the Bulk Actions Module.
 *
 * This class acts as a factory and mapping service that resolves action names
 * ('delete', 'restore', 'force_delete', etc.) to their concrete handler implementations.
 *
 * Features / Problems Solved:
 * - Single source of truth for which handler handles which action
 * - Makes the system highly extensible — adding a new action only requires:
 *     1. Creating a new handler class implementing BulkActionHandler
 *     2. Registering it once in this registry
 * - Prevents hardcoded match/switch statements in BulkActionService
 * - Supports lazy instantiation of handlers (better performance and memory usage)
 * - Allows easy overriding or replacement of handlers per environment or tenant
 * - Fully compatible with dependency injection
 *
 * Role in the Bulk Actions Package:
 * - Used by BulkActionService to resolve the correct handler based on the request
 * - Bridges the gap between action name (from frontend/request) and concrete class
 * - Enables future actions (activate, deactivate, approve, etc.) with zero changes to the Service
 *
 * How it fits into the architecture:
 * 1. BulkActionRequest provides the action name
 * 2. BulkActionService asks the Registry: "give me handler for 'delete'"
 * 3. Registry returns the appropriate BulkActionHandler instance
 * 4. Service calls $handler->handle($request, $modelClass)
 *
 * Extensibility:
 * To add a new action (e.g. 'activate'):
 *   - Create ActivateBulkAction implementing BulkActionHandler
 *   - Add it to the $handlers array in registerDefaultHandlers()
 *   - Done. No changes needed anywhere else.
 */

class BulkActionRegistry
{
    /**
     * Registered handlers mapped by action name.
     *
     * @var array<string, class-string<BulkActionHandler>>
     */
    protected array $handlers = [];

    /**
     * Create a new registry and register default core handlers.
     */
    public function __construct()
    {
        $this->registerDefaultHandlers();
    }

    /**
     * Register the three core handlers.
     *
     * This method is called automatically on construction.
     * You can override or extend it in a child class if needed.
     */
    protected function registerDefaultHandlers(): void
    {
        $this->register('delete', DeleteBulkAction::class);
        $this->register('restore', RestoreBulkAction::class);
        $this->register('force_delete', ForceDeleteBulkAction::class);

        // Future actions can be registered here or via the register() method:
        // $this->register('activate', ActivateBulkAction::class);
    }

    /**
     * Register a new bulk action handler.
     *
     * @param string $actionName Machine name of the action (e.g. 'delete')
     * @param class-string<BulkActionHandler> $handlerClass Fully qualified handler class
     * @return self
     */
    public function register(string $actionName, string $handlerClass): self
    {
        if (!is_subclass_of($handlerClass, BulkActionHandler::class)) {
            throw new \InvalidArgumentException(
                "Handler class {$handlerClass} must implement BulkActionHandler interface."
            );
        }

        $this->handlers[$actionName] = $handlerClass;

        return $this;
    }

    /**
     * Resolve and return a handler instance for the given action name.
     *
     * @param string $actionName
     * @return BulkActionHandler
     * @throws \InvalidArgumentException When no handler is registered for the action
     */
    public function resolve(string $actionName): BulkActionHandler
    {
        if (!isset($this->handlers[$actionName])) {
            throw new \InvalidArgumentException(
                "No handler registered for bulk action: '{$actionName}'. " .
                "Registered actions: " . implode(', ', array_keys($this->handlers))
            );
        }

        $handlerClass = $this->handlers[$actionName];

        return app($handlerClass); // Laravel container resolves the handler
    }

    /**
     * Get all registered action names.
     *
     * @return array<string>
     */
    public function getRegisteredActions(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Check if a handler exists for the given action.
     */
    public function has(string $actionName): bool
    {
        return isset($this->handlers[$actionName]);
    }

    /**
     * Return all registered handlers as a collection (useful for debugging or admin panels).
     *
     * @return Collection<string, class-string<BulkActionHandler>>
     */
    public function all(): Collection
    {
        return Collection::make($this->handlers);
    }
}
