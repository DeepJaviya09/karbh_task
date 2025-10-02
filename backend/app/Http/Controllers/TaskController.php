<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Task::with('tags');

        // If user is not admin, only show their tasks
        if (!$user->isAdmin()) {
            $query->forUser($user->id);
        }

        // Search by title, status, tags
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->status($request->status);
        }

        // Filter by due date range
        if ($request->has('due_date_from') && $request->has('due_date_to')) {
            $query->dueDateRange($request->due_date_from, $request->due_date_to);
        }

        // Filter by tags
        if ($request->has('tags') && is_array($request->tags)) {
            $query->withTags($request->tags);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['created_at', 'due_date', 'title', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 50); // Max 50 items per page
        $tasks = $query->paginate($perPage);

        return response()->json([
            'tasks' => $tasks->items(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:pending,in_progress,completed',
            'due_date' => 'nullable|date|after_or_equal:today',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $task = Task::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'description' => $request->description,
                'status' => $request->status ?? 'pending',
                'due_date' => $request->due_date,
            ]);

            // Add tags if provided
            if ($request->has('tags') && is_array($request->tags)) {
                foreach ($request->tags as $tagName) {
                    Tag::create([
                        'task_id' => $task->id,
                        'name' => trim($tagName),
                    ]);
                }
            }

            $task->load('tags');
            
            DB::commit();

            return response()->json([
                'message' => 'Task created successfully',
                'task' => $task,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $task = Task::with('tags', 'user')->find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the task or is admin
        if (!$user->isAdmin() && $task->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view this task',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'task' => $task,
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the task or is admin
        if (!$user->isAdmin() && $task->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to update this task',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'due_date' => 'sometimes|nullable|date|after_or_equal:today',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            // Update task fields (only provided ones)
            $updateData = array_filter([
                'title' => $request->title,
                'description' => $request->description,
                'status' => $request->status,
                'due_date' => $request->due_date,
            ], function ($value) {
                return $value !== null;
            });

            $task->update($updateData);

            // Update tags if provided
            if ($request->has('tags')) {
                // Delete existing tags
                $task->tags()->delete();
                
                // Add new tags
                if (is_array($request->tags)) {
                    foreach ($request->tags as $tagName) {
                        Tag::create([
                            'task_id' => $task->id,
                            'name' => trim($tagName),
                        ]);
                    }
                }
            }

            $task->load('tags');
            
            DB::commit();

            return response()->json([
                'message' => 'Task updated successfully',
                'task' => $task,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns the task or is admin
        if (!$user->isAdmin() && $task->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this task',
            ], Response::HTTP_FORBIDDEN);
        }

        $task->delete(); // This will also delete tags due to cascade

        return response()->json([
            'message' => 'Task deleted successfully',
        ], Response::HTTP_OK);
    }
}
