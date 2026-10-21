<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['error'])

const router = useRouter()
const auth = useAuthStore()
const contenedor = ref(null)

let intervalo = null

const alResponder = async (respuesta) => {
  try {
    const data = await auth.loginWithGoogle(respuesta.credential)
    router.push(data.two_factor ? '/verificar-2fa' : '/dashboard')
  } catch {
    emit('error', 'No se pudo iniciar sesión con Google')
  }
}

const pintarBoton = () => {
  window.google.accounts.id.initialize({
    client_id: import.meta.env.VITE_GOOGLE_CLIENT_ID,
    callback: alResponder,
  })
  window.google.accounts.id.renderButton(contenedor.value, {
    theme: 'outline',
    size: 'large',
    width: 320,
    text: 'continue_with',
  })
}

onMounted(() => {
  // El script de Google carga con async: puede no estar listo todavía
  if (window.google?.accounts) {
    pintarBoton()
    return
  }

  intervalo = setInterval(() => {
    if (window.google?.accounts) {
      clearInterval(intervalo)
      intervalo = null
      pintarBoton()
    }
  }, 200)
})

onBeforeUnmount(() => {
  if (intervalo) clearInterval(intervalo)
})
</script>

<template>
  <div ref="contenedor" class="boton-google"></div>
</template>

<style scoped>
.boton-google {
  display: flex;
  justify-content: center;
  margin-top: 1rem;
}
</style>
