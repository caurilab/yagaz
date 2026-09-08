/**
 * Mode démo : fixtures typées conformes au contrat (docs/09-contrat-api.md),
 * utilisées quand l'API réelle ne répond pas (cet environnement de build,
 * ou tout simplement le foyer hors ligne). Les vrais appels API restent
 * branchés en priorité - voir `avecRepliDemo` dans `src/data/DonneesContext.tsx`.
 */
import type { Alerte, Bouteille, Format, Site, User } from './types';

/** Active/désactive le repli sur les fixtures quand l'appel réel échoue. */
export const MODE_DEMO = true;

const maintenant = new Date();
function ilYA(minutes: number): string {
  return new Date(maintenant.getTime() - minutes * 60_000).toISOString();
}

export const utilisateurDemo: User = {
  uuid: 'user-demo-1',
  nom: 'Aïcha Diallo',
  telephone: '+221771234567',
  langue: 'fr',
};

export const formatsDemo: Format[] = [
  { id: 1, code: 'B6', marque: 'Yagaz Gaz', tare_nominale_g: 5200, contenance_gaz_g: 6000 },
  { id: 2, code: 'B12', marque: 'Yagaz Gaz', tare_nominale_g: 14800, contenance_gaz_g: 12500 },
  { id: 3, code: 'B38', marque: 'Yagaz Gaz', tare_nominale_g: 34000, contenance_gaz_g: 38000 },
];

export const sitesDemo: Site[] = [
  {
    uuid: 'site-domicile',
    nom: 'Domicile',
    adresse: 'Sacré-Cœur, Dakar',
    lat: 14.7167,
    lng: -17.4677,
    niveau_acces: 'proprietaire',
    nb_bouteilles: 3,
    a_alerte_active: true,
  },
  {
    uuid: 'site-maman',
    nom: 'Chez Maman',
    adresse: 'Thiès',
    lat: 14.7833,
    lng: -16.9333,
    niveau_acces: 'lecture',
    nb_bouteilles: 1,
    a_alerte_active: true,
  },
];

export const bouteillesDemo: Record<string, Bouteille[]> = {
  'site-domicile': [
    {
      uuid: 'bouteille-active-cuisine',
      site_uuid: 'site-domicile',
      format: formatsDemo[1],
      role_bouteille: 'active',
      tare_g: 14800,
      tare_source: 'mesuree',
      tare_fiable: true,
      seuil_bas_pct: 20,
      plateau_uid: 'plateau-01',
      created_at: ilYA(60 * 24 * 40),
      niveau: {
        gaz_g: 8400,
        niveau_pct: 67,
        etat: 'correct',
        autonomie_min: 3360,
        autonomie_heures: 56,
        debit_g_par_h: 150,
        tare_fiable: true,
        estimation: false,
        calcule_at: ilYA(8),
        frais: true,
      },
    },
    {
      uuid: 'bouteille-secours-pleine',
      site_uuid: 'site-domicile',
      format: formatsDemo[1],
      role_bouteille: 'secours',
      tare_g: 14750,
      tare_source: 'mesuree',
      tare_fiable: true,
      seuil_bas_pct: 20,
      plateau_uid: null,
      created_at: ilYA(60 * 24 * 5),
      niveau: {
        gaz_g: 12500,
        niveau_pct: 100,
        etat: 'plein',
        autonomie_min: 5000,
        autonomie_heures: 83,
        debit_g_par_h: 150,
        tare_fiable: true,
        estimation: false,
        calcule_at: ilYA(20),
        frais: true,
      },
    },
    {
      uuid: 'bouteille-nouvelle-b6',
      site_uuid: 'site-domicile',
      format: formatsDemo[0],
      role_bouteille: 'secours',
      tare_g: null,
      tare_source: 'nominale',
      tare_fiable: false,
      seuil_bas_pct: 20,
      plateau_uid: 'plateau-02',
      created_at: ilYA(60 * 6),
      niveau: {
        gaz_g: 900,
        niveau_pct: 15,
        etat: 'presque_vide',
        autonomie_min: 360,
        autonomie_heures: 6,
        debit_g_par_h: 150,
        tare_fiable: false,
        estimation: true,
        calcule_at: ilYA(4),
        frais: true,
      },
    },
  ],
  'site-maman': [
    {
      uuid: 'bouteille-maman-active',
      site_uuid: 'site-maman',
      format: formatsDemo[2],
      role_bouteille: 'active',
      tare_g: 34000,
      tare_source: 'mesuree',
      tare_fiable: true,
      seuil_bas_pct: 20,
      plateau_uid: 'plateau-09',
      created_at: ilYA(60 * 24 * 90),
      niveau: {
        gaz_g: 3800,
        niveau_pct: 10,
        etat: 'presque_vide',
        autonomie_min: 1520,
        autonomie_heures: 25,
        debit_g_par_h: 150,
        tare_fiable: true,
        estimation: false,
        calcule_at: ilYA(9 * 60),
        frais: false,
      },
    },
  ],
};

export const alertesDemo: Alerte[] = [
  {
    id: 1,
    site_uuid: 'site-domicile',
    bouteille_uuid: 'bouteille-nouvelle-b6',
    type: 'seuil_bas',
    statut: 'emise',
    message: 'La bouteille de réserve (B6) est sous le seuil bas.',
    created_at: ilYA(4),
  },
  {
    id: 2,
    site_uuid: 'site-maman',
    bouteille_uuid: 'bouteille-maman-active',
    type: 'seuil_bas',
    statut: 'emise',
    message: 'Chez Maman : bouteille active presque vide - dernière valeur connue.',
    created_at: ilYA(9 * 60),
  },
];

export type SourceDonnees = 'api' | 'demo';

/**
 * Exécute l'appel réel et indique sa provenance ; si le flag démo est actif
 * et que l'appel échoue (API non joignable), retombe sur les fixtures.
 * Les vrais appels API restent donc toujours tentés en premier.
 */
export async function executerAvecSource<T>(
  appelReel: () => Promise<T>,
  donneesDemo: T
): Promise<{ data: T; source: SourceDonnees }> {
  try {
    const data = await appelReel();
    return { data, source: 'api' };
  } catch (erreur) {
    if (MODE_DEMO) {
      return { data: donneesDemo, source: 'demo' };
    }
    throw erreur;
  }
}

/** Variante qui ne retourne que les données, quand la provenance importe peu. */
export async function avecRepliDemo<T>(appelReel: () => Promise<T>, donneesDemo: T): Promise<T> {
  const { data } = await executerAvecSource(appelReel, donneesDemo);
  return data;
}
