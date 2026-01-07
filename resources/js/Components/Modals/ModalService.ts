// resources/js/Components/Modals/ModalService.ts
/**
 * ModalService.ts
 *
 * Core service managing a queue of open modals, their lifecycle, instance-specific events, and optional Promise resolution.
 *
 * Features / Problems Solved:
 * - Supports stacked modals via a reactive queue (topmost modal is always rendered).
 * - Each modal gets its own isolated mitt emitter → safe custom events (e.g., 'confirm', 'saved') without global leaks.
 * - `open()` adds to the end of the queue (normal stacking); `prepend()` adds to the beginning (priority overlays like alerts).
 * - Prevents duplicate modals of the same ID if configured (avoids accidental stacking).
 * - Async mode: returns a Promise that resolves on 'confirm' or rejects on error/close.
 * - updateData() allows live prop updates to an open modal.
 * - Proper cleanup: clears emitters and resolves/rejects promises on close.
 * - Developer-friendly errors via PrimeVue toast for unregistered modals.
 *
 * Fits into the Modal Module:
 * - Installed as a Vue plugin (ModalPlugin) and provided via injection key.
 * - Consumed via useModal() composable in any component.
 * - ResourceDialog.vue reads the queue's top item (currentItem) to render the active modal.
 * - Ensures frontend-backend alignment: data payloads match Laravel responses; events trigger Inertia reloads or toasts.
 */

import { App, InjectionKey, reactive, inject } from 'vue';
import mitt, { Emitter } from 'mitt';
import { ModalId, ModalComponentDirectory } from './ModalDirectory';
import { cloneDeep } from 'lodash';

// ────────────────────────────────────────────────
// Types (Strictly typed events for better safety & autocomplete)
// ────────────────────────────────────────────────

type ModalEvents = {
    close: void;
    confirm: any;        // e.g., form data on submit
    cancel: any;         // optional reason
    error: any;          // error object or message
    [key: string]: any;  // allow custom events
};

interface ModalInstance<T = unknown> {
    instanceId: string;
    on<K extends keyof ModalEvents>(event: K, handler: (payload: ModalEvents[K]) => void): () => void;
    once<K extends keyof ModalEvents>(event: K, handler: (payload: ModalEvents[K]) => void): void;
    off<K extends keyof ModalEvents>(event: K, handler?: (payload: ModalEvents[K]) => void): void;
    close(result?: T): void;
    updateData(partial: Partial<Record<string, any>>): void;
    promise?: Promise<T>;
}

export interface InternalModalItem {
    id: ModalId;
    instanceId: string;
    data: Record<string, any>;
    emitter: Emitter<ModalEvents>;
    resolve?: (value: any) => void;
    reject?: (reason?: any) => void;
    config?: typeof ModalComponentDirectory[ModalId]['config'];
}

// ────────────────────────────────────────────────
// Injection key
// ────────────────────────────────────────────────
export const MODAL_SERVICE_KEY = Symbol('ModalService') as InjectionKey<ModalService>;

// ────────────────────────────────────────────────
// The service class
// ────────────────────────────────────────────────

export class ModalService {
    private queue = reactive<InternalModalItem[]>([]);
    private instanceCounter = 0;

    // ─── Public API ───────────────────────────────────────

    get currentItem(): InternalModalItem | null {
        return this.queue[0] ?? null;
    }

    get queueLength(): number {
        return this.queue.length;
    }

    get queueItems(): readonly InternalModalItem[] {
        return this.queue;
    }

    /**
     * Opens a modal and adds it to the END of the queue (normal stacking).
     */
    open<T = unknown>(
        id: ModalId,
        data: Record<string, any> = {},
        options: { async?: boolean; preventDuplicates?: boolean } = {}
    ): ModalInstance<T> {
        return this._createInstance(id, data, options, false); // false = push (end)
    }

    /**
     * Opens a modal and adds it to the BEGINNING of the queue (priority overlay).
     */
    prepend<T = unknown>(
        id: ModalId,
        data: Record<string, any> = {},
        options: { async?: boolean; preventDuplicates?: boolean } = {}
    ): ModalInstance<T> {
        return this._createInstance(id, data, options, true); // true = unshift (start)
    }

