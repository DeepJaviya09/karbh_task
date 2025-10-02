<template>
  <div class="user-tasks-page">
    <!-- Loading state -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading user tasks...</p>
    </div>

    <!-- Main content -->
    <div v-else>
      <!-- Page header -->
      <div class="page-header">
        <div class="header-content">
          <router-link to="/users" class="back-btn">
            ← Back to Users
          </router-link>
          
          <div v-if="user" class="user-info">
            <div class="user-avatar">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="user-details">
              <h1>{{ user.name }}'s Tasks</h1>
              <p>{{ user.email }}</p>
              <span class="user-role" :class="user.role">{{ user.role }}</span>
            </div>
          </div>
        </div>

        <!-- Task summary -->
        <div v-if="tasks.length > 0" class="task-summary">
          <div class="summary-item">
            <span class="summary-number">{{ tasks.length }}</span>
            <span class="summary-label">Showing Tasks</span>
          </div>
          <div class="summary-item">
            <span class="summary-number">{{ completedTasks }}</span>
            <span class="summary-label">Completed</span>
          </div>
          <div class="summary-item">
            <span class="summary-number">{{ pendingTasks }}</span>
            <span class="summary-label">Pending</span>
          </div>
          <div class="summary-item">
            <span class="summary-number">{{ overdueTasks }}</span>
            <span class="summary-label">Overdue</span>
          </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
          <!-- Search bar -->
          <div class="search-bar">
            <input
              ref="searchInput"
              v-model="filters.search"
              @input="debouncedSearch"
              type="text"
              placeholder="Search tasks by title or description..."
              class="search-input"
            >
          </div>

          <!-- Filter controls -->
          <div class="filter-controls">
            <!-- Status filter -->
            <select v-model="filters.status" @change="applyFilters" class="filter-select">
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
            </select>

            <!-- Due date filter -->
            <select v-model="filters.due_date_filter" @change="applyFilters" class="filter-select">
              <option value="">All Due Dates</option>
              <option value="overdue">Overdue</option>
              <option value="today">Due Today</option>
              <option value="this_week">Due This Week</option>
              <option value="no_due_date">No Due Date</option>
            </select>

            <!-- Sort options -->
            <select v-model="filters.sort_by" @change="applyFilters" class="filter-select">
              <option value="created_at">Sort by Created Date</option>
              <option value="due_date">Sort by Due Date</option>
              <option value="title">Sort by Title</option>
              <option value="status">Sort by Status</option>
            </select>

            <!-- Sort order -->
            <button 
              @click="toggleSortOrder" 
              class="sort-order-btn"
              :title="filters.sort_order === 'desc' ? 'Descending' : 'Ascending'"
            >
              {{ filters.sort_order === 'desc' ? '↓' : '↑' }}
            </button>

            <!-- Clear filters -->
            <button @click="clearFilters" class="clear-filters-btn">
              Clear Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Tasks list -->
      <div v-if="tasks.length > 0" class="tasks-container">
        <div
          v-for="task in tasks"
          :key="task.id"
          class="task-card"
          :class="{ 'overdue': isOverdue(task), 'completed': task.status === 'completed' }"
        >
          <div class="task-header">
            <h3>{{ task.title }}</h3>
            <span class="task-status" :class="task.status">
              {{ formatStatus(task.status) }}
            </span>
          </div>

          <p v-if="task.description" class="task-description">
            {{ task.description }}
          </p>

          <div class="task-meta">
            <div class="meta-row">
              <span v-if="task.due_date" class="due-date">
                <strong>Due:</strong> {{ formatDate(task.due_date) }}
              </span>
              <span class="created-date">
                <strong>Created:</strong> {{ formatDate(task.created_at) }}
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

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="pagination">
          <button 
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="pagination-btn"
          >
            Previous
          </button>
          
          <div class="pagination-info">
            Page {{ pagination.current_page }} of {{ pagination.last_page }}
            ({{ pagination.total }} total tasks)
          </div>
          
          <button 
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="pagination-btn"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Empty state: show contextual message depending on filters -->
      <div v-else class="empty-state">
        <div v-if="filters.search || filters.status || filters.due_date_filter">
          <div class="empty-icon">🔍</div>
          <h3>No Tasks Match Your Filters</h3>
          <p>Try adjusting your search or filter criteria.</p>
          <button @click="clearFilters" class="btn btn-primary">
            Clear Filters
          </button>
        </div>
        <div v-else>
          <div class="empty-icon">📝</div>
          <h3>No Tasks Found</h3>
          <p v-if="user">{{ user.name }} hasn't created any tasks yet.</p>
          <router-link to="/users" class="btn btn-primary">
            Back to Users
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'

