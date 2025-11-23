<?php
// app/Http/Controllers/Promotion/PromotionBatchController.php

namespace App\Http\Controllers;

use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionStudent;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessStudentPromotion;

class PromotionBatchController extends Controller
{
    protected PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**
     * Show list of promotion batches
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PromotionBatch::class);

        $batches = PromotionBatch::currentSchool()
            ->with(['academicSession', 'principal'])
            ->latest()
            ->tableQuery($request, [
                ['field' => 'session_name', 'relation' => 'academicSession', 'relatedField' => 'name'],
                ['field' => 'principal_name', 'relation' => 'principal', 'relatedField' => 'name'],
            ])
            ->get();

        return Inertia::render('Promotion/Index', [
            'batches' => $batches,
            'can' => [
                'create' => false, // auto-created
                'execute' => Gate::allows('execute', PromotionBatch::class),
            ]
        ]);
    }

    /**
     * Review a promotion batch
     */
    public function review(PromotionBatch $batch)
    {
        Gate::authorize('view', $batch);

        $batch->load(['academicSession', 'principal']);

        // Load students with relations
        $students = PromotionStudent::where('promotion_batch_id', $batch->id)
            ->with([
                'student.user',
                'currentSection.classLevel',
                'nextSection.classLevel',
                'overriddenBy'
            ])
            ->tableQuery($request ?? request(), [
                ['field' => 'student_name', 'relation' => 'student.user', 'relatedField' => 'name'],
                ['field' => 'current_class', 'relation' => 'currentSection.classLevel', 'relatedField' => 'display_name'],
                ['field' => 'next_class', 'relation' => 'nextSection.classLevel', 'relatedField' => 'display_name'],
            ])
            ->get();

        return Inertia::render('Promotion/Review', [
            'batch' => $batch->append(['progress_percentage', 'can_execute']),
            'students' => $students,
            'stats' => [
                'total' => $batch->total_students,
                'promote' => $batch->students()->where('recommendation', 'promote')->count(),
                'probation' => $batch->students()->where('recommendation', 'probation')->count(),
                'repeat' => $batch->students()->where('recommendation', 'repeat')->count(),
                'graduated' => $batch->students()->where('recommendation', 'graduated')->count(),
            ],
            'can' => [
                'approve' => $batch->status === 'pending' && Gate::allows('approve', $batch),
                'execute' => $batch->can_execute && Gate::allows('execute', $batch),
            ]
        ]);
    }

    /**
     * Principal approves the batch
     */
    public function approve(Request $request, PromotionBatch $batch)
    {
        Gate::authorize('approve', $batch);

        $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $batch->update([
            'status' => 'approved',
            'principal_id' => auth()->id(),
            'principal_reviewed_at' => now(),
            'principal_comments' => $request->comments,
        ]);

        return redirect()->route('promotions.review', $batch)
            ->with('success', 'Promotion batch approved successfully!');
    }

    /**
     * Principal rejects the batch
     */
    public function reject(Request $request, PromotionBatch $batch)
    {
        Gate::authorize('approve', $batch);

        $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        $batch->update([
            'status' => 'rejected',
            'principal_id' => auth()->id(),
            'principal_reviewed_at' => now(),
            'principal_comments' => $request->comments,
        ]);

        return redirect()->route('promotions.index')
            ->with('error', 'Promotion batch rejected.');
    }

    /**
     * Execute approved batch
     */
    public function execute(PromotionBatch $batch)
    {
        Gate::authorize('execute', $batch);

        if (!$batch->can_execute) {
            return back()->with('error', 'This batch cannot be executed.');
        }

        // Dispatch the job (one job that processes all students)
        Bus::batch([
            new ProcessStudentPromotion($batch)
        ])
        ->name("Promotion: {$batch->name}")
        ->then(function () use ($batch) {
            $batch->update(['status' => 'completed', 'executed_at' => now()]);
        })
        ->catch(function () use ($batch) {
            $batch->update(['status' => 'failed']);
        })
        ->dispatch();

        $batch->update(['status' => 'executing']);

        return back()->with('success', 'Promotion execution started! You will be notified when complete.');
    }

    /**
     * Bulk override decisions (Vue sends array of IDs + decision)
     */
    public function bulkOverride(Request $request, PromotionBatch $batch)
    {
        Gate::authorize('approve', $batch);

        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:promotion_students,id',
            'decision' => 'required|in:promote,repeat,probation,graduated',
            'reason' => 'nullable|string|max:500',
        ]);

        PromotionStudent::where('promotion_batch_id', $batch->id)
            ->whereIn('id', $request->student_ids)
            ->update([
                'final_decision' => $request->decision,
                'override_reason' => $request->reason,
                'overridden_by' => auth()->id(),
            ]);

        return back()->with('success', 'Bulk override applied successfully.');
    }
}