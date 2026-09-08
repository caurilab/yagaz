// POST /api/auth/login, POST /api/auth/logout, GET /api/me (docs/09 §Authentification).
// Réponses d'auth : forme plate (pas d'enveloppe `data`).
import { api, clearAuthToken, setAuthToken } from '../lib/api'
import { DEMO_MODE, withDemoDelay } from './demo'
import { DEMO_USER } from './fixtures'
import type { AuthResponse, User } from './types'

export async function login(telephone: string, motDePasse: string): Promise<AuthResponse> {
  if (DEMO_MODE) {
    const response = await withDemoDelay<AuthResponse>({ token: 'demo-token', user: DEMO_USER }, 350)
    setAuthToken(response.token)
    return response
  }
  const { data } = await api.post<AuthResponse>('/api/auth/login', {
    telephone,
    mot_de_passe: motDePasse,
  })
  setAuthToken(data.token)
  return data
}

export async function logout(): Promise<void> {
  if (!DEMO_MODE) {
    await api.post('/api/auth/logout').catch(() => undefined)
  }
  clearAuthToken()
}

export async function fetchMe(): Promise<User> {
  if (DEMO_MODE) {
    return withDemoDelay(DEMO_USER, 100)
  }
  const { data } = await api.get<{ user: User }>('/api/me')
  return data.user
}