export default {
  name: 'UserTasks',
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { showToast } = useToast()
    
    const user = ref(null)
    const tasks = ref([])
    const pagination = ref(null)
    const isLoading = ref(true)
    const searchInput = ref(null)

    // Filter and search states
    const filters = reactive({
      search: '',
      status: '',
      due_date_filter: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      page: 1,
      per_page: 10,
      user_id: route.params.id
    })

    let searchTimeout = null

    // Computed properties for task stats
    const completedTasks = computed(() => 
      tasks.value.filter(task => task.status === 'completed').length
    )

    const pendingTasks = computed(() => 
      tasks.value.filter(task => task.status === 'pending').length
    )

    const overdueTasks = computed(() => 
      tasks.value.filter(task => isOverdue(task)).length
    )

    const fetchUserTasks = async () => {
      try {
        isLoading.value = true
        
        const params = new URLSearchParams()
        Object.entries(filters).forEach(([key, value]) => {
          if (value) params.append(key, value)
        })
        
        // Fetch both user info and tasks
        const [userResponse, tasksResponse] = await Promise.all([
          api.get(`/admin/users?per_page=50`), // Get users to find the specific one
          api.get(`/admin/tasks?${params}`)
        ])

        // Find the specific user
        user.value = userResponse.data.users.find(u => u.id == route.params.id)
        tasks.value = tasksResponse.data.tasks
        pagination.value = tasksResponse.data.pagination

        if (!user.value) {
          throw new Error('User not found')
        }
      } catch (error) {
        console.error('Failed to fetch user tasks:', error)
        showToast('Failed to load user tasks', 'error')
        router.push('/users')
      } finally {
        isLoading.value = false
        // Focus search after data is ready
        await nextTick()
        if (searchInput.value) {
          searchInput.value.focus()
          searchInput.value.select()
        }
      }
    }

    const debouncedSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        // Reset to first page on search and temporarily increase page size
        filters.page = 1
        filters.per_page = filters.search && filters.search.trim() ? 25 : 10
        fetchUserTasks()
      }, 300)
    }

    const applyFilters = () => {
      filters.page = 1
      fetchUserTasks()
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        filters.page = page
        fetchUserTasks()
      }
    }

    const formatStatus = (status) => {
      return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const isOverdue = (task) => {
      if (!task.due_date || task.status === 'completed') return false
      return new Date(task.due_date) < new Date()
    }

    const toggleSortOrder = () => {
      filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc'
      applyFilters()
    }

    const clearFilters = () => {
      filters.search = ''
      filters.status = ''
      filters.due_date_filter = ''
      filters.sort_by = 'created_at'
      filters.sort_order = 'desc'
      filters.page = 1
      fetchUserTasks()
    }

    onMounted(async () => {
      await nextTick()
      if (searchInput.value) {
        searchInput.value.focus()
      }
      fetchUserTasks()
    })

    return {
      user,
      tasks,
      pagination,
      isLoading,
      filters,
      searchInput,
      completedTasks,
      pendingTasks,
      overdueTasks,
      formatStatus,
      formatDate,
      isOverdue,
      debouncedSearch,
      applyFilters,
      toggleSortOrder,
      clearFilters,
      changePage
    }
  }
}
</script>

<style scoped>
.user-tasks-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem;
}

.loading-state {
  text-align: center;
  padding: 4rem;
}

.loading-state .spinner {
  margin: 0 auto 1rem;
}

.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-bottom: 2rem;
}

.back-btn {
  color: #3498db;
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: background-color 0.3s;
}

.back-btn:hover {
  background-color: rgba(52, 152, 219, 0.1);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 1.5rem;
}

