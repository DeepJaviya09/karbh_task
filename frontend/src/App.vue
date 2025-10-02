<template>
  <div id="app">
    <nav class="navbar">
      <div class="nav-container">
        <router-link to="/" class="nav-logo">
          Task Manager
        </router-link>
        
        <div class="nav-menu" :class="{ active: isMenuOpen }">
          <template v-if="authStore.isAuthenticated">
            <router-link 
              v-if="authStore.user?.role === 'user'" 
              to="/tasks" 
              class="nav-link" 
              @click="closeMenu"
            >
              Tasks
            </router-link>
            <router-link 
              v-if="authStore.user?.role === 'admin'" 
              to="/users" 
              class="nav-link" 
              @click="closeMenu"
            >
              Users
            </router-link>
            <router-link 
              to="/profile" 
              class="nav-link" 
              @click="closeMenu"
            >
              Profile
            </router-link>
            <div class="nav-link user-menu">
              <span>{{ authStore.user?.name }}</span>
              <button @click="logout" class="logout-btn">Logout</button>
            </div>
          </template>
          <template v-else>
            <router-link to="/login" class="nav-link" @click="closeMenu">
              Login
            </router-link>
            <router-link to="/signup" class="nav-link" @click="closeMenu">
              Sign Up
            </router-link>
          </template>
        </div>

        <div class="nav-toggle" @click="toggleMenu">
          <span class="bar"></span>
          <span class="bar"></span>
          <span class="bar"></span>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <router-view />
    </main>

    <!-- Loading overlay -->
    <div v-if="authStore.isLoading" class="loading-overlay">
      <div class="spinner"></div>
    </div>

    <!-- Toast notifications -->
    <div class="toast-container">
      <div 
        v-for="toast in toasts" 
        :key="toast.id" 
        :class="['toast', `toast-${toast.type}`]"
      >
        {{ toast.message }}
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useToast } from './utils/toast'

export default {
  name: 'App',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    const { toasts } = useToast()
    const isMenuOpen = ref(false)

    const toggleMenu = () => {
      isMenuOpen.value = !isMenuOpen.value
    }

    const closeMenu = () => {
      isMenuOpen.value = false
    }

    const logout = async () => {
      await authStore.logout()
      closeMenu()
      // Redirect to login page after logout
      router.push('/login')
    }

    onMounted(() => {
      authStore.checkAuth()
    })

    return {
      authStore,
      toasts,
      isMenuOpen,
      toggleMenu,
      closeMenu,
      logout
    }
  }
}
</script>

