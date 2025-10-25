<?php

namespace App\Http\Controllers;

use App\Models\Resource\BookList;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Notifications\BookListAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing book lists in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for book lists, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class BookListController extends Controller
{
    /**
     * Display a listing of book lists with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/BookLists Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', BookList::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'classLevel' => ['field' => 'class_level.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'subject' => ['field' => 'subject.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = BookList::with([
                'classLevel:id,name',
                'subject:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $bookLists = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($bookLists);
            }

            return Inertia::render('Academic/BookLists', [
                'bookLists' => $bookLists,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch book lists: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch book lists'], 500)
                : redirect()->back()->with('error', 'Failed to load book lists.');
        }
    }

    /**
     * Show the form for creating a new book list entry.
     *
     * Renders the Academic/BookListCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', BookList::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/BookListCreate', [
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load book list creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load book list creation form.');
        }
    }

    /**
     * Store a newly created book list entry in storage.
     *
     * Validates the input, creates the book list, attaches media, and sends notifications.
     *
     * @param Request $request The HTTP request containing book list data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', BookList::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'isbn' => 'nullable|string|max:13',
                'edition' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,png|max:2048',
            ])->validate();

            // Create the book list entry
            $bookList = BookList::create([
                'school_id' => $school->id,
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'title' => $validated['title'],
                'author' => $validated['author'],
                'isbn' => $validated['isbn'],
                'edition' => $validated['edition'],
                'description' => $validated['description'],
                'price' => $validated['price'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $bookList->addMedia($file)->toMediaCollection('book_covers');
                }
            }

            // Notify admins and teachers
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new BookListAction($bookList, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Book list entry created successfully'], 201)
                : redirect()->route('book-lists.index')->with('success', 'Book list entry created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create book list entry: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create book list entry'], 500)
                : redirect()->back()->with('error', 'Failed to create book list entry.');
        }
    }

    /**
     * Display the specified book list entry.
     *
     * Loads the book list with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param BookList $bookList The book list entry to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or book list is not accessible.
     */
    public function show(Request $request, BookList $bookList)
    {
        Gate::authorize('view', $bookList);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookList->school_id !== $school->id) {
                throw new \Exception('Book list entry not found or not accessible.');
            }

            $bookList->load(['classLevel', 'subject', 'media']);

            return response()->json(['book_list' => $bookList]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch book list entry: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch book list entry'], 500);
        }
    }

    /**
     * Show the form for editing the specified book list entry.
     *
     * Renders the Academic/BookListEdit Vue component.
     *
     * @param BookList $bookList The book list entry to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or book list is not accessible.
     */
    public function edit(BookList $bookList)
    {
        Gate::authorize('update', $bookList);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookList->school_id !== $school->id) {
                throw new \Exception('Book list entry not found or not accessible.');
            }

            $bookList->load(['classLevel', 'subject', 'media']);

            return Inertia::render('Academic/BookListEdit', [
                'bookList' => $bookList,
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load book list edit form: ' . $e->getMessage());
            return redirect()->route('book-lists.index')->with('error', 'Failed to load book list edit form.');
        }
    }

    /**
     * Update the specified book list entry in storage.
     *
     * Validates the input, updates the book list, syncs media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated book list data.
     * @param BookList $bookList The book list entry to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, BookList $bookList)
    {
        Gate::authorize('update', $bookList);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookList->school_id !== $school->id) {
                throw new \Exception('Book list entry not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'isbn' => 'nullable|string|max:13',
                'edition' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,png|max:2048',
            ])->validate();

            // Update the book list entry
            $bookList->update([
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'title' => $validated['title'],
                'author' => $validated['author'],
                'isbn' => $validated['isbn'],
                'edition' => $validated['edition'],
                'description' => $validated['description'],
                'price' => $validated['price'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $bookList->clearMediaCollection('book_covers');
                foreach ($request->file('media') as $file) {
                    $bookList->addMedia($file)->toMediaCollection('book_covers');
                }
            }

            // Notify admins and teachers
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new BookListAction($bookList, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Book list entry updated successfully'])
                : redirect()->route('book-lists.index')->with('success', 'Book list entry updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update book list entry: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update book list entry'], 500)
                : redirect()->back()->with('error', 'Failed to update book list entry.');
        }
    }

    /**
     * Remove one or more book list entries from storage (soft or force delete).
     *
     * Accepts an array of book list IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of book list IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or book lists are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', BookList::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:book_lists,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $bookLists = BookList::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($bookLists as $bookList) {
                Notification::send($users, new BookListAction($bookList, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = BookList::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted book list entries deleted successfully" : "No book list entries were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('book-lists.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete book list entries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete book list entries'], 500)
                : redirect()->back()->with('error', 'Failed to delete book list entries.');
        }
    }

    /**
     * Restore one or more soft-deleted book list entries.
     *
     * Accepts an array of book list IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of book list IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or book lists are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', BookList::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:book_lists,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $bookLists = BookList::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($bookLists as $bookList) {
                Notification::send($users, new BookListAction($bookList, 'restored'));
            }

            // Restore the book lists
            $count = BookList::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count book list entries restored successfully" : "No book list entries were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('book-lists.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore book list entries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore book list entries'], 500)
                : redirect()->back()->with('error', 'Failed to restore book list entries.');
        }
    }
}
