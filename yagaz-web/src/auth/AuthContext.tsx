import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { fetchMe, login as loginRequest, logout as logoutRequest } from '../api/auth'
import { getAuthToken, clearAuthToken } from '../lib/api'
import type { OrganisationRef, User } from '../api/types'

export type Espace = 'mandataire' | 'distributeur'

const ESPACE_STORAGE_KEY = 'yagaz_espace'

interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  espace: Espace
  setEspace: (espace: Espace) => void
  espacesDisponibles: Espace[]
  organisationCourante: OrganisationRef | null
  login: (telephone: string, motDePasse: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

function espaceValide(value: string | null): value is Espace {
  return value === 'mandataire' || value === 'distributeur'
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [espace, setEspaceState] = useState<Espace>(() => {
    const stored = localStorage.getItem(ESPACE_STORAGE_KEY)
    return espaceValide(stored) ? stored : 'mandataire'
  })

  useEffect(() => {
    const token = getAuthToken()
    if (!token) {
      setIsLoading(false)
      return
    }
    fetchMe()
      .then(setUser)
      .catch(() => {
        clearAuthToken()
        setUser(null)
      })
      .finally(() => setIsLoading(false))
  }, [])

  const espacesDisponibles = useMemo<Espace[]>(() => {
    if (!user) return []
    const roles = new Set(user.roles.map((r) => r.role))
    const disponibles: Espace[] = []
    if (roles.has('mandataire')) disponibles.push('mandataire')
    if (roles.has('distributeur')) disponibles.push('distributeur')
    return disponibles
  }, [user])

  useEffect(() => {
    if (espacesDisponibles.length > 0 && !espacesDisponibles.includes(espace)) {
      setEspaceState(espacesDisponibles[0])
    }
  }, [espacesDisponibles, espace])

  function setEspace(next: Espace) {
    setEspaceState(next)
    localStorage.setItem(ESPACE_STORAGE_KEY, next)
  }

  const organisationCourante = useMemo<OrganisationRef | null>(() => {
    if (!user) return null
    return user.roles.find((r) => r.role === espace)?.organisation ?? null
  }, [user, espace])

  async function login(telephone: string, motDePasse: string) {
    const response = await loginRequest(telephone, motDePasse)
    setUser(response.user)
  }

  async function logout() {
    await logoutRequest()
    setUser(null)
  }

  const value: AuthContextValue = {
    user,
    isAuthenticated: user !== null,
    isLoading,
    espace,
    setEspace,
    espacesDisponibles,
    organisationCourante,
    login,
    logout,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth doit être utilisé dans un AuthProvider')
  }
  return ctx
}
