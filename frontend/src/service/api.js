import axios from 'axios'


const apiBaseUrl = import.meta.env.VITE_API_URL


if (!apiBaseUrl) {
 console.error('La variable de entorno no está definida, verifica tu archivo .env')
}


const api = axios.create({
 baseURL: apiBaseUrl,
 headers: {
   'Content-Type': 'application/json',
   Accept: 'application/json',
 },
})


api.interceptors.request.use(
 (config) => {
   const token = localStorage.getItem('auth_token')
   if (token) {
     config.headers.Authorization = `Bearer ${token}`
   }
   return config
 },
 (error) => {
   return Promise.reject(error)
 },
)

// Interceptor para manejar errores de autenticación
api.interceptors.response.use(
 (response) => response,
 (error) => {
   const esLogin = error.config.url.includes('/login')
   if (error.response && error.response.status === 401 && !esLogin) {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_data')
     // Redirigir al usuario a la página de login
     window.location.href = '/login?expirada=1'
   }
   return Promise.reject(error)
 },
)
//Sercicios para auth
 // Servicios para auth



export default api



