/**
 * Client HTTP bas niveau : base URL, injection du jeton Bearer, timeout,
 * traduction des réponses d'erreur du contrat (422 -> ErreurValidation, etc.)
 * et notification de déconnexion sur 401.
 */
import { ErreurApi } from './types';

const URL_BASE_DEFAUT = 'https://api.yagaz.test';

export const URL_BASE_API = process.env.EXPO_PUBLIC_API_URL ?? URL_BASE_DEFAUT;

const DELAI_TIMEOUT_MS = 12000;

let jetonCourant: string | null = null;
let ecouteurNonAutorise: (() => void) | null = null;

/** Appelé par le contexte d'auth au chargement / après connexion. */
export function definirJeton(jeton: string | null) {
  jetonCourant = jeton;
}

/** Permet au contexte d'auth de réagir à un 401 (déconnexion forcée). */
export function surNonAutorise(ecouteur: (() => void) | null) {
  ecouteurNonAutorise = ecouteur;
}

export type Methode = 'GET' | 'POST' | 'PATCH' | 'DELETE';

interface OptionsRequete {
  methode?: Methode;
  corps?: unknown;
  requiertAuth?: boolean;
}

export async function requeteApi<T>(chemin: string, options: OptionsRequete = {}): Promise<T> {
  const { methode = 'GET', corps, requiertAuth = true } = options;

  const entetes: Record<string, string> = {
    Accept: 'application/json',
  };
  if (corps !== undefined) {
    entetes['Content-Type'] = 'application/json';
  }
  if (requiertAuth && jetonCourant) {
    entetes.Authorization = `Bearer ${jetonCourant}`;
  }

  const controleur = new AbortController();
  const idTimeout = setTimeout(() => controleur.abort(), DELAI_TIMEOUT_MS);

  let reponse: Response;
  try {
    reponse = await fetch(`${URL_BASE_API}/api${chemin}`, {
      method: methode,
      headers: entetes,
      body: corps !== undefined ? JSON.stringify(corps) : undefined,
      signal: controleur.signal,
    });
  } catch (erreur) {
    clearTimeout(idTimeout);
    if (erreur instanceof Error && erreur.name === 'AbortError') {
      throw new ErreurApi(0, "Délai dépassé - vérifiez la connexion réseau.");
    }
    throw new ErreurApi(0, 'Impossible de joindre le serveur.');
  }
  clearTimeout(idTimeout);

  if (reponse.status === 204) {
    return undefined as T;
  }

  let donnees: unknown = null;
  const texte = await reponse.text();
  if (texte) {
    try {
      donnees = JSON.parse(texte);
    } catch {
      donnees = null;
    }
  }

  if (!reponse.ok) {
    const corpsErreur = (donnees ?? {}) as { message?: string; errors?: Record<string, string[]> };
    const message = corpsErreur.message ?? `Erreur ${reponse.status}`;

    if (reponse.status === 401) {
      ecouteurNonAutorise?.();
    }

    throw new ErreurApi(reponse.status, message, corpsErreur.errors);
  }

  return donnees as T;
}
