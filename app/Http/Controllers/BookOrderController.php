<?php

namespace App\Http\Controllers;

use App\Models\Resource\BookList;
use App\Models\Resource\BookOrder;
use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Notifications\BookOrderAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing book orders in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for book orders, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class BookOrderController extends Controller
{
    /**
     * Display a listing of book orders with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/BookOrders Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', BookOrder::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'book' => ['field' => 'book.title', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'student' => ['field' => 'student.full_name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = BookOrder::with([
                'book:id,title',
                'student:id,first_name,last_name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $bookOrders = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($bookOrders);
            }

            return Inertia::render('Academic/BookOrders', [
                'bookOrders' => $bookOrders,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'books' => BookList::where('school_id', $school->id)->select('id', 'title')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch book orders: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch book orders'], 500)
                : redirect()->back()->with('error', 'Failed to load book orders.');
        }
    }

    /**
     * Show the form for creating a new book order.
     *
     * Renders the Academic/BookOrderCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', BookOrder::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/BookOrderCreate', [
                'books' => BookList::where('school_id', $school->id)->select('id', 'title')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load book order creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load book order creation form.');
        }
    }

    /**
     * Store a newly created book order in storage.
     *
     * Validates the input, creates the book order, and sends notifications.
     *
     * @param Request $request The HTTP request containing book order data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', BookOrder::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'book_list_id' => 'required|exists:book_lists,id,school_id,' . $school->id,
                'student_id' => 'required|exists:students,id,school_id,' . $school->id,
                'order_date' => 'required|date',
                'return_date' => 'nullable|date|after_or_equal:order_date',
                'status' => 'required|in:pending,approved,rejected,returned',
            ])->validate();

            // Create the book order
            $bookOrder = BookOrder::create([
                'school_id' => $school->id,
                'book_list_id' => $validated['book_list_id'],
                'student_id' => $validated['student_id'],
                'order_date' => $validated['order_date'],
                'return_date' => $validated['return_date'],
                'status' => $validated['status'],
            ]);

            // Notify student and staff (admin/teacher roles)
            $bookOrder->student->notify(new BookOrderAction($bookOrder, 'created'));
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new BookOrderAction($bookOrder, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Book order created successfully'], 201)
                : redirect()->route('book-orders.index')->with('success', 'Book order created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create book order: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create book order'], 500)
                : redirect()->back()->with('error', 'Failed to create book order.');
        }
    }

    /**
     * Display the specified book order.
     *
     * Loads the book order with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param BookOrder $bookOrder The book order to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or book order is not accessible.
     */
    public function show(Request $request, BookOrder $bookOrder)
    {
        Gate::authorize('view', $bookOrder);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookOrder->school_id !== $school->id) {
                throw new \Exception('Book order not found or not accessible.');
            }

            $bookOrder->load(['book', 'student']);

            return response()->json(['book_order' => $bookOrder]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch book order: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch book order'], 500);
        }
    }

    /**
     * Show the form for editing the specified book order.
     *
     * Renders the Academic/BookOrderEdit Vue component.
     *
     * @param BookOrder $bookOrder The book order to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or book order is not accessible.
     */
    public function edit(BookOrder $bookOrder)
    {
        Gate::authorize('update', $bookOrder);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookOrder->school_id !== $school->id) {
                throw new \Exception('Book order not found or not accessible.');
            }

            $bookOrder->load(['book', 'student']);

            return Inertia::render('Academic/BookOrderEdit', [
                'bookOrder' => $bookOrder,
                'books' => BookList::where('school_id', $school->id)->select('id', 'title')->get(),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load book order edit form: ' . $e->getMessage());
            return redirect()->route('book-orders.index')->with('error', 'Failed to load book order edit form.');
        }
    }

    /**
     * Update the specified book order in storage.
     *
     * Validates the input, updates the book order, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated book order data.
     * @param BookOrder $bookOrder The book order to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, BookOrder $bookOrder)
    {
        Gate::authorize('update', $bookOrder);

        try {
            $school = GetSchoolModel();
            if (!$school || $bookOrder->school_id !== $school->id) {
                throw new \Exception('Book order not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'book_list_id' => 'required|exists:book_lists,id,school_id,' . $school->id,
                'student_id' => 'required|exists:students,id,school_id,' . $school->id,
                'order_date' => 'required|date',
                'return_date' => 'nullable|date|after_or_equal:order_date',
                'status' => 'required|in:pending,approved,rejected,returned',
            ])->validate();

            // Update the book order
            $bookOrder->update([
                'book_list_id' => $validated['book_list_id'],
                'student_id' => $validated['student_id'],
                'order_date' => $validated['order_date'],
                'return_date' => $validated['return_date'],
                'status' => $validated['status'],
            ]);

            // Notify student and staff
            $bookOrder->student->notify(new BookOrderAction($bookOrder, 'updated'));
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new BookOrderAction($bookOrder, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Book order updated successfully'])
                : redirect()->route('book-orders.index')->with('success', 'Book order updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update book order: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update book order'], 500)
                : redirect()->back()->with('error', 'Failed to update book order.');
        }
    }

    /**
     * Remove one or more book orders from storage (soft or force delete).
     *
     * Accepts an array of book order IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of book order IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or book orders are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', BookOrder::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:book_orders,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $bookOrders = BookOrder::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($bookOrders as $bookOrder) {
                $bookOrder->student->notify(new BookOrderAction($bookOrder, 'deleted'));
                Notification::send($users, new BookOrderAction($bookOrder, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = BookOrder::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted book order(s) deleted successfully" : "No book orders were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('book-orders.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete book orders: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete book order(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete book order(s).');
        }
    }

    /**
     * Restore one or more soft-deleted book orders.
     *
     * Accepts an array of book order IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of book order IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or book orders are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', BookOrder::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:book_orders,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $bookOrders = BookOrder::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($bookOrders as $bookOrder) {
                $bookOrder->student->notify(new BookOrderAction($bookOrder, 'restored'));
                Notification::send($users, new BookOrderAction($bookOrder, 'restored'));
            }

            // Restore the book orders
            $count = BookOrder::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count book order(s) restored successfully" : "No book orders were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('book-orders.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore book orders: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore book order(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore book order(s).');
        }
    }
}
