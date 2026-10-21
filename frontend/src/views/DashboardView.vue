<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useAuthStore } from '@/stores/auth.js'
import {
  useProductsQuery,
  useCreateProduct,
  useUpdateProduct,
  useDeleteProduct,
} from '@/composables/useProducts.js'
import {
  fetchAllProducts,
  exportCsv,
  exportPdf,
  exportXlsx,
} from '@/composables/useProductsExport.js'

// --- Dependencias ---

// --- Dependencias ---
const router = useRouter()
const auth = useAuthStore()
const queryClient = useQueryClient()
const toast = useToast()
const confirm = useConfirm()

// Refresca usuario y permisos al entrar
onMounted(() => {
  auth.fetchMe().catch(() => {})
})

// --- Tabla: página, búsqueda y orden ---
const page = ref(1)
const perPage = ref(5)
const search = ref('')
const searchDebounced = ref('')
const sort = ref('id')
const dir = ref('asc')

let temporizador
watch(search, (valor) => {
  clearTimeout(temporizador)
  temporizador = setTimeout(() => {
    searchDebounced.value = valor
    page.value = 1
  }, 400)
})

const params = computed(() => ({
  page: page.value,
  per_page: perPage.value,
  search: searchDebounced.value || undefined,
  sort: sort.value,
  dir: dir.value,
}))

const onPage = (e) => {
  page.value = e.page + 1
  perPage.value = e.rows
}

const onSort = (e) => {
  sort.value = e.sortField
  dir.value = e.sortOrder === 1 ? 'asc' : 'desc'
  page.value = 1
}

// --- Datos ---
const { data: respuesta, isPending, isError, error } = useProductsQuery(params)
const productos = computed(() => respuesta.value?.data ?? [])
const total = computed(() => respuesta.value?.total ?? 0)
watch(respuesta, (r) => {
  if (r && r.last_page && page.value > r.last_page) {
    page.value = r.last_page
  }
})
const crear = useCreateProduct()
const actualizar = useUpdateProduct()
const eliminar = useDeleteProduct()

// Todos los productos (para gráfico y exportación).
// La clave empieza por 'products', así que invalidateQueries también la refresca.
const { data: todos } = useQuery({
  queryKey: ['products', 'all'],
  queryFn: fetchAllProducts,
  enabled: computed(() => auth.can('ver-productos')),
})

const chartData = computed(() => ({
  labels: (todos.value ?? []).map((p) => p.name),
  datasets: [
    { label: 'Stock', data: (todos.value ?? []).map((p) => p.stock), backgroundColor: '#10b981' },
  ],
}))


const exportando = ref(false)

const exportar = async (tipo) => {
  exportando.value = true
  try {
    const lista = todos.value ?? (await fetchAllProducts())
    if (tipo === 'csv') exportCsv(lista)
    else if (tipo === 'xlsx') await exportXlsx(lista)
    else exportPdf(lista)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: mensajeError(e), life: 5000 })
  } finally {
    exportando.value = false
  }
}


// --- Logout ---
const logoutMutation = useMutation({
  mutationFn: () => auth.logout(),
  onSettled: async () => {
    await router.replace('/login')
    queryClient.clear()
  },
})

// --- Formulario ---
const dialogVisible = ref(false)
const form = reactive({ id: null, name: '', description: '', price: 0, stock: 0 })
const errores = reactive({ name: '', description: '', price: '', stock: '' })

const limpiarErrores = () => {
  Object.keys(errores).forEach((k) => (errores[k] = ''))
}

const validarFormulario = () => {
  limpiarErrores()
  let valido = true

  if (!form.name?.trim()) {
    errores.name = 'El nombre es obligatorio'
    valido = false
  } else if (form.name.length > 255) {
    errores.name = 'El nombre no puede superar 255 caracteres'
    valido = false
  }

  if (form.price === null || form.price === undefined || form.price < 0) {
    errores.price = 'El precio debe ser 0 o mayor'
    valido = false
  }

  if (form.stock === null || form.stock === undefined || form.stock < 0) {
    errores.stock = 'El stock debe ser 0 o mayor'
    valido = false
  }

  return valido
}

const abrirNuevo = () => {
  limpiarErrores()
  Object.assign(form, { id: null, name: '', description: '', price: 0, stock: 0 })
  dialogVisible.value = true
}

const abrirEditar = (producto) => {
  limpiarErrores()
  Object.assign(form, producto)
  dialogVisible.value = true
}

const mensajeError = (e) => {
  if (e.response?.status === 403) return 'No tienes permiso para realizar esta acción'
  const erroresApi = e.response?.data?.errors
  if (erroresApi) return Object.values(erroresApi)[0][0]
  return e.response?.data?.message || 'Ocurrió un error al comunicarse con el servidor'
}

const guardar = () => {
  if (!validarFormulario()) return

  const { id, ...campos } = form
  const opciones = {
    onSuccess: () => {
      dialogVisible.value = false
      toast.add({ severity: 'success', summary: 'Listo', detail: 'Producto guardado', life: 3000 })
    },
    onError: (e) => {
      const erroresServidor = e.response?.data?.errors
      if (e.response?.status === 422 && erroresServidor) {
        Object.keys(erroresServidor).forEach((campo) => {
          if (campo in errores) errores[campo] = erroresServidor[campo][0]
        })
        return
      }
      toast.add({ severity: 'error', summary: 'Error', detail: mensajeError(e), life: 5000 })
    },
  }

  if (id) actualizar.mutate({ id, ...campos }, opciones)
  else crear.mutate(campos, opciones)
}

