<template>
  <div class="email-verification-page">
    <div class="verification-container">
      <!-- Loading state -->
      <div v-if="isVerifying" class="verification-loading">
        <div class="spinner"></div>
        <h2>Verifying your email...</h2>
        <p>Please wait while we verify your email address.</p>
      </div>

      <!-- Success state -->
      <div v-else-if="verificationStatus === 'success'" class="verification-success">
        <div class="success-icon">✅</div>
        <h2>Email Verified Successfully!</h2>
        <p>Your email has been verified. You can now access all features of the Task Manager.</p>
        <router-link to="/tasks" class="btn btn-primary">
          Go to Tasks
        </router-link>
      </div>

      <!-- Error state -->
      <div v-else-if="verificationStatus === 'error'" class="verification-error">
        <div class="error-icon">❌</div>
        <h2>Verification Failed</h2>
        <p>{{ errorMessage }}</p>
        <div class="error-actions">
          <button @click="resendVerification" class="btn btn-primary" :disabled="isResending">
            {{ isResending ? 'Sending...' : 'Resend Verification Email' }}
          </button>
          <router-link to="/login" class="btn btn-secondary">
            Back to Login
          </router-link>
        </div>
      </div>

      <!-- Email not verified notice -->
      <div v-else class="verification-pending">
        <div class="pending-icon">📧</div>
        <h2>Please Verify Your Email</h2>
        <p v-if="isFromLogin">
          Your account requires email verification before you can log in. Please check your email and click the verification link that was sent when you registered.
        </p>
        <p v-else>
          We've sent a verification email to your address. Please check your email and click the verification link to activate your account.
        </p>
        
        <div class="pending-actions">
          <button @click="resendVerification" class="btn btn-primary" :disabled="isResending">
            {{ isResending ? 'Sending...' : 'Resend Verification Email' }}
          </button>
          <router-link to="/login" class="btn btn-secondary">
            Back to Login
          </router-link>
        </div>

        <div class="help-text">
          <p><strong>Didn't receive the email?</strong></p>
          <ul>
            <li>Check your spam/junk folder</li>
            <li>Make sure you entered the correct email address</li>
            <li>Click "Resend Verification Email" to get a new link</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { useToast } from '../utils/toast'
import { useAuthStore } from '../stores/auth'

export default {
  name: 'EmailVerification',
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { showToast } = useToast()
    const authStore = useAuthStore()

    const isVerifying = ref(false)
    const isResending = ref(false)
    const verificationStatus = ref(null)
    const errorMessage = ref('')
    const userEmail = ref(localStorage.getItem('pendingVerificationEmail') || '')
    const isFromLogin = ref(route.query.fromLogin === 'true')

    const verifyEmail = async () => {
      const { id, token } = route.query

      if (!id || !token) {
        verificationStatus.value = 'error'
        errorMessage.value = 'Invalid verification link. Please check the link in your email.'
        return
      }

      try {
        isVerifying.value = true
        const response = await api.get('/auth/verify-email', {
          params: { id, token }
        })

        verificationStatus.value = 'success'
        
        // If we got a token, log the user in
        if (response.data.token) {
          authStore.setToken(response.data.token)
          authStore.user = response.data.user
        }

        showToast(response.data.message, 'success')
        
        // Clear pending email from storage
        localStorage.removeItem('pendingVerificationEmail')

      } catch (error) {
        verificationStatus.value = 'error'
        errorMessage.value = error.response?.data?.message || 'Verification failed. Please try again.'
        showToast(errorMessage.value, 'error')
      } finally {
        isVerifying.value = false
      }
    }

    const resendVerification = async () => {
      if (!userEmail.value) {
        showToast('Please provide your email address', 'error')
        return
      }

      try {
        isResending.value = true
        const response = await api.post('/auth/resend-verification', {
          email: userEmail.value
        })

        showToast(response.data.message, 'success')
      } catch (error) {
        const message = error.response?.data?.message || 'Failed to resend verification email'
        showToast(message, 'error')
      } finally {
        isResending.value = false
      }
    }

    onMounted(() => {
      // If we have verification parameters, verify immediately
      if (route.query.id && route.query.token) {
        verifyEmail()
      } else {
        // Show the pending verification state
        verificationStatus.value = 'pending'
      }
    })

    return {
      isVerifying,
      isResending,
      verificationStatus,
      errorMessage,
      userEmail,
      isFromLogin,
      resendVerification
    }
  }
}
</script>

<style scoped>
.email-verification-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.verification-container {
  max-width: 500px;
  width: 100%;
  background: white;
  padding: 3rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.verification-loading,
.verification-success,
.verification-error,
.verification-pending {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.success-icon,
.error-icon,
.pending-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.verification-container h2 {
  color: #2c3e50;
  margin: 0;
}

.verification-container p {
  color: #7f8c8d;
  line-height: 1.6;
  margin: 0;
}

.error-actions,
.pending-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  justify-content: center;
}

.help-text {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 8px;
  text-align: left;
  margin-top: 2rem;
}

.help-text p {
  color: #2c3e50;
  margin-bottom: 1rem;
}

.help-text ul {
  color: #7f8c8d;
  padding-left: 1.5rem;
}

.help-text li {
  margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
  .verification-container {
    padding: 2rem 1.5rem;
  }

  .error-actions,
  .pending-actions {
    flex-direction: column;
  }
}
</style>
