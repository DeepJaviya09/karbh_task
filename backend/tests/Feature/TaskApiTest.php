<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_user_can_create_task(): void
    {
        $taskData = [
            'title' => 'Test Task',
            'description' => 'This is a test task',
            'status' => 'pending',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'tags' => ['urgent', 'work'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'task' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'due_date',
                    'created_at',
                    'updated_at',
                    'tags' => [
                        '*' => ['id', 'name', 'task_id', 'created_at', 'updated_at']
                    ]
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'urgent',
        ]);
    }

    public function test_user_can_list_their_tasks(): void
    {
        // Create tasks for the user
        Task::factory(3)->create(['user_id' => $this->user->id]);
        
        // Create task for another user (should not be visible)
        $otherUser = User::factory()->create();
        Task::factory(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tasks' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'due_date',
                        'created_at',
                        'updated_at',
                        'tags'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Should only see their own tasks (3)
        $this->assertEquals(3, count($response->json('tasks')));
    }

    public function test_user_can_update_their_task(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => 'completed',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Task updated successfully',
                'task' => [
                    'id' => $task->id,
                    'title' => 'Updated Title',
                    'status' => 'completed',
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'status' => 'completed',
        ]);
    }

    public function test_user_cannot_update_other_users_task(): void
    {
        $otherUser = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $otherUser->id]);

        $updateData = [
            'title' => 'Hacked Title',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized to update this task',
            ]);
    }

    public function test_user_can_delete_their_task(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Task deleted successfully',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_admin_can_see_all_tasks(): void
    {
        // Create tasks for different users
        Task::factory(2)->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        Task::factory(3)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertStatus(200);

        // Admin should see all tasks (5 total)
        $this->assertEquals(5, count($response->json('tasks')));
    }

    public function test_task_search_functionality(): void
    {
        Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Important Meeting',
            'description' => 'Team standup meeting',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Code Review',
            'description' => 'Review pull request',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tasks?search=meeting');

        $response->assertStatus(200);
        
        $tasks = $response->json('tasks');
        $this->assertEquals(1, count($tasks));
        $this->assertStringContainsString('Meeting', $tasks[0]['title']);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $response = $this->getJson('/api/tasks');

        $response->assertStatus(401);
    }
}
