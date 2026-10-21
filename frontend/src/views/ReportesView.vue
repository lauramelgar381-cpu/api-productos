<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import { useAuthStore } from '@/stores/auth.js'
import { fetchAllProducts } from '@/composables/useProductsExport.js'

const router = useRouter()
const auth = useAuthStore()

// Misma clave que usa el dashboard, así comparten la caché
const { data: productos, isPending } = useQuery({
  queryKey: ['products', 'all'],
  queryFn: fetchAllProducts,
  enabled: computed(() => auth.can('ver-productos')),
})

const cargando = computed(() => auth.can('ver-productos') && isPending.value)

const chartData = computed(() => ({
  labels: (productos.value ?? []).map((p) => p.name),
  datasets: [
    {
      label: 'Stock',
      data: (productos.value ?? []).map((p) => p.stock),
      backgroundColor: '#10b981',
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}
</script>

<template>
  <div class="pagina">
    <div class="encabezado">
      <h1>Reportes</h1>
      <div class="acciones">
        <span>{{ auth.user?.name }}</span>
        <Button
          label="Volver a productos"
          icon="pi pi-arrow-left"
          severity="secondary"
          outlined
          @click="router.push('/dashboard')"
        />
      </div>
    </div>

    <p v-if="!auth.can('ver-productos')" class="mensaje-error">
      No tienes permiso para ver los reportes.
    </p>

    <p v-else-if="cargando">Cargando datos...</p>

    <div v-else-if="productos?.length" class="grafico">
      <h3>Stock por producto</h3>
      <Chart type="bar" :data="chartData" :options="chartOptions" style="height: 24rem" />
    </div>

    <p v-else>No hay productos para mostrar.</p>
  </div>
</template>

<style scoped>
.pagina {
  padding: 1.5rem;
}

.encabezado {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.acciones {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.grafico {
  background: white;
  padding: 1rem;
  border-radius: 8px;
}

.mensaje-error {
  color: #dc2626;
}
</style>
