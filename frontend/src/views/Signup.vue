<template>
  <div class="auth-page">
    <div class="form-container">
      <h2 class="text-center mb-3">Create Your Account</h2>
      
      <form @submit.prevent="handleSignup">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="form-control"
            :class="{ 'error': errors.name }"
            placeholder="Enter your full name"
            required
          >
          <div v-if="errors.name" class="error-message">
            {{ errors.name[0] }}
          </div>
        </div>

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
            placeholder="Enter your password (min 8 characters)"
            required
            minlength="8"
          >
          <div v-if="errors.password" class="error-message">
            {{ errors.password[0] }}
          </div>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm Password</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            class="form-control"
            :class="{ 'error': errors.password_confirmation }"
            placeholder="Confirm your password"
            required
          >
          <div v-if="errors.password_confirmation" class="error-message">
            {{ errors.password_confirmation[0] }}
          </div>
        </div>

        <button 
          type="submit" 
          class="btn btn-primary w-full"
          :disabled="authStore.isLoading"
        >
          {{ authStore.isLoading ? 'Creating Account...' : 'Sign Up' }}
        </button>
      </form>

      <div class="text-center mt-3">
        <p>
          Already have an account? 
          <router-link to="/login" class="link">Login here</router-link>
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
  name: 'Signup',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    
    const form = reactive({
      name: '',
      email: '',
      password: '',
      password_confirmation: ''
    })
    
    const errors = ref({})

    const handleSignup = async () => {
      errors.value = {}
      
      const result = await authStore.signup(form)
      
      if (result.success) {
        if (result.requires_verification) {
          // Store email for verification page (new registration)
          localStorage.setItem('pendingVerificationEmail', form.email)
          router.push('/verify-email')
        } else {
          // Admin users or already verified users go directly to tasks
          router.push('/tasks')
        }
      } else {
        if (result.errors) {
          errors.value = result.errors
        }
      }
    }

    return {
      form,
      errors,
      authStore,
      handleSignup
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

