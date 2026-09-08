/**
 * Contexte d'authentification : jeton persistant (expo-secure-store),
 * chargement au démarrage, connexion/inscription/déconnexion.
 * Un 401 renvoyé par le client API déclenche une déconnexion automatique.
 */
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import * as SecureStore from 'expo-secure-store';

import { avecRepliDemo, utilisateurDemo } from '../api/demo';
import { definirJeton, surNonAutorise } from '../api/client';
import * as api from '../api/endpoints';
import type { ReponseAuth, User } from '../api/types';

const CLE_JETON = 'yagaz.jeton';

interface EtatAuth {
  user: User | null;
  chargementInitial: boolean;
  enCours: boolean;
  erreur: string | null;
}

interface ContexteAuthValeur extends EtatAuth {
  estConnecte: boolean;
  connecter: (telephone: string, motDePasse: string) => Promise<void>;
  inscrire: (nom: string, telephone: string, motDePasse: string) => Promise<void>;
  deconnecter: () => Promise<void>;
}

const ContexteAuth = createContext<ContexteAuthValeur | null>(null);

async function memoriserSession(reponse: ReponseAuth) {
  await SecureStore.setItemAsync(CLE_JETON, reponse.token);
  definirJeton(reponse.token);
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [etat, setEtat] = useState<EtatAuth>({
    user: null,
    chargementInitial: true,
    enCours: false,
    erreur: null,
  });

  const deconnecter = useCallback(async () => {
    try {
      await api.deconnexion();
    } catch {
      // Le jeton local est de toute façon purgé, la panne réseau n'empêche pas de se déconnecter.
    }
    definirJeton(null);
    await SecureStore.deleteItemAsync(CLE_JETON);
    setEtat({ user: null, chargementInitial: false, enCours: false, erreur: null });
  }, []);

  // Chargement du jeton persistant au démarrage.
  useEffect(() => {
    (async () => {
      const jeton = await SecureStore.getItemAsync(CLE_JETON);
      if (!jeton) {
        setEtat((precedent) => ({ ...precedent, chargementInitial: false }));
        return;
      }
      definirJeton(jeton);
      try {
        const { user } = await avecRepliDemo(() => api.moi(), { user: utilisateurDemo });
        setEtat({ user, chargementInitial: false, enCours: false, erreur: null });
      } catch {
        definirJeton(null);
        await SecureStore.deleteItemAsync(CLE_JETON);
        setEtat({ user: null, chargementInitial: false, enCours: false, erreur: null });
      }
    })();
  }, []);

  // Déconnexion automatique sur 401 renvoyé par n'importe quel appel.
  useEffect(() => {
    surNonAutorise(() => {
      definirJeton(null);
      SecureStore.deleteItemAsync(CLE_JETON).catch(() => {});
      setEtat({ user: null, chargementInitial: false, enCours: false, erreur: null });
    });
    return () => surNonAutorise(null);
  }, []);

  const connecter = useCallback(async (telephone: string, motDePasse: string) => {
    setEtat((precedent) => ({ ...precedent, enCours: true, erreur: null }));
    try {
      const donneesDemo: ReponseAuth = { token: 'demo-token', user: utilisateurDemo };
      const reponse = await avecRepliDemo(
        () => api.connexion({ telephone, mot_de_passe: motDePasse }),
        donneesDemo
      );
      await memoriserSession(reponse);
      setEtat({ user: reponse.user, chargementInitial: false, enCours: false, erreur: null });
    } catch (erreur) {
      const message = erreur instanceof Error ? erreur.message : 'Connexion impossible.';
      setEtat((precedent) => ({ ...precedent, enCours: false, erreur: message }));
      throw erreur;
    }
  }, []);

  const inscrire = useCallback(async (nom: string, telephone: string, motDePasse: string) => {
    setEtat((precedent) => ({ ...precedent, enCours: true, erreur: null }));
    try {
      const donneesDemo: ReponseAuth = {
        token: 'demo-token',
        user: { ...utilisateurDemo, nom, telephone },
      };
      const reponse = await avecRepliDemo(
        () => api.inscription({ nom, telephone, mot_de_passe: motDePasse }),
        donneesDemo
      );
      await memoriserSession(reponse);
      setEtat({ user: reponse.user, chargementInitial: false, enCours: false, erreur: null });
    } catch (erreur) {
      const message = erreur instanceof Error ? erreur.message : 'Inscription impossible.';
      setEtat((precedent) => ({ ...precedent, enCours: false, erreur: message }));
      throw erreur;
    }
  }, []);

  const valeur = useMemo<ContexteAuthValeur>(
    () => ({
      ...etat,
      estConnecte: etat.user !== null,
      connecter,
      inscrire,
      deconnecter,
    }),
    [etat, connecter, inscrire, deconnecter]
  );

  return <ContexteAuth.Provider value={valeur}>{children}</ContexteAuth.Provider>;
}

export function useAuth(): ContexteAuthValeur {
  const contexte = useContext(ContexteAuth);
  if (!contexte) {
    throw new Error('useAuth doit être utilisé sous AuthProvider');
  }
  return contexte;
}
