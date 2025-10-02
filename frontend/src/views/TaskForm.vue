<template>
  <div class="task-form-page">
    <div class="page-header">
      <h1>{{ isEdit ? 'Edit Task' : 'Create New Task' }}</h1>
      <router-link to="/tasks" class="btn btn-secondary">
        Back to Tasks
      </router-link>
    </div>

    <div class="form-container">
      <form @submit.prevent="handleSubmit">
        <div class="form-group">
          <label for="title">Task Title *</label>
          <input
            id="title"
            v-model="form.title"
            type="text"
            class="form-control"
            :class="{ 'error': errors.title }"
            placeholder="Enter task title"
            required
          >
          <div v-if="errors.title" class="error-message">
            {{ errors.title[0] }}
          </div>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea
            id="description"
            v-model="form.description"
            class="form-control"
            :class="{ 'error': errors.description }"
            placeholder="Enter task description"
            rows="4"
          ></textarea>
          <div v-if="errors.description" class="error-message">
            {{ errors.description[0] }}
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="status">Status</label>
            <select
              id="status"
              v-model="form.status"
              class="form-control"
              :class="{ 'error': errors.status }"
            >
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
            </select>
            <div v-if="errors.status" class="error-message">
              {{ errors.status[0] }}
            </div>
          </div>

          <div class="form-group">
            <label for="due_date">Due Date</label>
            <input
              id="due_date"
              v-model="form.due_date"
              type="date"
              class="form-control"
              :class="{ 'error': errors.due_date }"
              :min="today"
            >
            <div v-if="errors.due_date" class="error-message">
              {{ errors.due_date[0] }}
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="tags">Tags</label>
          <div class="tags-input">
            <div class="current-tags">
              <span
                v-for="(tag, index) in form.tags"
                :key="index"
                class="tag-item"
              >
                {{ tag }}
                <button
                  type="button"
                  @click="removeTag(index)"
                  class="tag-remove"
                >
                  ×
                </button>
              </span>
            </div>
            <input
              v-model="newTag"
              type="text"
              class="form-control"
              placeholder="Add a tag and press Enter"
              @keydown.enter.prevent="addTag"
            >
          </div>
          <div v-if="errors.tags" class="error-message">
            {{ errors.tags[0] }}
          </div>
        </div>

        <div class="form-actions">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="isLoading"
          >
            {{ isLoading ? 'Saving...' : (isEdit ? 'Update Task' : 'Create Task') }}
          </button>
          <router-link to="/tasks" class="btn btn-secondary">
            Cancel
          </router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'

export default {
  name: 'TaskForm',
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { showToast } = useToast()

    const isEdit = computed(() => !!route.params.id)
    const isLoading = ref(false)
    const errors = ref({})
    const newTag = ref('')

    const form = reactive({
      title: '',
      description: '',
      status: 'pending',
      due_date: '',
      tags: []
    })

    const today = computed(() => {
      return new Date().toISOString().split('T')[0]
    })

    const fetchTask = async () => {
      if (!isEdit.value) return

      try {
        const response = await api.get(`/tasks/${route.params.id}`)
        const task = response.data.task

        form.title = task.title
        form.description = task.description || ''
        form.status = task.status
        form.due_date = task.due_date || ''
        form.tags = task.tags ? task.tags.map(tag => tag.name) : []
      } catch (error) {
        console.error('Failed to fetch task:', error)
        showToast('Failed to load task', 'error')
        router.push('/tasks')
      }
    }

    const addTag = () => {
      const tag = newTag.value.trim()
      if (tag && !form.tags.includes(tag)) {
        form.tags.push(tag)
        newTag.value = ''
      }
    }

    const removeTag = (index) => {
      form.tags.splice(index, 1)
    }

    const handleSubmit = async () => {
      try {
        isLoading.value = true
        errors.value = {}

        const payload = { ...form }
        
        let response
        if (isEdit.value) {
          response = await api.put(`/tasks/${route.params.id}`, payload)
        } else {
          response = await api.post('/tasks', payload)
        }

        showToast(
          isEdit.value ? 'Task updated successfully!' : 'Task created successfully!',
          'success'
        )
        
        router.push('/tasks')
      } catch (error) {
        console.error('Failed to save task:', error)
        
        if (error.response?.data?.errors) {
          errors.value = error.response.data.errors
        }
        
        const message = error.response?.data?.message || 'Failed to save task'
        showToast(message, 'error')
      } finally {
        isLoading.value = false
      }
    }

    onMounted(() => {
      fetchTask()
    })

    return {
      isEdit,
      isLoading,
      errors,
      form,
      newTag,
      today,
      addTag,
      removeTag,
      handleSubmit
    }
  }
}
</script>

<style scoped>
.task-form-page {
  max-width: 800px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.tags-input {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.5rem;
  min-height: 60px;
}

.current-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.tag-item {
  background: #3498db;
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.tag-remove {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  font-size: 1rem;
  padding: 0;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tag-remove:hover {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
}

.tags-input input {
  border: none;
  outline: none;
  width: 100%;
  padding: 0;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .form-actions {
    flex-direction: column;
  }
}
</style>
