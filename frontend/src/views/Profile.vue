<template>
  <div class="profile-page">
    <h1>My Profile</h1>

    <div v-if="!authStore.user" class="loading">Loading...</div>
    <div v-else class="profile-card">
      <div class="row">
        <label>Name</label>
        <div>{{ authStore.user.name }}</div>
      </div>
      <div class="row">
        <label>Email</label>
        <div>{{ authStore.user.email }}</div>
      </div>
      <div class="row">
        <label>Role</label>
        <div class="role" :class="authStore.user.role">{{ authStore.user.role }}</div>
      </div>
      
    </div>
  </div>
  
</template>

<script>
import { useAuthStore } from '../stores/auth'

export default {
  name: 'Profile',
  setup() {
    const authStore = useAuthStore()

    const formatDate = (dateString) => {
      if (!dateString) return '—'
      return new Date(dateString).toLocaleString()
    }

    return { authStore, formatDate }
  }
}
</script>

<style scoped>
.profile-page { max-width: 700px; margin: 0 auto; padding: 2rem; }
.profile-card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.row { display: grid; grid-template-columns: 160px 1fr; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid #ecf0f1; }
.row:last-child { border-bottom: 0; }
label { color: #7f8c8d; }
.role { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; text-transform: uppercase; color: #fff; }
.role.admin { background: #e74c3c; }
.role.user { background: #3498db; }
.loading { text-align: center; padding: 2rem; color: #7f8c8d; }
</style>


