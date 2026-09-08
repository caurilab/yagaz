import { useState } from 'react'
import type { FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { DEMO_MODE } from '../api/demo'
import './Login.css'

interface LocationState {
  from?: { pathname: string }
}

export function Login() {
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [telephone, setTelephone] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [enCours, setEnCours] = useState(false)

  if (isAuthenticated) {
    const state = location.state as LocationState | null
    return <Navigate to={state?.from?.pathname ?? '/'} replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErreur(null)
    setEnCours(true)
    try {
      await login(telephone, motDePasse)
      navigate('/', { replace: true })
    } catch {
      setErreur('Numéro ou mot de passe incorrect.')
    } finally {
      setEnCours(false)
    }
  }

  return (
    <div className="login">
      <div className="login__card">
        <div className="login__logo">
          <span className="login__logo-mark">Y</span>
          <span className="login__logo-text">Yagaz</span>
        </div>
        <h1>Connexion</h1>
        <p className="login__sous-titre">Espace mandataire et distributeur - pilotage web.</p>

        <form className="login__form" onSubmit={handleSubmit}>
          <label className="login__champ">
            <span>Téléphone</span>
            <input
              type="tel"
              value={telephone}
              onChange={(event) => setTelephone(event.target.value)}
              placeholder="+225 07 00 00 00 00"
              autoComplete="tel"
              required
            />
          </label>

          <label className="login__champ">
            <span>Mot de passe</span>
            <input
              type="password"
              value={motDePasse}
              onChange={(event) => setMotDePasse(event.target.value)}
              placeholder="********"
              autoComplete="current-password"
              required
            />
          </label>

          {erreur ? <p className="login__erreur">{erreur}</p> : null}

          <button type="submit" className="login__submit" disabled={enCours}>
            {enCours ? 'Connexion...' : 'Se connecter'}
          </button>
        </form>

        {DEMO_MODE ? (
          <p className="login__demo">
            Mode démo actif - toute combinaison téléphone / mot de passe ouvre le compte de
            démonstration (accès mandataire et distributeur).
          </p>
        ) : null}
      </div>
    </div>
  )
}
