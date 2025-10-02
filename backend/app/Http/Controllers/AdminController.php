<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller
{
    /**
     * Get all users (admin only).
     */
    public function users(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = User::withCount('tasks');

        // Search by name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        // Filter by email verification status
        if ($request->has('verified') && $request->verified !== '') {
            if ($request->verified === '1') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['created_at', 'name', 'email', 'tasks_count'])) {
            if ($sortBy === 'tasks_count') {
                $query->orderBy('tasks_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        $perPage = min($request->get('per_page', 15), 50);
        $users = $query->paginate($perPage);

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get all tasks across all users (admin only).
     */
    public function allTasks(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = Task::with(['tags', 'user:id,name,email']);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->status($request->status);
        }

        // Filter by user
        if ($request->has('user_id') && $request->user_id) {
            $query->forUser($request->user_id);
        }

        // Filter by due date range
        if ($request->has('due_date_from') && $request->has('due_date_to')) {
            $query->dueDateRange($request->due_date_from, $request->due_date_to);
        }

        // Filter by tags
        if ($request->has('tags') && is_array($request->tags)) {
            $query->withTags($request->tags);
        }

        // Filter by due date status (overdue, today, this_week, no_due_date)
        if ($request->has('due_date_filter') && $request->due_date_filter) {
            $today = now()->format('Y-m-d');
            $endOfWeek = now()->endOfWeek()->format('Y-m-d');
            
            switch ($request->due_date_filter) {
                case 'overdue':
                    $query->where('due_date', '<', $today)
                          ->where('status', '!=', 'completed');
                    break;
                case 'today':
                    $query->whereDate('due_date', $today);
                    break;
                case 'this_week':
                    $query->whereBetween('due_date', [$today, $endOfWeek]);
                    break;
                case 'no_due_date':
                    $query->whereNull('due_date');
                    break;
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['created_at', 'due_date', 'title', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->get('per_page', 15), 50);
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
     * Get dashboard statistics (admin only).
     */
    public function dashboard(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $totalUsers = User::count();
        $totalTasks = Task::count();
        $completedTasks = Task::where('status', 'completed')->count();
        $pendingTasks = Task::where('status', 'pending')->count();
        $inProgressTasks = Task::where('status', 'in_progress')->count();
        $overdueTasks = Task::where('due_date', '<', now())->where('status', '!=', 'completed')->count();

        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'email', 'created_at']);
        $recentTasks = Task::with(['user:id,name,email', 'tags'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'statistics' => [
                'total_users' => $totalUsers,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $pendingTasks,
                'in_progress_tasks' => $inProgressTasks,
                'overdue_tasks' => $overdueTasks,
            ],
            'recent_users' => $recentUsers,
            'recent_tasks' => $recentTasks,
        ], Response::HTTP_OK);
    }
}
