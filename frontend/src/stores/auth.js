import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'
import { useToast } from '../utils/toast'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token'))
  const isLoading = ref(false)
  
  const { showToast } = useToast()

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  // Set token in localStorage and API headers
  const setToken = (newToken) => {
    token.value = newToken
    if (newToken) {
      localStorage.setItem('token', newToken)
      api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
    } else {
      localStorage.removeItem('token')
      delete api.defaults.headers.common['Authorization']
    }
  }

  // Initialize API with token if it exists
  const initializeAuth = () => {
    if (token.value) {
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    }
  }

  // Check if user is authenticated and get profile
  const checkAuth = async () => {
    if (!token.value) return false

    try {
      isLoading.value = true
      const response = await api.get('/auth/profile')
      user.value = response.data.user
      return true
    } catch (error) {
      console.error('Auth check failed:', error)
      setToken(null)
      user.value = null
      return false
    } finally {
      isLoading.value = false
    }
  }

  // Login
  const login = async (credentials) => {
    try {
      isLoading.value = true
      const response = await api.post('/auth/login', credentials)
      
      const { token: newToken, user: userData } = response.data
      setToken(newToken)
      user.value = userData
      
      showToast('Login successful!', 'success')
      return { 
        success: true,
        redirectTo: userData.role === 'admin' ? '/users' : '/tasks'
      }
    } catch (error) {
      const message = error.response?.data?.message || 'Login failed'
      
      // Handle email verification required
      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
        return {
          success: false,
          message,
          requires_verification: true,
          user_id: error.response.data.user_id
        }
      }
      
      showToast(message, 'error')
      return { 
        success: false, 
        message,
        errors: error.response?.data?.errors 
      }
    } finally {
      isLoading.value = false
    }
  }

  // Signup
  const signup = async (userData) => {
    try {
      isLoading.value = true
      const response = await api.post('/auth/signup', userData)
      
      const { token: newToken, user: newUser, requires_verification } = response.data
      
      if (newToken) {
        setToken(newToken)
        user.value = newUser
      }
      
      showToast(response.data.message, 'success')
      return { 
        success: true,
        requires_verification: requires_verification || false,
        user: newUser
      }
    } catch (error) {
      const message = error.response?.data?.message || 'Signup failed'
      showToast(message, 'error')
      return { 
        success: false, 
        message,
        errors: error.response?.data?.errors 
      }
    } finally {
      isLoading.value = false
    }
  }

  // Logout
  const logout = async () => {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } catch (error) {
      console.error('Logout API call failed:', error)
    } finally {
      setToken(null)
      user.value = null
      showToast('Logged out successfully', 'info')
    }
  }

  // Handle token expiry
  const handleTokenExpiry = () => {
    setToken(null)
    user.value = null
    showToast('Session expired. Please login again.', 'error')
  }

  return {
    // State
    user,
    token,
    isLoading,
    
    // Getters
    isAuthenticated,
    isAdmin,
    
    // Actions
    initializeAuth,
    checkAuth,
    login,
    signup,
    logout,
    handleTokenExpiry,
    setToken
  }
})

