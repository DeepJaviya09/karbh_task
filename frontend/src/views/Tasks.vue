<template>
  <div class="tasks-page">
    <div class="page-header">
      <h1>My Tasks</h1>
      <router-link to="/tasks/create" class="btn btn-primary">
        Create New Task
      </router-link>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section card">
      <div class="filters-row">
        <div class="search-box">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search tasks..."
            class="form-control"
            @input="debouncedSearch"
          >
        </div>

        <div class="filter-group">
          <select v-model="filters.status" @change="applyFilters" class="form-control">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <div class="filter-group">
          <select v-model="filters.sort_by" @change="applyFilters" class="form-control">
            <option value="created_at">Sort by Created</option>
            <option value="due_date">Sort by Due Date</option>
            <option value="title">Sort by Title</option>
            <option value="status">Sort by Status</option>
          </select>
        </div>

        <div class="filter-group">
          <select v-model="filters.sort_order" @change="applyFilters" class="form-control">
            <option value="desc">Descending</option>
            <option value="asc">Ascending</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading tasks...</p>
    </div>

    <!-- Tasks list -->
    <div v-else-if="tasks.length > 0" class="tasks-list">
      <div
        v-for="task in tasks"
        :key="task.id"
        class="task-item card"
        :class="{
          'overdue': isOverdue(task),
          'completed': task.status === 'completed'
        }"
      >
        <div class="task-header">
          <h3>
            <router-link :to="`/tasks/${task.id}`" class="task-title-link">
              {{ task.title }}
            </router-link>
          </h3>
          <div class="task-actions">
            <router-link 
              :to="`/tasks/${task.id}/edit`" 
              class="btn btn-secondary btn-sm"
            >
              Edit
            </router-link>
            <button 
              @click="deleteTask(task.id)" 
              class="btn btn-danger btn-sm"
              :disabled="isDeleting === task.id"
            >
              {{ isDeleting === task.id ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>

        <p v-if="task.description" class="task-description">
          {{ task.description }}
        </p>

        <div class="task-meta">
          <span class="task-status" :class="task.status">
            {{ formatStatus(task.status) }}
          </span>
          
          <span v-if="task.due_date" class="task-due-date">
            Due: {{ formatDate(task.due_date) }}
          </span>

          <span class="task-created">
            Created: {{ formatDate(task.created_at) }}
          </span>
        </div>

        <div v-if="task.tags && task.tags.length > 0" class="task-tags">
          <span
            v-for="tag in task.tags"
            :key="tag.id"
            class="task-tag"
          >
            {{ tag.name }}
          </span>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="empty-state card">
      <h3>No tasks found</h3>
      <p>{{ filters.search || filters.status ? 'Try adjusting your filters' : 'Create your first task to get started!' }}</p>
      <router-link to="/tasks/create" class="btn btn-primary">
        Create Your First Task
      </router-link>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="pagination">
      <button
        @click="changePage(pagination.current_page - 1)"
        :disabled="pagination.current_page === 1"
        class="btn btn-secondary"
      >
        Previous
      </button>
      
      <span class="pagination-info">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
      </span>
      
      <button
        @click="changePage(pagination.current_page + 1)"
        :disabled="pagination.current_page === pagination.last_page"
        class="btn btn-secondary"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'

export default {
  name: 'Tasks',
  setup() {
    const router = useRouter()
    const { showToast } = useToast()
    
    const tasks = ref([])
    const pagination = ref(null)
    const isLoading = ref(false)
    const isDeleting = ref(null)
    
    const filters = reactive({
      search: '',
      status: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      page: 1
    })

    let searchTimeout = null

    const fetchTasks = async () => {
      try {
        isLoading.value = true
        
        const params = new URLSearchParams()
        Object.entries(filters).forEach(([key, value]) => {
          if (value) params.append(key, value)
        })
        
        const response = await api.get(`/tasks?${params}`)
        tasks.value = response.data.tasks
        pagination.value = response.data.pagination
      } catch (error) {
        console.error('Failed to fetch tasks:', error)
        showToast('Failed to load tasks', 'error')
      } finally {
        isLoading.value = false
      }
    }

    const debouncedSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        filters.page = 1
        fetchTasks()
      }, 300)
    }

    const applyFilters = () => {
      filters.page = 1
      fetchTasks()
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        filters.page = page
        fetchTasks()
      }
    }

    const deleteTask = async (taskId) => {
      if (!confirm('Are you sure you want to delete this task?')) return
      
      try {
        isDeleting.value = taskId
        await api.delete(`/tasks/${taskId}`)
        showToast('Task deleted successfully', 'success')
        fetchTasks()
      } catch (error) {
        console.error('Failed to delete task:', error)
        showToast('Failed to delete task', 'error')
      } finally {
        isDeleting.value = null
      }
    }

    const formatStatus = (status) => {
      return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString()
    }

    const isOverdue = (task) => {
      if (!task.due_date || task.status === 'completed') return false
      return new Date(task.due_date) < new Date()
    }

    onMounted(() => {
      fetchTasks()
    })

    return {
      tasks,
      pagination,
      isLoading,
      isDeleting,
      filters,
      fetchTasks,
      debouncedSearch,
      applyFilters,
      changePage,
      deleteTask,
      formatStatus,
      formatDate,
      isOverdue
    }
  }
}
</script>

<style scoped>
.tasks-page {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.page-header h1 {
  color: #2c3e50;
  margin: 0;
}

.filters-section {
  margin-bottom: 2rem;
}

.filters-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 1rem;
  align-items: center;
}

.search-box {
  position: relative;
}

.filter-group select {
  width: 100%;
}

.loading-state {
  text-align: center;
  padding: 3rem;
}

.loading-state .spinner {
  margin: 0 auto 1rem;
}

.tasks-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.task-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.task-title-link {
  color: #2c3e50;
  text-decoration: none;
  font-weight: 600;
}

.task-title-link:hover {
  color: #3498db;
  text-decoration: underline;
}

.task-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
}

.task-description {
  color: #7f8c8d;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.task-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.task-due-date {
  color: #e67e22;
}

.task-created {
  color: #95a5a6;
}

.empty-state {
  text-align: center;
  padding: 3rem;
}

.empty-state h3 {
  color: #7f8c8d;
  margin-bottom: 1rem;
}

.empty-state p {
  color: #95a5a6;
  margin-bottom: 2rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}

.pagination-info {
  color: #7f8c8d;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .filters-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .task-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .task-actions {
    justify-content: flex-end;
  }

  .task-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>

