<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AssessmentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * AssessmentTemplateController
 *
 * CRUD for assessment templates — the scoring structure definition for exams.
 *
 * Routes:
 * ─────────────────────────────────────────────────────────────────────────────
 * GET    /assessment-templates         → index  (list, usually shown in Settings/Academic)
 * POST   /assessment-templates         → store
 * GET    /assessment-templates/{id}    → show   (JSON, for populating edit modal)
 * PATCH  /assessment-templates/{id}    → update
 * DELETE /assessment-templates         → destroy (bulk soft-delete)
 *
 * Key validation rules:
 * - Components array must have at least 2 items and at most 8
 * - Each component must have a unique key within the template
 * - The sum of all weight_percent values must equal exactly 100
 * - The sum of all max_score values must equal template.total_score
 * - Cannot update a template that is referenced by published/ongoing/completed exams
 *   (the template's components must remain stable once scores are being entered)
 *
 * Fits into the module:
 * - Rendered in Settings/Academic/AssessmentTemplates page
 * - ExamController reads templates via the foreign key
 * - ScoreEntryService reads components to build the entry form
 */
class AssessmentTemplateController extends Controller
{
    /**
     * List all templates for the current school.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AssessmentTemplate::class);

        $templates = AssessmentTemplate::with('schoolSection:id,name,display_name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($templates->map(fn($t) => [
                'id'                  => $t->id,
                'name'                => $t->name,
                'description'         => $t->description,
                'components'          => $t->sorted_components,
                'total_score'         => $t->total_score,
                'pass_mark'           => $t->pass_mark,
                'is_default'          => $t->is_default,
                'is_active'           => $t->is_active,
                'school_section_id'   => $t->school_section_id,
                'school_section_name' => $t->schoolSection?->display_name,
                'exams_count'         => $t->exams()->count(),
            ]));
        }

        return Inertia::render('Settings/Academic/AssessmentTemplates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Create a new assessment template.
     *
     * Validates that component weights sum to 100 and keys are unique.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', AssessmentTemplate::class);

        $validated = $this->validateTemplateRequest($request);

        try {
            $template = AssessmentTemplate::create($validated);

            return $request->wantsJson()
                ? response()->json(['template' => $template, 'message' => 'Template created.'], 201)
                : back()->with('success', "Template '{$template->name}' created.");
        } catch (\Throwable $e) {
            Log::error('Assessment template create failed', ['error' => $e->getMessage()]);
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create template.'], 500)
                : back()->with('error', 'Failed to create template.')->withInput();
        }
    }

    /**
     * Show a single template (JSON — used by edit modal).
     */
    public function show(AssessmentTemplate $assessmentTemplate)
    {
        Gate::authorize('view', $assessmentTemplate);

        return response()->json([
            'id'                => $assessmentTemplate->id,
            'name'              => $assessmentTemplate->name,
            'description'       => $assessmentTemplate->description,
            'components'        => $assessmentTemplate->sorted_components,
            'total_score'       => $assessmentTemplate->total_score,
            'pass_mark'         => $assessmentTemplate->pass_mark,
            'is_default'        => $assessmentTemplate->is_default,
            'is_active'         => $assessmentTemplate->is_active,
            'school_section_id' => $assessmentTemplate->school_section_id,
            'sort_order'        => $assessmentTemplate->sort_order,
            'exams_count'       => $assessmentTemplate->exams()->count(),
            'has_active_exams'  => $assessmentTemplate->exams()
                ->whereIn('status', ['published', 'ongoing', 'completed'])
                ->exists(),
        ]);
    }

    /**
     * Update a template.
     *
     * Blocks component changes if the template has active exams with scores entered.
     */
    public function update(Request $request, AssessmentTemplate $assessmentTemplate)
    {
        Gate::authorize('update', $assessmentTemplate);

        $validated = $this->validateTemplateRequest($request, $assessmentTemplate);

        // Block component changes on active exams
        $hasActiveExams = $assessmentTemplate->exams()
            ->whereIn('status', ['published', 'ongoing', 'completed', 'results_approved'])
            ->exists();

        if ($hasActiveExams && isset($validated['components'])) {
            $componentsChanged = json_encode($validated['components']) !== json_encode($assessmentTemplate->components);

            if ($componentsChanged) {
                $error = 'Cannot change components while this template has active exams with scores entered. Create a new template instead.';
                return $request->wantsJson()
                    ? response()->json(['error' => $error], 422)
                    : back()->withErrors(['components' => $error]);
            }
        }

        $assessmentTemplate->update($validated);

        return $request->wantsJson()
            ? response()->json(['template' => $assessmentTemplate->fresh(), 'message' => 'Template updated.'])
            : back()->with('success', 'Template updated.');
    }

    /**
     * Bulk soft-delete templates.
     *
     * Blocked if any template is referenced by an active exam.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', AssessmentTemplate::class);

        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:assessment_templates,id']);

        $deleted = 0;
        $errors  = [];

        foreach ($request->input('ids') as $id) {
            $template = AssessmentTemplate::find($id);
            if (!$template) continue;

            $hasExams = $template->exams()->exists();
            if ($hasExams) {
                $errors[] = "'{$template->name}' cannot be deleted — it is used by existing exams.";
                continue;
            }

            $template->delete();
            $deleted++;
        }

        return response()->json([
            'deleted' => $deleted,
            'errors'  => $errors,
            'message' => "{$deleted} template(s) deleted.",
        ]);
    }

    // ─── Shared Validation ────────────────────────────────────────────────────

    private function validateTemplateRequest(Request $request, ?AssessmentTemplate $existing = null): array
    {
        $rules = [
            'name'              => ['required', 'string', 'max:150'],
            'description'       => ['nullable', 'string', 'max:500'],
            'school_section_id' => ['nullable', 'exists:school_sections,id'],
            'is_default'        => ['boolean'],
            'is_active'         => ['boolean'],
            'total_score'       => ['required', 'integer', 'min:10', 'max:1000'],
            'pass_mark'         => ['required', 'integer', 'min:0', 'max:total_score'],
            'sort_order'        => ['integer', 'min:0'],

            'components'                 => ['required', 'array', 'min:2', 'max:8'],
            'components.*.key'           => ['required', 'string', 'regex:/^[a-z0-9_]+$/', 'max:30'],
            'components.*.label'         => ['required', 'string', 'max:60'],
            'components.*.max_score'     => ['required', 'numeric', 'min:1'],
            'components.*.weight_percent'=> ['required', 'numeric', 'min:1', 'max:100'],
            'components.*.is_exam'       => ['boolean'],
            'components.*.sort_order'    => ['integer', 'min:0'],
        ];

        $validated = $request->validate($rules, [
            'components.*.key.regex' => 'Component keys must be lowercase letters, numbers, and underscores only.',
        ]);

        // Custom: component keys must be unique within the template
        $keys = array_column($validated['components'], 'key');
        if (count($keys) !== count(array_unique($keys))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'components' => 'Each component must have a unique key.',
            ]);
        }

        // Custom: weights must sum to exactly 100
        $totalWeight = array_sum(array_column($validated['components'], 'weight_percent'));
        if (abs($totalWeight - 100) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'components' => "Component weights must sum to 100 (currently {$totalWeight}).",
            ]);
        }

        // Custom: max_scores must sum to total_score
        $totalMaxScore = array_sum(array_column($validated['components'], 'max_score'));
        if (abs($totalMaxScore - $validated['total_score']) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'components' => "Component max scores must sum to the template's total score ({$validated['total_score']}). Currently {$totalMaxScore}.",
            ]);
        }

        return $validated;
    }
}
