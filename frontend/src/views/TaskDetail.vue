<template>
  <div class="task-detail-page">
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading task...</p>
    </div>

    <div v-else-if="task" class="task-detail">
      <div class="page-header">
        <h1>{{ task.title }}</h1>
        <div class="task-actions">
          <router-link 
            :to="`/tasks/${task.id}/edit`" 
            class="btn btn-primary"
          >
            Edit Task
          </router-link>
          <router-link to="/tasks" class="btn btn-secondary">
            Back to Tasks
          </router-link>
        </div>
      </div>

      <div class="task-content card">
        <div class="task-meta">
          <div class="meta-item">
            <strong>Status:</strong>
            <span class="task-status" :class="task.status">
              {{ formatStatus(task.status) }}
            </span>
          </div>

          <div class="meta-item" v-if="task.due_date">
            <strong>Due Date:</strong>
            <span 
              class="task-due-date"
              :class="{ 'overdue': isOverdue(task) }"
            >
              {{ formatDate(task.due_date) }}
            </span>
          </div>

          <div class="meta-item">
            <strong>Created:</strong>
            <span>{{ formatDate(task.created_at) }}</span>
          </div>

          <div class="meta-item" v-if="task.updated_at !== task.created_at">
            <strong>Updated:</strong>
            <span>{{ formatDate(task.updated_at) }}</span>
          </div>

          <div class="meta-item" v-if="task.user">
            <strong>Owner:</strong>
            <span>{{ task.user.name }} ({{ task.user.email }})</span>
          </div>
        </div>

        <div v-if="task.description" class="task-description">
          <h3>Description</h3>
          <p>{{ task.description }}</p>
        </div>

        <div v-if="task.tags && task.tags.length > 0" class="task-tags-section">
          <h3>Tags</h3>
          <div class="task-tags">
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
    </div>

    <div v-else class="error-state card">
      <h3>Task Not Found</h3>
      <p>The task you're looking for doesn't exist or you don't have permission to view it.</p>
      <router-link to="/tasks" class="btn btn-primary">
        Back to Tasks
      </router-link>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'

export default {
  name: 'TaskDetail',
  setup() {
    const route = useRoute()
    const { showToast } = useToast()

    const task = ref(null)
    const isLoading = ref(true)

    const fetchTask = async () => {
      try {
        const response = await api.get(`/tasks/${route.params.id}`)
        task.value = response.data.task
      } catch (error) {
        console.error('Failed to fetch task:', error)
        showToast('Failed to load task', 'error')
      } finally {
        isLoading.value = false
      }
    }

    const formatStatus = (status) => {
      return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }

    const isOverdue = (task) => {
      if (!task.due_date || task.status === 'completed') return false
      return new Date(task.due_date) < new Date()
    }

    onMounted(() => {
      fetchTask()
    })

    return {
      task,
      isLoading,
      formatStatus,
      formatDate,
      isOverdue
    }
  }
}
</script>

<style scoped>
.task-detail-page {
  max-width: 800px;
  margin: 0 auto;
}

.loading-state,
.error-state {
  text-align: center;
  padding: 3rem;
}

.loading-state .spinner {
  margin: 0 auto 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.page-header h1 {
  color: #2c3e50;
  margin: 0;
  flex: 1;
  margin-right: 2rem;
}

.task-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

.task-content {
  padding: 2rem;
}

.task-meta {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid #eee;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.meta-item strong {
  color: #7f8c8d;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.task-due-date.overdue {
  color: #e74c3c;
  font-weight: 600;
}

.task-description {
  margin-bottom: 2rem;
}

.task-description h3 {
  color: #2c3e50;
  margin-bottom: 1rem;
}

.task-description p {
  color: #7f8c8d;
  line-height: 1.6;
  white-space: pre-wrap;
}

.task-tags-section h3 {
  color: #2c3e50;
  margin-bottom: 1rem;
}

.task-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.error-state h3 {
  color: #e74c3c;
  margin-bottom: 1rem;
}

.error-state p {
  color: #7f8c8d;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .page-header h1 {
    margin-right: 0;
  }

  .task-actions {
    justify-content: flex-end;
  }

  .task-meta {
    grid-template-columns: 1fr;
  }
}
</style>
