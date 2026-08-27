<?php

namespace App\Http\Controllers;

use App\Http\Requests\Promotion\ApprovePromotionBatchRequest;
use App\Http\Requests\Promotion\CancelPromotionBatchRequest;
use App\Http\Requests\Promotion\ExecutePromotionBatchRequest;
use App\Http\Requests\Promotion\OverridePromotionStudentRequest;
use App\Http\Requests\Promotion\StorePromotionBatchRequest;
use App\Http\Resources\Promotion\PromotionBatchResource;
use App\Http\Resources\Promotion\PromotionStudentResource;
use App\Models\Academic\AcademicSession;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionStudent;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PromotionBatchController extends Controller
{
    public function __construct(protected PromotionService $promotionService)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PromotionBatch::class);

        $batches = PromotionBatch::query()
            ->with(['academicSession', 'initiatedBy', 'approvedBy'])
            ->latest()
            ->tableQuery($request, [
                ['field' => 'session_name', 'relation' => 'academicSession', 'relatedField' => 'name'],
            ]);

        $academicSessions = AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        return Inertia::render('Promotion/Index', [
            'batches' => PromotionBatchResource::collection($batches->get())->resolve(),
            'academicSessions' => $academicSessions,
            'can' => [
                'create' => Gate::allows('create', PromotionBatch::class),
            ],
        ]);
    }

    public function store(StorePromotionBatchRequest $request)
    {
        Gate::authorize('create', PromotionBatch::class);

        $session = AcademicSession::query()->findOrFail($request->validated('academic_session_id'));

        $batch = $this->promotionService->createBatchForSession(
            $session,
            $request->user(),
            $request->validated('name'),
            $request->validated('description')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Promotion batch created. Population is running in the background.',
                'batch' => new PromotionBatchResource($batch),
            ], 201);
        }

        return redirect()
            ->route('promotions.show', $batch)
            ->with('success', 'Promotion batch created. Student recommendations are being computed.');
    }

    public function show(PromotionBatch $batch): Response
    {
        Gate::authorize('view', $batch);

        $batch->load(['academicSession', 'initiatedBy', 'approvedBy', 'executedBy']);

        return Inertia::render('Promotion/Show', [
            'batch' => (new PromotionBatchResource($batch))->resolve(),
            'can' => $this->abilities($batch),
        ]);
    }

    public function review(PromotionBatch $batch): Response
    {
        Gate::authorize('review', $batch);

        $batch->load(['academicSession']);

        $students = PromotionStudent::query()
            ->where('promotion_batch_id', $batch->id)
            ->with(['student.profile', 'currentClassSection', 'nextClassSection', 'overriddenBy'])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Promotion/Review', [
            'batch' => (new PromotionBatchResource($batch))->resolve(),
            'students' => PromotionStudentResource::collection($students)->resolve(),
            'can' => $this->abilities($batch),
        ]);
    }

    public function override(OverridePromotionStudentRequest $request, PromotionBatch $batch, PromotionStudent $student)
    {
        Gate::authorize('override', $batch);

        if ($student->promotion_batch_id !== $batch->id) {
            abort(404);
        }

        $updated = $this->promotionService->overrideStudentDecision(
            $student,
            $request->validated('final_decision'),
            $request->validated('override_reason'),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Student decision updated.',
                'student' => new PromotionStudentResource($updated->load(['student.profile', 'overriddenBy'])),
                'batch' => new PromotionBatchResource($batch->fresh()),
            ]);
        }

        return back()->with('success', 'Student decision updated.');
    }

    public function approve(ApprovePromotionBatchRequest $request, PromotionBatch $batch)
    {
        Gate::authorize('approve', $batch);

        $this->promotionService->approveBatch(
            $batch,
            $request->user(),
            $request->validated('approval_comments') ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Promotion batch approved.',
                'batch' => new PromotionBatchResource($batch->fresh()),
            ]);
        }

        return back()->with('success', 'Promotion batch approved.');
    }

    public function execute(ExecutePromotionBatchRequest $request, PromotionBatch $batch)
    {
        Gate::authorize('execute', $batch);

        $this->promotionService->executeBatch($batch, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Promotion execution started.',
                'batch' => new PromotionBatchResource($batch->fresh()),
            ]);
        }

        return back()->with('success', 'Promotion execution started.');
    }

    public function cancel(CancelPromotionBatchRequest $request, PromotionBatch $batch)
    {
        Gate::authorize('cancel', $batch);

        $this->promotionService->cancelBatch(
            $batch,
            $request->user(),
            $request->validated('reason') ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Promotion batch cancelled.',
                'batch' => new PromotionBatchResource($batch->fresh()),
            ]);
        }

        return back()->with('success', 'Promotion batch cancelled.');
    }

    protected function abilities(PromotionBatch $batch): array
    {
        return [
            'review' => Gate::allows('review', $batch),
            'override' => Gate::allows('override', $batch),
            'approve' => Gate::allows('approve', $batch),
            'execute' => Gate::allows('execute', $batch),
            'cancel' => Gate::allows('cancel', $batch),
        ];
    }
}
