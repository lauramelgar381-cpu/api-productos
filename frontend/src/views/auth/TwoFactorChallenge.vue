<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const code = ref('')
const error = ref('')

// Sin challenge (por ejemplo, al recargar la página) no hay nada que verificar
onMounted(() => {
  if (!auth.challenge) router.replace('/login')
})

const verificar = useMutation({
  mutationFn: () => auth.verifyTwoFactor(code.value),
  onSuccess: () => router.push('/dashboard'),
  onError: (e) => {
    error.value = e.response?.data?.errors?.code?.[0] || 'No se pudo verificar el código'
  },
})

const cargando = computed(() => verificar.isPending.value)

const enviar = () => {
  error.value = ''
  if (!/^\d{6}$/.test(code.value)) {
    error.value = 'Ingresa los 6 dígitos de tu aplicación'
    return
  }
  verificar.mutate()
}

const cancelar = () => {
  auth.challenge = null
  router.replace('/login')
}
</script>

<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Verificación en dos pasos</h2>
        <p>Abre Google Authenticator e ingresa el código de 6 dígitos</p>
      </div>

      <form class="formulario-login" novalidate @submit.prevent="enviar">
        <div class="campo-formulario">
          <label for="code">Código de 6 dígitos</label>
          <InputText
            id="code"
            v-model="code"
            maxlength="6"
            inputmode="numeric"
            autocomplete="one-time-code"
            class="input-completo"
          />
        </div>

        <Button type="submit" label="Verificar" :loading="cargando" class="boton-login" />
      </form>

      <Message v-if="error" severity="error" class="mensaje-server">{{ error }}</Message>

      <p class="enlace-alterno">
        <a href="#" @click.prevent="cancelar">Cancelar</a>
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