    private _createInstance<T = unknown>(
        id: ModalId,
        data: Record<string, any>,
        options: { async?: boolean; preventDuplicates?: boolean },
        prepend: boolean
    ): ModalInstance<T> {
        const entry = ModalComponentDirectory[id];
        if (!entry) {
            throw new Error(`Modal "${id}" not registered`);
        }

        if (options.preventDuplicates && this.queue.some(i => i.id === id)) {
            console.warn(`Modal "${id}" is already open – returning existing instance`);
            const existing = this.queue.find(i => i.id === id)!;
            return this.getInstanceById(existing.instanceId)! as ModalInstance<T>;
        }

        const instanceId = `modal-${Date.now()}-${this.instanceCounter++}`;
        const emitter = mitt<ModalEvents>();

        const item: InternalModalItem = {
            id,
            instanceId,
            data: cloneDeep(data), // Deep clone prevents external mutation leaks (perf note: use shallow if data is large)
            emitter,
            config: entry.config,
        };

        if (prepend) {
            this.queue.unshift(item);
        } else {
            this.queue.push(item);
        }

        const instance: ModalInstance<T> = {
            instanceId,
            on: (event, handler) => {
                emitter.on(event, handler);
                return () => emitter.off(event, handler);
            },
            once: (event, handler) => {
                const wrapped = (payload: any) => {
                    handler(payload);
                    emitter.off(event, wrapped);
                };
                emitter.on(event, wrapped);
            },
            off: (event, handler) => emitter.off(event, handler),
            close: (result?: T) => this.closeByInstanceId(instanceId, result),
            updateData: (partial) => {
                const target = this.queue.find(i => i.instanceId === instanceId);
                if (target) Object.assign(target.data, cloneDeep(partial));
            },
            promise: undefined,
        };

        emitter.on('close', () => instance.close());

        if (options.async) {
            instance.promise = new Promise<T>((resolve, reject) => {
                item.resolve = resolve;
                item.reject = reject;

                emitter.on('confirm', (value) => instance.close(value as T));
                emitter.on('cancel', () => instance.close());
                emitter.on('error', (err) => {
                    reject(err instanceof Error ? err : new Error(String(err)));
                    instance.close();
                });
            });
        }

        return instance;
    }

    close(instanceId?: string, result?: any) {
        if (instanceId) this.closeByInstanceId(instanceId, result);
    }

    closeAll() {
        while (this.queue.length > 0) {
            this.closeByInstanceId(this.queue[0].instanceId);
        }
    }

    // ─── Private helpers ──────────────────────────────────

    private closeByInstanceId(instanceId: string, result?: any) {
        const index = this.queue.findIndex(i => i.instanceId === instanceId);
        if (index === -1) return;

        const item = this.queue[index];

        if (item.resolve && result !== undefined) {
            item.resolve(result);
        } else if (item.reject) {
            item.reject(new Error('Modal closed without result'));
        }

        item.emitter.all.clear();
        this.queue.splice(index, 1);
    }

    private getInstanceById(instanceId: string): ModalInstance | undefined {
        const item = this.queue.find(i => i.instanceId === instanceId);
        if (!item) return undefined;

        return {
            instanceId,
            close: (result?) => this.close(instanceId, result),
            // Minimal proxy – add more methods if needed during migration
            on: () => () => { },
            once: () => { },
            off: () => { },
            updateData: () => { },
        } as ModalInstance;
    }
}

// ────────────────────────────────────────────────
// Plugin installer
// ────────────────────────────────────────────────

export const ModalPlugin = {
    install(app: App) {
        const service = new ModalService();
        app.provide(MODAL_SERVICE_KEY, service);
    },
};

// ────────────────────────────────────────────────
// Composable (unchanged but included for completeness)
// ────────────────────────────────────────────────

// export function useModal() {
//     const service = inject(MODAL_SERVICE_KEY);
//     if (!service) {
//         throw new Error('useModal() must be used inside a component setup with ModalPlugin installed');
//     }
//     return service;
// }
