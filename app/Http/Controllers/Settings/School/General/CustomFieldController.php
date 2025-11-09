<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\School;
use App\Services\SchoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    protected array $resources = [
        'Staff' => \App\Models\Staff::class,
        'Guardian' => \App\Models\Guardian::class,
        'Student' => \App\Models\Student::class,
        'Certificate' => \App\Models\Certificate::class,
        'Result' => \App\Models\Result::class,
    ];

    public function index(Request $request)
    {
        $school = $this->school();

        $query = CustomField::query()
            ->forSchool($school)
            ->when($request->resource, fn($q) => $q->forModel($request->resource))
            ->withTableQuery($request)
            ->ordered();

        return Inertia::render('Settings/School/CustomField', [
            'settings' => $query->get(),
            'resources' => array_keys($this->resources),
            'columns' => ColumnDefinitionHelper::fromModel(new CustomField()),
            'filters' => $request->only(['search', 'resource', 'sort', 'direction']),
        ]);
    }

    public function json(string $resource)
    {
        $this->validateResource($resource);

        $fields = CustomField::forSchool($this->school())
            ->forModel($this->resources[$resource])
            ->ordered()
            ->get()
            ->groupBy('category')
            ->map(fn($fields, $cat) => [
                'category' => $cat ?? 'General',
                'count' => $fields->count(),
                'fields' => $fields->makeHidden(['school_id', 'deleted_at']),
            ])
            ->values();

        return response()->json($fields);
    }

    public function store(Request $request)
    {
        Gate::authorize('create');

        $data = $this->validatedData($request);

        DB::transaction(function () use ($data) {
            CustomField::create($data);
        });

        return back()->with('success', __('Custom field created.'));
    }

    public function update(Request $request, CustomField $customField)
    {
        Gate::authorize('update', $customField);
        $this->ensureOwnership($customField);

        $data = $this->validatedData($request, $customField);

        DB::transaction(function () use ($customField, $data) {
            $customField->update($data);
        });

        return back()->with('success', __('Custom field updated.'));
    }

    public function destroy(Request $request, ?CustomField $customField = null)
    {
        Gate::authorize('delete');
        
        $school = $this->school();
        $deleted = 0;

        DB::transaction(function () use ($request, $customField, $school, &$deleted) {
            if ($request->filled('ids') && is_array($ids = $request->ids)) {
                $deleted = CustomField::forSchool($school)->whereIn('id', $ids)->delete();
            } elseif ($customField?->exists && $customField->school_id === $school->id) {
                $customField->delete();
                $deleted = 1;
            }
        });

        if ($deleted === 0) {
            return response()->json(['success' => false, 'message' => 'Nothing deleted.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => __(':count field(s) deleted.', ['count' => $deleted]),
        ]);
    }

    public function reorder(Request $request)
    {
        Gate::authorize('manage-custom-fields');

        $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:custom_fields,id',
            'fields.*.sort' => 'required|integer|min:0',
        ]);

        $school = $this->school();

        DB::transaction(function () use ($request, $school) {
            foreach ($request->fields as $item) {
                CustomField::where('id', $item['id'])
                    ->where('school_id', $school->id)
                    ->update(['sort' => $item['sort']]);
            }
        });

        return response()->json(['success' => true]);
    }

    protected function validatedData(Request $request, ?CustomField $field = null): array
    {
        $school = $this->school();
        $id = $field?->id;

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                \Illuminate\Validation\Rule::unique('custom_fields')
                    ->where('school_id', $school->id)
                    ->where('model_type', $request->model_type)
                    ->ignore($id),
            ],
            'label' => 'required|string|max:255',
            'field_type' => 'required|in:text,textarea,select,radio,checkbox,date,file',
            'model_type' => 'required|in:' . implode(',', array_keys($this->resources)),
            'category' => 'nullable|string|max:100',
            'sort' => 'nullable|integer|min:0',
            'required' => 'sometimes|boolean',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'rules' => 'nullable|array',
            'rules.*' => 'string',
        ];

        $data = $request->validate($rules);

        // Normalize name
        $data['name'] = Str::snake(trim($data['name']));

        // Build rules array
        $data['rules'] = array_filter([
            ...($data['rules'] ?? []),
            $request->boolean('required') ? 'required' : null,
        ]);

        // Auto-add 'in:' rule for option-based fields
        if (in_array($data['field_type'], ['select', 'radio', 'checkbox']) && !empty($data['options'])) {
            $data['rules'][] = 'in:' . implode(',', $data['options']);
        }

        $data['has_options'] = in_array($data['field_type'], ['select', 'radio', 'checkbox']);
        $data['school_id'] = $school->id;
        $data['model_type'] = $this->resources[$data['model_type']];

        return $data;
    }

    protected function school(): School
    {
        return app(SchoolService::class)->current() ?? throw new \Exception('No active school.');
    }

    protected function ensureOwnership(CustomField $field): void
    {
        if ($field->school_id !== $this->school()->id) {
            abort(403);
        }
    }

    protected function validateResource(string $resource): void
    {
        if (!array_key_exists($resource, $this->resources)) {
            abort(404, 'Resource not found.');
        }
    }
}