.user-details h1 {
  margin: 0 0 0.25rem 0;
  color: #2c3e50;
}

.user-details p {
  margin: 0 0 0.5rem 0;
  color: #7f8c8d;
}

.user-role {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.user-role.admin {
  background: #e74c3c;
  color: white;
}

.user-role.user {
  background: #3498db;
  color: white;
}

.task-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 12px;
}

.summary-item {
  text-align: center;
}

.summary-number {
  display: block;
  font-size: 2rem;
  font-weight: bold;
  color: #2c3e50;
}

.summary-label {
  font-size: 0.875rem;
  color: #7f8c8d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.tasks-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.task-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-left: 4px solid #3498db;
  transition: transform 0.2s;
}

.task-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.task-card.overdue {
  border-left-color: #e74c3c;
}

.task-card.completed {
  border-left-color: #27ae60;
  opacity: 0.8;
}

.task-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.task-header h3 {
  margin: 0;
  color: #2c3e50;
  flex: 1;
  margin-right: 1rem;
}

.task-status {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.task-status.pending {
  background: #f39c12;
  color: white;
}

.task-status.in_progress {
  background: #3498db;
  color: white;
}

.task-status.completed {
  background: #27ae60;
  color: white;
}

.task-description {
  color: #7f8c8d;
  line-height: 1.6;
  margin-bottom: 1rem;
}

.task-meta {
  border-top: 1px solid #ecf0f1;
  padding-top: 1rem;
}

.meta-row {
  display: flex;
  gap: 2rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  color: #7f8c8d;
}

.due-date strong,
.created-date strong {
  color: #2c3e50;
}

.task-tags {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.task-tag {
  background: #ecf0f1;
  color: #2c3e50;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

/* Search and Filter Styles */
.search-filters {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.search-bar {
  margin-bottom: 1rem;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #ecf0f1;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.3s;
}

.search-input:focus {
  outline: none;
  border-color: #3498db;
}

.filter-controls {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: center;
}

.filter-select {
  padding: 0.5rem 0.75rem;
  border: 2px solid #ecf0f1;
  border-radius: 6px;
  background: white;
  font-size: 0.875rem;
  cursor: pointer;
  transition: border-color 0.3s;
}

.filter-select:focus {
  outline: none;
  border-color: #3498db;
}

.sort-order-btn {
  padding: 0.5rem 0.75rem;
  border: 2px solid #ecf0f1;
  border-radius: 6px;
  background: white;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s;
  min-width: 40px;
}

.sort-order-btn:hover {
  border-color: #3498db;
  background: #f8f9fa;
}

.clear-filters-btn {
  padding: 0.5rem 1rem;
  border: 2px solid #e74c3c;
  border-radius: 6px;
  background: white;
  color: #e74c3c;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.clear-filters-btn:hover {
  background: #e74c3c;
  color: white;
}

.empty-state {
  text-align: center;
  padding: 4rem;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: #7f8c8d;
  margin-bottom: 1rem;
}

.empty-state p {
  color: #95a5a6;
  margin-bottom: 2rem;
}

/* Pagination Styles */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
  padding: 1rem;
}

.pagination-btn {
  padding: 0.5rem 1rem;
  border: 2px solid #3498db;
  border-radius: 6px;
  background: white;
  color: #3498db;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.pagination-btn:hover:not(:disabled) {
  background: #3498db;
  color: white;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  border-color: #bdc3c7;
  color: #bdc3c7;
}

.pagination-info {
  font-size: 0.875rem;
  color: #7f8c8d;
  text-align: center;
}

@media (max-width: 768px) {
  .user-tasks-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .user-info {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .task-summary {
    grid-template-columns: repeat(2, 1fr);
  }

  .search-filters {
    padding: 1rem;
  }

  .filter-controls {
    flex-direction: column;
    align-items: stretch;
  }

  .filter-select,
  .sort-order-btn,
  .clear-filters-btn {
    width: 100%;
  }

  .task-header {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
  }

  .meta-row {
    flex-direction: column;
    gap: 0.5rem;
  }

  .pagination {
    flex-direction: column;
    gap: 0.5rem;
  }

  .pagination-info {
    order: -1;
  }
}
</style>
