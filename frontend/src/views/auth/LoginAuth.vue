<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Iniciar Sesión</h2>
        <p>Ingresa tus credenciales para acceder al sistema</p>
      </div>

      <form @submit.prevent="enviarLogin" class="formulario-login">
        <div class="campo-formulario">
          <label for="email">Correo Electrónico</label>
          <InputText
            id="email"
            v-model="credenciales.email"
            type="email"
            placeholder="tu@email.com"
            :class="{ 'p-invalido': errores.email }"
            class="input-completo"
          />
          <small class="mensaje-error" v-if="errores.email">{{ errores.email }}</small>
        </div>

        <div class="campo-formulario">
          <label for="password">Contraseña</label>
          <Password
            input-id="password"
            v-model="credenciales.password"
            placeholder="Ingresa tu contraseña"
            :feedback="false"
            toggleMask
            :class="{ 'p-invalido': errores.password }"
            class="input-completo password-completo"
          />
          <small class="mensaje-error" v-if="errores.password">{{ errores.password }}</small>
        </div>

        <Button type="submit" label="Iniciar Sesión" :loading="cargando" class="boton-login" />
      </form>

      <GoogleButton @error="(m) => (mensajeError = m)" />

      <Message v-if="mensajeError" severity="error" class="mensaje-server">
        {{ mensajeError }}
      </Message>

      <p class="enlace-alterno">
        ¿No tienes cuenta? <router-link to="/register">Regístrate</router-link>
      </p>
      <p class="enlace-alterno">
        ¿Prefieres un código? <router-link to="/login-codigo">Entrar con código por correo</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'
import GoogleButton from '@/components/GoogleButton.vue'

const router = useRouter()
const auth = useAuthStore()
const route = useRoute()

const mensajeError = ref(route.query.expirada ? 'Tu sesión expiró. Inicia sesión de nuevo.' : '')

const credenciales = reactive({
  email: '',
  password: '',
})

const errores = reactive({
  email: '',
  password: '',
})

const validarFormulario = () => {
  let esValido = true

  errores.email = ''
  errores.password = ''

  if (!credenciales.email.trim()) {
    errores.email = 'El correo electrónico es requerido'
    esValido = false
  } else if (!/\S+@\S+\.\S+/.test(credenciales.email)) {
    errores.email = 'El formato del correo no es válido'
    esValido = false
  }

  if (!credenciales.password) {
    errores.password = 'La contraseña es requerida'
    esValido = false
  } else if (credenciales.password.length < 6) {
    errores.password = 'La contraseña debe tener al menos 6 caracteres'
    esValido = false
  }

  return esValido
}

const loginMutation = useMutation({
  mutationFn: (datos) => auth.login(datos),
  // Con 2FA activo el backend devuelve un challenge: pasamos al paso del código
  onSuccess: (data) => router.push(data.two_factor ? '/verificar-2fa' : '/dashboard'),
  onError: (error) => {
    mensajeError.value = error.response
      ? error.response.data?.message || error.response.data?.error || 'Credenciales incorrectas'
      : 'Error de conexión con el servidor'
  },
})

const cargando = computed(() => loginMutation.isPending.value)

const enviarLogin = () => {
  if (!validarFormulario()) return
  mensajeError.value = ''
  loginMutation.mutate({ ...credenciales })
}
</script>

<style scoped>
/* Código para el ojito de la contraseña */
:deep(.p-password) {
  width: 100%;
  position: relative;
}

:deep(.p-password .p-password-input) {
  width: 100%;
  padding-right: 2.5rem;
}

:deep(.p-password-toggle) {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  z-index: 10;
}

.enlace-alterno {
  text-align: center;
  margin-top: 1.25rem;
}
</style>
