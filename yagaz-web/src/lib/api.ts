import axios from 'axios'

const TOKEN_STORAGE_KEY = 'yagaz_token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'https://api.yagaz.test',
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY)
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

export function setAuthToken(token: string) {
  localStorage.setItem(TOKEN_STORAGE_KEY, token)
}

export function clearAuthToken() {
  localStorage.removeItem(TOKEN_STORAGE_KEY)
}

export function getAuthToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY)
}
