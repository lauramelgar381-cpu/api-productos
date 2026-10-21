<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const code = ref('')
const paso = ref(1) // 1 = pedir código, 2 = verificar
const mensaje = ref('')
const error = ref('')

const pedir = useMutation({
  mutationFn: () => auth.requestCode(email.value),
  onSuccess: () => {
    paso.value = 2
    mensaje.value = 'Si el correo existe, enviamos un código. Revisa tu bandeja.'
  },
  onError: (e) => {
    error.value = e.response?.data?.errors?.email?.[0] || 'No se pudo enviar el código'
  },
})

const verificar = useMutation({
  mutationFn: () => auth.verifyCode(email.value, code.value),
  // Si el usuario tiene 2FA, pasa al paso del código de Google Authenticator
  onSuccess: (data) => router.push(data.two_factor ? '/verificar-2fa' : '/dashboard'),
  onError: (e) => {
    error.value = e.response?.data?.errors?.code?.[0] || 'No se pudo verificar el código'
  },
})

const cargando = computed(() => pedir.isPending.value || verificar.isPending.value)

const enviar = () => {
  error.value = ''
  if (paso.value === 1) pedir.mutate()
  else verificar.mutate()
}
</script>

<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Entrar con código</h2>
        <p v-if="paso === 1">Te enviaremos un código a tu correo</p>
        <p v-else>{{ mensaje }}</p>
      </div>

      <form class="formulario-login" novalidate @submit.prevent="enviar">
        <div class="campo-formulario">
          <label for="email">Correo Electrónico</label>
          <InputText
            id="email"
            v-model="email"
            type="email"
            :disabled="paso === 2"
            class="input-completo"
          />
        </div>

        <div v-if="paso === 2" class="campo-formulario">
          <label for="code">Código de 6 dígitos</label>
          <InputText
            id="code"
            v-model="code"
            maxlength="6"
            inputmode="numeric"
            class="input-completo"
          />
        </div>

        <Button
          type="submit"
          :label="paso === 1 ? 'Enviar código' : 'Verificar'"
          :loading="cargando"
          class="boton-login"
        />
      </form>

      <Message v-if="error" severity="error" class="mensaje-server">{{ error }}</Message>

      <p class="enlace-alterno">
        <router-link to="/login">Volver al inicio de sesión</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.enlace-alterno {
  text-align: center;
  margin-top: 1.25rem;
}
</style>
