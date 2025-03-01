<?php

namespace App\Http\Controllers\Settings\School\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MailTemplates\Models\MailTemplate;
// TODO add authorization policies to check for permission and roles
class TemplateController extends Controller
{
    /**
     * Display a listing of the Mail Templates.
     * there are mails with school_id, that is a customised version for that school,
     * so we have to group the mails by the mailable column to get distinct mails
     * and then we have to check if there is a customised version for the school
     * if there is, we have to show that, if not, we have to show the default one
     *
     * @return Response
     */
    public function index(): Response
    {
        $schoolId = GetSchoolModel()?->id;
        $mailTemplates = MailTemplate::whereIn('school_id', [$schoolId, null])
            ->orderBy('school_id')
            ->get()
            ->groupBy('mailable');

        return Inertia::render('Settings/System/EmailTemplate', ['mailTemplates' => $mailTemplates]);
    }

    /**
     * Store a newly created Mail Template in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        //fetch the mail template from the request input
        $validated = $request->validate([
            'mailable' => 'required|string',
            'subject' => 'required|string',
            'body' => 'required|string',
        ]);

        //check if the mail template is customised for the school and create if not
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
            'message' => 'Mail template saved successfully'
        ]);
    }

    /**
     * Display the specified resource.
     * this method receives the mailable name and returns the mail template for that mail
     * if there is a customised version for the school, it returns that, if not, it returns the default one
     *
     * @param  MailTemplate $mailTemplate
     * @return \Illuminate\Http\JsonResponse
     */
    public function show()
    {
        //fetch the mail template from the request input
        $validated = request()->validate([
            'mailable' => 'required|string',
        ]);

        //check if the mail template is customised for the school and return that if it is
        $schoolId = GetSchoolModel()?->id;
        $mailTemplateModel = MailTemplate::where('mailable', $validated['mailable'])
            ->where('school_id', $schoolId)
            ->orWhereNull('school_id')
            ->orderBy('school_id')
            ->first();

        $mergeTags = $mailTemplateModel?->getVariables();
        

        return response()->json([
            'success' => true,
            'data' => ['mailTemplate' => $mailTemplateModel, 'mergeTags'=>$mergeTags],
            'message' => 'Mail template fetched successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * This method will be used to reset to the default setting
     */
    public function destroy(MailTemplate $mailTemplate)
    {
        //check if the mail template is customised for the school and delete that if it is
        $schoolId = GetSchoolModel()?->id;
        $mailTemplateModel = MailTemplate::where('mailable', $mailTemplate->mailable)
            ->where('school_id', $schoolId)
            ->first();

        if ($mailTemplateModel) {
            $mailTemplateModel->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mail template deleted successfully'
        ]);
    }
}
