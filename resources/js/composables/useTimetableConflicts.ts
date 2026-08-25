import { computed, ref, type Ref, type ComputedRef } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue';
import type {
    ResolutionStrategy,
    TimetableConflict,
} from '@/types/timetable';

export interface ResolveConflictPayload {
    resolution_strategy: ResolutionStrategy;
    suggestion_index?: number;
    class_period_id?: number;
    day_of_week?: number;
    teacher_class_section_subject_id?: number;
    resolution_notes?: string;
}

/**
 * Manages conflict state for a timetable: fetch, list unresolved, resolve via API.
 */
export function useTimetableConflicts(
    timetableId: Ref<string | null | undefined> | ComputedRef<string | null | undefined>,
    initialConflicts?: Ref<TimetableConflict[]> | ComputedRef<TimetableConflict[]> | TimetableConflict[],
) {
    const toast = useToast();
    const conflicts = ref<TimetableConflict[]>(
        Array.isArray(initialConflicts)
            ? [...initialConflicts]
            : [...(initialConflicts?.value ?? [])],
    );
    const loading = ref(false);
    const resolvingId = ref<number | null>(null);

    const unresolved = computed(() =>
        conflicts.value.filter((c) => !c.resolved_at),
    );

    const unresolvedCount = computed(() => unresolved.value.length);

    const byType = computed(() => {
        const map: Record<string, TimetableConflict[]> = {};
        for (const c of unresolved.value) {
            const key = c.conflict_type;
            if (!map[key]) map[key] = [];
            map[key].push(c);
        }
        return map;
    });

    const setConflicts = (next: TimetableConflict[]) => {
        conflicts.value = [...next];
    };

    const fetchConflicts = async () => {
        const id = timetableId.value;
        if (!id) return;
        loading.value = true;
        try {
            const { data } = await axios.get(`/timetables/${id}/conflicts`, {
                headers: { Accept: 'application/json' },
            });
            const list = data?.data ?? data?.conflicts ?? data ?? [];
            conflicts.value = Array.isArray(list) ? list : [];
        } catch {
            // Endpoint may not exist yet — keep current state
        } finally {
            loading.value = false;
        }
    };

    const resolveConflict = async (
        conflictId: number,
        payload: ResolveConflictPayload,
    ): Promise<{ ok: boolean; slot?: unknown; message?: string }> => {
        const id = timetableId.value;
        if (!id) {
            return { ok: false, message: 'No timetable selected' };
        }
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

            toast.add({
                severity: 'success',
                summary: 'Conflict resolved',
                detail: data?.message ?? 'The conflict has been resolved.',
                life: 3000,
            });

            return { ok: true, slot: data?.slot ?? data?.data };
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
        byType,
        loading,
        resolvingId,
        setConflicts,
        fetchConflicts,
        resolveConflict,
        resolveWithSuggestion,
        resolveManual,
        skipConflict,
    };
}
