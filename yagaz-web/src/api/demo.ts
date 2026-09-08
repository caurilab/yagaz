// Mode démo : l'API réelle (yagaz-api) ne tourne pas dans cet environnement.
// Ce flag bascule chaque fonction d'endpoint vers des fixtures typées, pour que
// les dashboards s'affichent et que le build passe. Les vrais appels HTTP
// restent écrits et branchés (voir chaque fichier src/api/*.ts) ; il suffit de
// mettre VITE_DEMO_MODE=false une fois l'API disponible.
export const DEMO_MODE = (import.meta.env.VITE_DEMO_MODE ?? 'true') !== 'false'

/** Simule une latence réseau légère pour un rendu réaliste en mode démo. */
export function withDemoDelay<T>(value: T, ms = 200): Promise<T> {
  return new Promise((resolve) => {
    setTimeout(() => resolve(value), ms)
  })
}
