import { computed } from 'vue'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/service/api'
import { useAuthStore } from '@/stores/auth'


const PRODUCTS_KEY = ['products']

// GET /products
export const useProductsQuery = (params) => {
  const auth = useAuthStore()
  return useQuery({
    queryKey: ['products', params],          // al cambiar params, se vuelve a pedir
    enabled: computed(() => auth.can('ver-productos')),
    placeholderData: keepPreviousData,       // evita parpadeos entre páginas
    queryFn: async () => {
      const { data } = await api.get('/products', { params: params.value })
      return data
    },
  })
}

export const useCreateProduct = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (producto) => api.post('/products', producto),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY }),
  })
}

export const useUpdateProduct = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...cambios }) => api.put(`/products/${id}`, cambios),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY }),
  })
}

export const useDeleteProduct = () => {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.delete(`/products/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY }),
  })
}
