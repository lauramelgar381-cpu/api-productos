import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/service/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token'))
  const user = ref(JSON.parse(localStorage.getItem('user_data') || 'null'))

  // Solo en memoria: al recargar se pierde, y es lo correcto
  const challenge = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  // Permisos del usuario (vienen en user.permissions desde el backend)
  const permissions = computed(() => user.value?.permissions ?? [])

  // Uso: auth.can('crear-productos')
  const can = (permission) => permissions.value.includes(permission)

  function setSession(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('auth_token', newToken)
    localStorage.setItem('user_data', JSON.stringify(newUser))
  }

  function clearSession() {
    token.value = null
    user.value = null
    challenge.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_data')
  }

  // Si el usuario tiene 2FA el backend devuelve { two_factor, challenge } en vez del token
  function procesarLogin(data) {
    if (data.two_factor) {
      challenge.value = data.challenge
      return data
    }
    setSession(data.token, data.user)
    return data
  }

  async function login(credentials) {
    const { data } = await api.post('/login', credentials)
    return procesarLogin(data)
  }

  async function register(datos) {
    const { data } = await api.post('/register', datos)
    setSession(data.token, data.user)
    return data
  }

  async function requestCode(email) {
    await api.post('/auth/code/request', { email })
  }

  async function verifyCode(email, code) {
    const { data } = await api.post('/auth/code/verify', { email, code })
    return procesarLogin(data)
  }

  async function loginWithGoogle(credential) {
    const { data } = await api.post('/auth/google', { credential })
    return procesarLogin(data)
  }

  async function verifyTwoFactor(code) {
    const { data } = await api.post('/auth/2fa/challenge', {
      challenge: challenge.value,
      code,
    })
    challenge.value = null
    setSession(data.token, data.user)
    return data
  }

  async function setupTwoFactor() {
    const { data } = await api.post('/2fa/setup')
    return data
  }

  function actualizarUsuario(nuevoUsuario) {
    user.value = nuevoUsuario
    localStorage.setItem('user_data', JSON.stringify(nuevoUsuario))
  }

  async function enableTwoFactor(code) {
    const { data } = await api.post('/2fa/enable', { code })
    actualizarUsuario(data.user)
  }

  async function disableTwoFactor(password) {
    const { data } = await api.post('/2fa/disable', { password })
    actualizarUsuario(data.user)
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      clearSession()
    }
  }

  // Refresca usuario y permisos desde el servidor
  async function fetchMe() {
    const { data } = await api.get('/me')
    actualizarUsuario(data.user)
  }

  // Todo lo que se use desde fuera debe estar en este return (y el return va al final)
  return {
    token,
    user,
    isAuthenticated,
    permissions,
    can,
    challenge,
    login,
    register,
    requestCode,
    verifyCode,
    loginWithGoogle,
    verifyTwoFactor,
    setupTwoFactor,
    enableTwoFactor,
    disableTwoFactor,
    logout,
    clearSession,
    fetchMe,
  }
})
