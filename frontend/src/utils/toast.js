import { ref, reactive } from 'vue'

const toasts = reactive([])
let toastId = 0

export const useToast = () => {
  const showToast = (message, type = 'info', duration = 4000) => {
    const id = ++toastId
    const toast = {
      id,
      message,
      type
    }
    
    toasts.push(toast)
    
    // Auto remove toast after duration
    setTimeout(() => {
      const index = toasts.findIndex(t => t.id === id)
      if (index > -1) {
        toasts.splice(index, 1)
      }
    }, duration)
  }

  const removeToast = (id) => {
    const index = toasts.findIndex(t => t.id === id)
    if (index > -1) {
      toasts.splice(index, 1)
    }
  }

  const clearToasts = () => {
    toasts.splice(0)
  }

  return {
    toasts,
    showToast,
    removeToast,
    clearToasts
  }
}

