import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import LoginAuth from '@/views/auth/LoginAuth.vue'
import RegisterAuth from '@/views/auth/RegisterAuth.vue'
import DashboardView from '@/views/DashboardView.vue'
import LoginCodigo from '@/views/auth/LoginCodigo.vue'
import ReportesView from '@/views/ReportesView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginAuth,
      meta: { title: 'Iniciar sesión', guestOnly: true },
    },
    {
      path: '/login-codigo',
      name: 'login-codigo',
      component: LoginCodigo,
      meta: { title: 'Iniciar sesión con código', guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterAuth,
      meta: { title: 'Crear cuenta', guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { title: 'Dashboard', requiresAuth: true },
    },
    {
      path: '/reportes',
      name: 'reportes',
      component: ReportesView,
      meta: { title: 'Reportes', requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: () => import('@/views/NotFound.vue'),
      meta: { title: 'Página no encontrada' },
    },
  ],
})

// Guard global: un solo lugar para toda la protección
router.beforeEach((to) => {
  const auth = useAuthStore()

  // Ruta protegida sin sesión → login
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  // Ya autenticada intentando entrar al login → dashboard
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

// Título de la pestaña según la ruta
router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | Productos` : 'Productos'
})

export default router
