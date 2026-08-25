import { computed, ref, watch, type Ref, type ComputedRef } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue';
import type {
    ResolutionStrategy,
    TimetableConflict,
    TimetableSlot,
    UUID,
} from '@/types/timetable';

export interface ResolveConflictPayload {
    resolution_strategy: ResolutionStrategy;
    suggestion_index?: number;
    class_period_id?: number;
    day_of_week?: number;
    teacher_class_section_subject_id?: number;
    resolution_notes?: string;
}

export interface ResolveResult {
    ok: boolean;
    slot?: TimetableSlot | null;
    message?: string;
}

export function useTimetableConflicts(
    timetableId: Ref<UUID | null | undefined> | ComputedRef<UUID | null | undefined>,
    initialConflicts?:
        | Ref<TimetableConflict[]>
        | ComputedRef<TimetableConflict[]>
        | TimetableConflict[],
) {
    const toast = useToast();

    const initialList = (): TimetableConflict[] => {
        if (Array.isArray(initialConflicts)) return [...initialConflicts];
        return [...(initialConflicts?.value ?? [])];
    };

    const conflicts = ref<TimetableConflict[]>(initialList());
    const loading = ref(false);
    const resolvingId = ref<number | null>(null);
    const lastError = ref<string | null>(null);

    if (initialConflicts && !Array.isArray(initialConflicts)) {
        watch(
            () => initialConflicts.value,
            (v) => {
                if (v) conflicts.value = [...v];
            },
            { deep: true },
        );
    }

    const unresolved = computed(() => conflicts.value.filter((c) => !c.resolved_at));
    const unresolvedCount = computed(() => unresolved.value.length);

    const setConflicts = (next: TimetableConflict[]) => {
        conflicts.value = [...next];
        lastError.value = null;
    };

    const fetchConflicts = async (): Promise<{ ok: boolean; error?: string }> => {
        const id = timetableId.value;
        if (!id) return { ok: false, error: 'No timetable selected' };

        loading.value = true;
        lastError.value = null;
        try {
            const { data } = await axios.get(`/timetables/${id}/conflicts`, {
                headers: { Accept: 'application/json' },
            });
            const list = data?.data ?? data?.conflicts ?? data ?? [];
            conflicts.value = Array.isArray(list) ? list : [];
            return { ok: true };
        } catch (err: unknown) {
            const status = (err as { response?: { status?: number } })?.response?.status;
            const message =
                (err as { response?: { data?: { message?: string } } })?.response?.data
                    ?.message ??
                (status === 404
                    ? 'Conflicts endpoint not available'
                    : 'Failed to load conflicts');
            lastError.value = message;
            toast.add({
                severity: 'error',
                summary: 'Could not load conflicts',
                detail: message,
                life: 5000,
            });
            return { ok: false, error: message };
        } finally {
            loading.value = false;
        }
    };

    const resolveConflict = async (
        conflictId: number,
        payload: ResolveConflictPayload,
    ): Promise<ResolveResult> => {
        const id = timetableId.value;
        if (!id) return { ok: false, message: 'No timetable selected' };

        resolvingId.value = conflictId;
        try {
            const { data } = await axios.post(
                `/timetables/${id}/conflicts/${conflictId}/resolve`,
                payload,
                { headers: { Accept: 'application/json' } },
            );

            const idx = conflicts.value.findIndex((c) => c.id === conflictId);
            if (idx !== -1) {
                const next = [...conflicts.value];
                next[idx] = {
                    ...next[idx],
                    resolved_at: new Date().toISOString(),
                    resolution_notes: payload.resolution_notes ?? null,
                };
                conflicts.value = next;
            }

            const rawSlot = data?.slot ?? data?.data?.slot ?? data?.data ?? null;
            const slot = (rawSlot && rawSlot.id ? rawSlot : null) as TimetableSlot | null;

            toast.add({
                severity: 'success',
                summary: 'Conflict resolved',
                detail: data?.message ?? 'The conflict has been resolved.',
                life: 3000,
            });

            return { ok: true, slot };
        } catch (err: unknown) {
            const message =
                (err as { response?: { data?: { message?: string } } })?.response?.data
                    ?.message ?? 'Failed to resolve conflict';
            toast.add({
                severity: 'error',
                summary: 'Resolve failed',
                detail: message,
                life: 5000,
            });
            return { ok: false, message };
        } finally {
            resolvingId.value = null;
        }
    };

    const resolveWithSuggestion = (conflictId: number, suggestionIndex = 0) =>
        resolveConflict(conflictId, {
            resolution_strategy: 'use_suggestion',
            suggestion_index: suggestionIndex,
        });

    const resolveManual = (
        conflictId: number,
        dayOfWeek: number,
        classPeriodId: number,
        notes?: string,
        tcssId?: number,
    ) =>
        resolveConflict(conflictId, {
            resolution_strategy: 'manual',
            day_of_week: dayOfWeek,
            class_period_id: classPeriodId,
            resolution_notes: notes,
            teacher_class_section_subject_id: tcssId,
        });

    const skipConflict = (conflictId: number, notes: string) =>
        resolveConflict(conflictId, {
            resolution_strategy: 'skip',
            resolution_notes: notes,
        });

    return {
        conflicts,
        unresolved,
        unresolvedCount,
        loading,
        resolvingId,
        lastError,
        setConflicts,
        fetchConflicts,
        resolveConflict,
        resolveWithSuggestion,
        resolveManual,
        skipConflict,
    };
}
