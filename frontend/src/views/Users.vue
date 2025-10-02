<template>
  <div class="users-page">
    <div class="page-header">
      <h1>Users Management</h1>
      <div v-if="pagination" class="stats-summary">
        <span class="stat-item">
          <strong>{{ pagination.total }}</strong> Total Users
        </span>
        <span class="stat-item">
          <strong>{{ users.filter(u => u.role === 'admin').length }}</strong> Admins (page)
        </span>
        <span class="stat-item">
          <strong>{{ users.filter(u => u.role === 'user').length }}</strong> Regular Users (page)
        </span>
      </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-filters">
      <!-- Search bar -->
      <div class="search-bar">
        <input
          ref="usersSearchInput"
          v-model="filters.search"
          @input="debouncedSearch"
          type="text"
          placeholder="Search users by name or email..."
          class="search-input"
        >
      </div>

      <!-- Filter controls -->
      <div class="filter-controls">
        <!-- Role filter -->
        <select v-model="filters.role" @change="applyFilters" class="filter-select">
          <option value="">All Roles</option>
          <option value="admin">Admin</option>
          <option value="user">User</option>
        </select>

        <!-- Verification status filter -->
        <select v-model="filters.verified" @change="applyFilters" class="filter-select">
          <option value="">All Users</option>
          <option value="1">Verified</option>
          <option value="0">Unverified</option>
        </select>

        <!-- Sort options -->
        <select v-model="filters.sort_by" @change="applyFilters" class="filter-select">
          <option value="created_at">Sort by Join Date</option>
          <option value="name">Sort by Name</option>
          <option value="email">Sort by Email</option>
          <option value="tasks_count">Sort by Task Count</option>
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

    <!-- Loading state -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading users...</p>
    </div>

    <!-- Users grid -->
    <div v-else-if="users.length > 0" class="users-content">
      <div class="users-grid">
        <div
          v-for="user in users"
          :key="user.id"
          class="user-card"
          @click="viewUserTasks(user)"
        >
        <div class="user-header">
          <div class="user-avatar">
            {{ user.name.charAt(0).toUpperCase() }}
          </div>
          <div class="user-info">
            <h3>{{ user.name }}</h3>
            <p class="user-email">{{ user.email }}</p>
            <span class="user-role" :class="user.role">
              {{ user.role }}
            </span>
          </div>
        </div>

        <div class="user-stats">
          <div class="stat">
            <span class="stat-number">{{ user.tasks_count || 0 }}</span>
            <span class="stat-label">Tasks</span>
          </div>
          <div class="stat">
            <span class="stat-number">{{ formatDate(user.created_at) }}</span>
            <span class="stat-label">Joined</span>
          </div>
        </div>

        <div class="user-actions">
          <span class="view-tasks-btn">
            View Tasks →
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
          ({{ pagination.total }} total users)
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

    <!-- Empty state -->
    <div v-else class="empty-state">
      <h3>No users found</h3>
      <p>There are no users in the system yet.</p>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'

export default {
  name: 'Users',
  setup() {
    const router = useRouter()
    const { showToast } = useToast()
    
    const users = ref([])
    const pagination = ref(null)
    const isLoading = ref(true)
    const usersSearchInput = ref(null)

    // Filter and search states
    const filters = reactive({
      search: '',
      role: '',
      verified: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      page: 1,
      per_page: 12
    })

    let searchTimeout = null

    const fetchUsers = async () => {
      try {
        isLoading.value = true
        
        const params = new URLSearchParams()
        Object.entries(filters).forEach(([key, value]) => {
          if (value) params.append(key, value)
        })
        
        const response = await api.get(`/admin/users?${params}`)
        users.value = response.data.users
        pagination.value = response.data.pagination
      } catch (error) {
        console.error('Failed to fetch users:', error)
        showToast('Failed to load users', 'error')
      } finally {
        isLoading.value = false
        await nextTick()
        if (usersSearchInput.value) {
          usersSearchInput.value.focus()
          usersSearchInput.value.select()
        }
      }
    }

    const debouncedSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        // Reset to first page on search and temporarily increase page size
        filters.page = 1
        filters.per_page = filters.search && filters.search.trim() ? 50 : 12
        fetchUsers()
      }, 300)
    }

    const applyFilters = () => {
      filters.page = 1
      fetchUsers()
    }

    const toggleSortOrder = () => {
      filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc'
      applyFilters()
    }

    const clearFilters = () => {
      filters.search = ''
      filters.role = ''
      filters.verified = ''
      filters.sort_by = 'created_at'
      filters.sort_order = 'desc'
      filters.page = 1
      filters.per_page = 12
      fetchUsers()
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        filters.page = page
        fetchUsers()
      }
    }

    const viewUserTasks = (user) => {
      router.push(`/users/${user.id}/tasks`)
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
      })
    }

    onMounted(async () => {
      await nextTick()
      if (usersSearchInput.value) {
        usersSearchInput.value.focus()
      }
      fetchUsers()
    })

    return {
      users,
      pagination,
      isLoading,
      filters,
      usersSearchInput,
      viewUserTasks,
      formatDate,
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
.users-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  color: #2c3e50;
  margin: 0 0 1rem 0;
}

.stats-summary {
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
}

.stat-item {
  color: #7f8c8d;
  font-size: 0.875rem;
}

.stat-item strong {
  color: #2c3e50;
  font-size: 1.25rem;
  display: block;
}

.loading-state {
  text-align: center;
  padding: 4rem;
}

.loading-state .spinner {
  margin: 0 auto 1rem;
}

.users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.user-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.user-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border-color: #3498db;
}

.user-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.user-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 1.25rem;
}

.user-info h3 {
  margin: 0 0 0.25rem 0;
  color: #2c3e50;
  font-size: 1.125rem;
}

.user-email {
  margin: 0 0 0.5rem 0;
  color: #7f8c8d;
  font-size: 0.875rem;
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

.user-stats {
  display: flex;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.stat {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 1.25rem;
  font-weight: bold;
  color: #2c3e50;
}

.stat-label {
  font-size: 0.75rem;
  color: #7f8c8d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.user-actions {
  text-align: center;
}

.view-tasks-btn {
  color: #3498db;
  font-weight: 500;
  font-size: 0.875rem;
  opacity: 0.7;
  transition: opacity 0.3s ease;
}

.user-card:hover .view-tasks-btn {
  opacity: 1;
}

.empty-state {
  text-align: center;
  padding: 4rem;
}

.empty-state h3 {
  color: #7f8c8d;
  margin-bottom: 1rem;
}

.empty-state p {
  color: #95a5a6;
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
  .users-page {
    padding: 1rem;
  }

  .users-grid {
    grid-template-columns: 1fr;
  }

  .stats-summary {
    flex-direction: column;
    gap: 1rem;
  }

  .user-stats {
    flex-direction: column;
    gap: 1rem;
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

  .pagination {
    flex-direction: column;
    gap: 0.5rem;
  }

  .pagination-info {
    order: -1;
  }
}
</style>
