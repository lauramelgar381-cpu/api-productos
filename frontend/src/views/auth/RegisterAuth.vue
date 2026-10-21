<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const mensajeError = ref('')
const datos = reactive({ name: '', email: '', password: '' })
const errores = reactive({ name: '', email: '', password: '' })

const validar = () => {
  Object.keys(errores).forEach((k) => (errores[k] = ''))
  let ok = true

  if (!datos.name.trim()) {
    errores.name = 'El nombre es requerido'
    ok = false
  }
  if (!datos.email.trim()) {
    errores.email = 'El correo electrónico es requerido'
    ok = false
  } else if (!/\S+@\S+\.\S+/.test(datos.email)) {
    errores.email = 'El formato del correo no es válido'
    ok = false
  }
  if (datos.password.length < 6) {
    errores.password = 'La contraseña debe tener al menos 6 caracteres'
    ok = false
  }
  return ok
}

const registroMutation = useMutation({
  mutationFn: (payload) => auth.register(payload),
  onSuccess: () => router.push('/dashboard'),
  onError: (e) => {
    const erroresApi = e.response?.data?.errors
    if (e.response?.status === 422 && erroresApi) {
      Object.keys(erroresApi).forEach((campo) => {
        if (campo in errores) errores[campo] = erroresApi[campo][0]
      })
      return
    }
    mensajeError.value = e.response
      ? e.response.data?.message || 'No se pudo completar el registro'
      : 'Error de conexión con el servidor'
  },
})

const cargando = computed(() => registroMutation.isPending.value)

const enviar = () => {
  mensajeError.value = ''
  if (!validar()) return
  registroMutation.mutate({ ...datos })
}
</script>

<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Crear cuenta</h2>
        <p>Completa los datos para registrarte</p>
      </div>

      <form class="formulario-login" novalidate @submit.prevent="enviar">
        <div class="campo-formulario">
          <label for="name">Nombre</label>
          <InputText
            id="name"
            v-model="datos.name"
            placeholder="Tu nombre"
            :class="{ 'p-invalido': errores.name }"
            class="input-completo"
          />
          <small v-if="errores.name" class="mensaje-error">{{ errores.name }}</small>
        </div>

        <div class="campo-formulario">
          <label for="email">Correo Electrónico</label>
          <InputText
            id="email"
            v-model="datos.email"
            type="email"
            placeholder="tu@email.com"
            :class="{ 'p-invalido': errores.email }"
            class="input-completo"
          />
          <small v-if="errores.email" class="mensaje-error">{{ errores.email }}</small>
        </div>

        <div class="campo-formulario">
          <label for="password">Contraseña</label>
          <Password
            v-model="datos.password"
            input-id="password"
            placeholder="Mínimo 6 caracteres"
            :feedback="false"
            toggleMask
            :class="{ 'p-invalido': errores.password }"
            class="input-completo password-completo"
          />
          <small v-if="errores.password" class="mensaje-error">{{ errores.password }}</small>
        </div>

        <Button type="submit" label="Registrarme" :loading="cargando" class="boton-login" />
      </form>

      <Message v-if="mensajeError" severity="error" class="mensaje-server">
        {{ mensajeError }}
      </Message>

      <p class="enlace-alterno">
        ¿Ya tienes cuenta? <router-link to="/login">Inicia sesión</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.enlace-alterno {
  text-align: center;
  margin-top: 1.25rem;
}

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
</style>
