<?php

namespace App\Http\Controllers\Settings\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Services\Academic\AcademicSessionLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SessionActivationController – Phase 2 explicit lifecycle operations.
 *
 * Controllers stay thin: authorize → invoke domain operation → respond.
 * Business rules live in AcademicSessionLifecycleService.
 */
class SessionActivationController extends Controller
{
    public function __construct(
        protected AcademicSessionLifecycleService $lifecycle
    ) {
    }

    public function plan(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->plan($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been planned.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to plan academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to plan academic session.');
        }
    }

    public function activate(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->activate($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been activated and is now current.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to activate academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to activate academic session. ' . $e->getMessage());
        }
    }

    public function pause(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->pause($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been paused (still current).");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to pause academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to pause academic session.');
        }
    }

    public function resume(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->resume($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been resumed.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to resume academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to resume academic session.');
        }
    }

    public function close(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->close($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been closed.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to close academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to close academic session.');
        }
    }

    public function reopen(Request $request, AcademicSession $academicSession)
    {
        Gate::authorize('update', $academicSession);

        try {
            $this->lifecycle->reopen($academicSession);

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', "Academic session '{$academicSession->name}' has been reopened.");
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Exception $e) {
            Log::error('Failed to reopen academic session', [
                'error' => $e->getMessage(),
                'session_id' => $academicSession->id,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to reopen academic session.');
        }
    }
}
