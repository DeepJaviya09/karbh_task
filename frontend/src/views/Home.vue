<template>
  <div class="home">
    <div class="hero">
      <h1>Welcome to Task Manager</h1>
      <p class="hero-subtitle">
        Organize your tasks efficiently and boost your productivity
      </p>
      
      <div class="hero-actions" v-if="!authStore.isAuthenticated">
        <router-link to="/signup" class="btn btn-primary">
          Get Started
        </router-link>
        <router-link to="/login" class="btn btn-secondary">
          Login
        </router-link>
      </div>
      
      <div class="hero-actions" v-else>
        <router-link to="/tasks" class="btn btn-primary">
          View Your Tasks
        </router-link>
      </div>
    </div>

    <div class="features">
      <h2>Features</h2>
      <div class="features-grid">
        <div class="feature-card">
          <h3>🎯 Task Management</h3>
          <p>Create, update, and organize your tasks with ease</p>
        </div>
        <div class="feature-card">
          <h3>🏷️ Tags & Categories</h3>
          <p>Organize tasks with custom tags for better organization</p>
        </div>
        <div class="feature-card">
          <h3>📅 Due Dates</h3>
          <p>Set due dates and never miss important deadlines</p>
        </div>
        <div class="feature-card">
          <h3>🔍 Search & Filter</h3>
          <p>Find tasks quickly with powerful search and filtering</p>
        </div>
        <div class="feature-card">
          <h3>👥 User Roles</h3>
          <p>Admin dashboard for managing users and all tasks</p>
        </div>
        <div class="feature-card">
          <h3>📱 Responsive</h3>
          <p>Works perfectly on desktop, tablet, and mobile devices</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth'

export default {
  name: 'Home',
  setup() {
    const authStore = useAuthStore()

    return {
      authStore
    }
  }
}
</script>

<style scoped>
.home {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.hero {
  text-align: center;
  padding: 4rem 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 12px;
  margin-bottom: 4rem;
}

.hero h1 {
  font-size: 3rem;
  margin-bottom: 1rem;
  font-weight: 700;
}

.hero-subtitle {
  font-size: 1.25rem;
  margin-bottom: 2rem;
  opacity: 0.9;
}

.hero-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.features {
  text-align: center;
}

.features h2 {
  font-size: 2.5rem;
  margin-bottom: 3rem;
  color: #2c3e50;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
  margin-top: 2rem;
}

.feature-card {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.feature-card h3 {
  font-size: 1.5rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.feature-card p {
  color: #7f8c8d;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .hero h1 {
    font-size: 2rem;
  }
  
  .hero-subtitle {
    font-size: 1rem;
  }
  
  .hero-actions {
    flex-direction: column;
    align-items: center;
  }
  
  .features-grid {
    grid-template-columns: 1fr;
  }
}
</style>

