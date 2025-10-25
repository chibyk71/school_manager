<?php

namespace App\Http\Controllers\Settings\School\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\MailTemplates\Models\MailTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing email template settings for schools.
 */
class TemplateController extends Controller
{
    /**
     * Display a listing of the mail templates.
     *
     * @param Request $request
     * @return \Inertia\Response
     *
     * @throws \Exception If template retrieval fails.
     */
    public function index()
    {
        try {
            $schoolId = GetSchoolModel()?->id;
            $mailTemplates = MailTemplate::whereIn('school_id', [$schoolId, null])
                ->orderBy('school_id', 'desc') // Prioritize school-specific templates
                ->get()
                ->groupBy('mailable');

            return Inertia::render('Settings/System/EmailTemplate', ['mailTemplates' => $mailTemplates]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch mail templates: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load mail templates.');
        }
    }

    /**
     * Store a newly created mail template in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If template save fails.
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('manage-email-templates'); // Add authorization check

            $validated = $request->validate([
                'mailable' => 'required|string',
                'subject' => 'required|string',
                'body' => 'required|string',
            ]);

            $schoolId = GetSchoolModel()?->id;
            $mailTemplateModel = MailTemplate::firstOrCreate(
                [
                    'mailable' => $validated['mailable'],
                    'school_id' => $schoolId,
                ],
                [
                    'subject' => $validated['subject'],
                    'body' => $validated['body'],
                ]
            );

            return response()->json([
                'success' => true,
                'data' => ['mailTemplate' => $mailTemplateModel],
                'message' => 'Mail template saved successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save mail template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save mail template'], 500);
        }
    }

    /**
     * Display the specified mail template.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If template retrieval fails.
     */
    public function show(Request $request)
    {
        try {
            $this->authorize('manage-email-templates'); // Add authorization check

            $validated = $request->validate([
                'mailable' => 'required|string',
            ]);

            $schoolId = GetSchoolModel()?->id;
            $mailTemplateModel = MailTemplate::where('mailable', $validated['mailable'])
                ->whereIn('school_id', [$schoolId, null])
                ->orderBy('school_id', 'desc')
                ->first();

            if (!$mailTemplateModel) {
                return response()->json(['success' => false, 'error' => 'Mail template not found'], 404);
            }

            $mergeTags = $mailTemplateModel->getVariables();

            return response()->json([
                'success' => true,
                'data' => ['mailTemplate' => $mailTemplateModel, 'mergeTags' => $mergeTags],
                'message' => 'Mail template fetched successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to fetch mail template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch mail template'], 500);
        }
    }

    /**
     * Remove the specified mail template from storage.
     *
     * @param MailTemplate $mailTemplate
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception If template deletion fails.
     */
    public function destroy(MailTemplate $mailTemplate)
    {
        try {
            $this->authorize('manage-email-templates'); // Add authorization check

            $schoolId = GetSchoolModel()?->id;
            $mailTemplateModel = MailTemplate::where('mailable', $mailTemplate->mailable)
                ->where('school_id', $schoolId)
                ->first();

            if ($mailTemplateModel) {
                $mailTemplateModel->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Mail template deleted successfully',
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Mail template not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete mail template: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete mail template'], 500);
        }
    }
}
