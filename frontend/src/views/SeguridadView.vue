<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import QRCode from 'qrcode'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const qr = ref('') // imagen del QR (data URL)
const secreto = ref('') // clave manual, por si no puede escanear
const code = ref('')
const password = ref('')
const error = ref('')
const exito = ref('')

const primerError = (e, campo, porDefecto) =>
  e.response?.data?.errors?.[campo]?.[0] || e.response?.data?.message || porDefecto

// Paso 1: pedir secreto y dibujar el QR en el navegador
const iniciar = useMutation({
  mutationFn: () => auth.setupTwoFactor(),
  onSuccess: async (data) => {
    secreto.value = data.secret
    qr.value = await QRCode.toDataURL(data.otpauth_url, { width: 220, margin: 1 })
  },
  onError: (e) => {
    error.value = primerError(e, 'code', 'No se pudo iniciar la configuración')
  },
})

// Paso 2: confirmar con un código de la app
const activar = useMutation({
  mutationFn: () => auth.enableTwoFactor(code.value),
  onSuccess: () => {
    qr.value = ''
    secreto.value = ''
    code.value = ''
    exito.value = 'Verificación en dos pasos activada.'
  },
  onError: (e) => {
    error.value = primerError(e, 'code', 'No se pudo activar el 2FA')
  },
})

const desactivar = useMutation({
  mutationFn: () => auth.disableTwoFactor(password.value),
  onSuccess: () => {
    password.value = ''
    exito.value = 'Verificación en dos pasos desactivada.'
  },
  onError: (e) => {
    error.value = primerError(e, 'password', 'No se pudo desactivar el 2FA')
  },
})

const limpiar = () => {
  error.value = ''
  exito.value = ''
}

const empezar = () => {
  limpiar()
  iniciar.mutate()
}

const confirmar = () => {
  limpiar()
  if (!/^\d{6}$/.test(code.value)) {
    error.value = 'Ingresa los 6 dígitos de tu aplicación'
    return
  }
  activar.mutate()
}

const quitar = () => {
  limpiar()
  if (!password.value) {
    error.value = 'Ingresa tu contraseña'
    return
  }
  desactivar.mutate()
}
</script>

<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Seguridad</h2>
        <p>Verificación en dos pasos con Google Authenticator</p>
      </div>

      <!-- 2FA desactivado -->
      <template v-if="!auth.user?.two_factor_enabled">
        <Button
          v-if="!qr"
          label="Activar verificación en dos pasos"
          icon="pi pi-shield"
          :loading="iniciar.isPending.value"
          class="boton-login"
          @click="empezar"
        />

        <div v-else class="paso-qr">
          <p>1. Escanea este QR con Google Authenticator.</p>
          <img :src="qr" alt="Código QR para Google Authenticator" width="220" height="220" />
          <p class="clave">
            ¿No puedes escanear? Clave manual: <strong>{{ secreto }}</strong>
          </p>

          <form class="formulario-login" novalidate @submit.prevent="confirmar">
            <div class="campo-formulario">
              <label for="code">2. Escribe el código de 6 dígitos</label>
              <InputText
                id="code"
                v-model="code"
                maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
                class="input-completo"
              />
            </div>
            <Button
              type="submit"
              label="Activar"
              :loading="activar.isPending.value"
              class="boton-login"
            />
          </form>
        </div>
      </template>

      <!-- 2FA activado -->
      <template v-else>
        <Message severity="success" :closable="false">
          La verificación en dos pasos está activada.
        </Message>

        <form class="formulario-login" novalidate @submit.prevent="quitar">
          <div class="campo-formulario">
            <label for="password">Para desactivarla, confirma tu contraseña</label>
            <InputText
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              class="input-completo"
            />
          </div>
          <Button
            type="submit"
            label="Desactivar"
            severity="danger"
            outlined
            :loading="desactivar.isPending.value"
            class="boton-login"
          />
        </form>
      </template>

      <Message v-if="exito" severity="success" class="mensaje-server">{{ exito }}</Message>
      <Message v-if="error" severity="error" class="mensaje-server">{{ error }}</Message>

      <p class="enlace-alterno">
        <Button label="Volver" icon="pi pi-arrow-left" text @click="router.push('/dashboard')" />
      </p>
    </div>
  </div>
</template>

<style scoped>
.paso-qr {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  text-align: center;
}

.paso-qr .formulario-login {
  width: 100%;
}

.clave {
  font-size: 0.85rem;
  word-break: break-all;
}

.enlace-alterno {
  text-align: center;
  margin-top: 1.25rem;
}
</style>
