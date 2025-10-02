<template>
  <div class="auth-page">
    <div class="form-container">
      <h2 class="text-center mb-3">Login to Your Account</h2>
      
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="form-control"
            :class="{ 'error': errors.email }"
            placeholder="Enter your email"
            required
          >
          <div v-if="errors.email" class="error-message">
            {{ errors.email[0] }}
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="form-control"
            :class="{ 'error': errors.password }"
            placeholder="Enter your password"
            required
          >
          <div v-if="errors.password" class="error-message">
            {{ errors.password[0] }}
          </div>
        </div>

        <button 
          type="submit" 
          class="btn btn-primary w-full"
          :disabled="authStore.isLoading"
        >
          {{ authStore.isLoading ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <div class="text-center mt-3">
        <p>
          Don't have an account? 
          <router-link to="/signup" class="link">Sign up here</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

export default {
  name: 'Login',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    
    const form = reactive({
      email: '',
      password: ''
    })
    
    const errors = ref({})

    const handleLogin = async () => {
      errors.value = {}
      
      const result = await authStore.login(form)
      
      if (result.success) {
        router.push(result.redirectTo || '/tasks')
      } else {
        if (result.requires_verification) {
          // Store email for verification page but don't auto-send email
          localStorage.setItem('pendingVerificationEmail', form.email)
          router.push({ 
            path: '/verify-email', 
            query: { fromLogin: 'true' } 
          })
        } else if (result.errors) {
          errors.value = result.errors
        }
      }
    }

    return {
      form,
      errors,
      authStore,
      handleLogin
    }
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.form-control.error {
  border-color: #e74c3c;
}

.link {
  color: #3498db;
  text-decoration: none;
}

.link:hover {
  text-decoration: underline;
}
</style>