const confirmarEliminar = (producto) => {
  confirm.require({
    message: `¿Eliminar "${producto.name}"?`,
    header: 'Confirmar',
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    accept: () =>
      eliminar.mutate(producto.id, {
        onSuccess: () => toast.add({ severity: 'success', summary: 'Eliminado', life: 3000 }),
        onError: (e) =>
          toast.add({ severity: 'error', summary: 'Error', detail: mensajeError(e), life: 5000 }),
      }),
  })
}
</script>

<template>
  <div class="pagina">
    <Toast />
    <ConfirmDialog />

    <!-- Encabezado -->
    <div class="encabezado">
      <h1>Productos</h1>
      <div class="acciones">
        <span>{{ auth.user?.name }}</span>
        <template v-if="auth.can('ver-productos')">
            <Button label="CSV" icon="pi pi-file-excel" severity="secondary" outlined:loading="exportando"
               @click="exportar('xlsx')" />
             <Button label="PDF" icon="pi pi-file-pdf" severity="secondary" outlined
             :loading="exportando" @click="exportar('pdf')" />
             <Button
  v-if="auth.can('ver-productos')"
  label="Reportes"
  icon="pi pi-chart-bar"
  severity="secondary"
  outlined
  @click="router.push('/reportes')"
/>
         </template>
        <Button
          v-if="auth.can('crear-productos')"
          label="Nuevo producto"
          icon="pi pi-plus"
          @click="abrirNuevo"
        />
        <Button
          label="Cerrar sesión"
          severity="secondary"
          outlined
          :loading="logoutMutation.isPending.value"
          @click="logoutMutation.mutate()"
        />
      </div>
    </div>

    <!-- Mensajes -->
    <p v-if="!auth.can('ver-productos')" class="mensaje-error">
      No tienes permiso para ver los productos.
    </p>
    <p v-else-if="isError" class="mensaje-error">{{ mensajeError(error) }}</p>

    <!-- Buscador -->
    <div v-if="auth.can('ver-productos')" class="buscador">
      <InputText v-model="search" placeholder="Buscar por nombre o descripción..." />
    </div>

    <!-- Tabla -->
    <DataTable
      v-if="auth.can('ver-productos')"
      :value="productos"
      :loading="isPending"
      lazy
      paginator
      :rows="perPage"
      :first="(page - 1) * perPage"
      :totalRecords="total"
      :rowsPerPageOptions="[5, 10, 20]"
      :sortField="sort"
      :sortOrder="dir === 'asc' ? 1 : -1"
      stripedRows
      @page="onPage"
      @sort="onSort"
    >
      <template #empty>No se encontraron productos.</template>

      <Column field="id" header="#" sortable>
        <template #body="{ index }">
          {{ (page - 1) * perPage + index + 1 }}
        </template>
      </Column>

      <Column field="name" header="Nombre" sortable />
      <Column field="description" header="Descripción" />

      <Column field="price" header="Precio" sortable>
        <template #body="{ data }">
          {{ Number(data.price).toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}
        </template>
      </Column>

      <Column field="stock" header="Stock" sortable />

      <Column
        v-if="auth.can('editar-productos') || auth.can('eliminar-productos')"
        header="Acciones"
      >
        <template #body="{ data }">
          <Button
            v-if="auth.can('editar-productos')"
            icon="pi pi-pencil"
            text
            @click="abrirEditar(data)"
          />
          <Button
            v-if="auth.can('eliminar-productos')"
            icon="pi pi-trash"
            text
            severity="danger"
            @click="confirmarEliminar(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Diálogo de producto -->
    <Dialog
      v-model:visible="dialogVisible"
      modal
      :header="form.id ? 'Editar producto' : 'Nuevo producto'"
      style="width: 28rem"
    >
      <form class="formulario-dialogo" novalidate @submit.prevent="guardar">
        <label>
          Nombre
          <InputText v-model="form.name" :invalid="!!errores.name" fluid />
          <small v-if="errores.name" class="error-campo">{{ errores.name }}</small>
        </label>

        <label>
          Descripción
          <Textarea v-model="form.description" rows="3" :invalid="!!errores.description" fluid />
          <small v-if="errores.description" class="error-campo">{{ errores.description }}</small>
        </label>

        <label>
          Precio
          <InputNumber
            v-model="form.price"
            mode="currency"
            currency="USD"
            locale="en-US"
            :min="0"
            :invalid="!!errores.price"
            fluid
          />
          <small v-if="errores.price" class="error-campo">{{ errores.price }}</small>
        </label>

        <label>
          Stock
          <InputNumber
            v-model="form.stock"
            :min="0"
            :useGrouping="false"
            :invalid="!!errores.stock"
            fluid
          />
          <small v-if="errores.stock" class="error-campo">{{ errores.stock }}</small>
        </label>

        <Button
          type="submit"
          label="Guardar"
          :loading="crear.isPending.value || actualizar.isPending.value"
        />
      </form>
    </Dialog>
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

.buscador {
  margin-bottom: 1rem;
  max-width: 24rem;
}

.buscador :deep(.p-inputtext) {
  width: 100%;
}

.formulario-dialogo {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.formulario-dialogo label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-weight: 600;
}

.mensaje-error {
  color: #dc2626;
}

.error-campo {
  color: #dc2626;
  font-weight: 400;
}
</style>
