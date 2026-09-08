/**
 * Mode démo : fixtures typées conformes au contrat (docs/09-contrat-api.md),
 * utilisées quand l'API réelle ne répond pas (cet environnement de build,
 * ou tout simplement le foyer hors ligne). Les vrais appels API restent
 * branchés en priorité - voir `avecRepliDemo` dans `src/data/DonneesContext.tsx`.
 */
import type {
  Alerte,
  Bouteille,
  Commande,
  CommandeDepot,
  Depot,
  DepotConsolide,
  Format,
  FoyerEnTension,
  FoyerEnTensionLivreur,
  Marque,
  MembreLivreur,
  MesRoles,
  MissionLivreur,
  Notification,
  Paiement,
  Reappro,
  Site,
  StockFormat,
  Tournee,
  User,
} from './types';

/** Active/désactive le repli sur les fixtures quand l'appel réel échoue. */
export const MODE_DEMO = process.env.EXPO_PUBLIC_DEMO !== 'false';

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

/** Marques du référentiel (repli démo - mêmes couleurs que le contrat réel `/api/marques`). */
export const marquesDemo: Marque[] = [
  { id: 1, nom: 'Total', couleur: '#E4032E' },
  { id: 2, nom: 'Oryx', couleur: '#1E63B8' },
  { id: 3, nom: 'Petro Ivoire', couleur: '#2E9E5B' },
  { id: 4, nom: 'Sodigaz', couleur: '#F08A24' },
  { id: 5, nom: 'GESTOCI', couleur: '#7B4A2E' },
];

export const formatsDemo: Format[] = [
  { id: 1, code: 'B6', marque: 'Total', tare_nominale_g: 5200, contenance_gaz_g: 6000, couleur: '#E4032E' },
  { id: 2, code: 'B12', marque: 'Oryx', tare_nominale_g: 14800, contenance_gaz_g: 12500, couleur: '#1E63B8' },
  { id: 3, code: 'B38', marque: 'Petro Ivoire', tare_nominale_g: 34000, contenance_gaz_g: 38000, couleur: '#2E9E5B' },
  { id: 4, code: 'B12', marque: 'Sodigaz', tare_nominale_g: 14700, contenance_gaz_g: 12500, couleur: '#F08A24' },
  { id: 5, code: 'B6', marque: 'GESTOCI', tare_nominale_g: 5100, contenance_gaz_g: 6000, couleur: '#7B4A2E' },
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

// --- Rôles et espaces (Phase 4) ---

/**
 * Fixture "multi-rôle" par défaut : ce compte démo a un foyer, gère un
 * dépôt, et est aussi livreur - de quoi exercer le sélecteur d'espace.
 */
export const rolesDemo: MesRoles = {
  foyer: true,
  depots: [{ uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur' }],
  livreur: true,
  mandataires: [{ uuid: 'org-mandataire-dakar', nom: 'Mandataire Dakar' }],
};

const orgDepotDemoUuid = rolesDemo.depots[0].uuid;

export const depotsDemo: Depot[] = [
  {
    uuid: orgDepotDemoUuid,
    nom: 'Dépôt Sacré-Cœur',
    adresse: 'Route de Ouakam, Dakar',
    distance_km: 1.2,
    disponible: true,
  },
  {
    uuid: 'org-depot-ouakam',
    nom: 'Dépôt Ouakam Plage',
    adresse: 'Corniche Ouest, Dakar',
    distance_km: 3.8,
    disponible: true,
  },
];

// --- Commandes - foyer (contrat 10 §2-3) ---

export const commandesFoyerDemo: Commande[] = [
  {
    uuid: 'commande-a-payer',
    site_uuid: 'site-domicile',
    format: formatsDemo[1],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'confirmee',
    commission_g: 150,
    mode_paiement: 'a_la_livraison',
    statut_paiement: 'en_attente',
    created_at: ilYA(10),
    livraison: null,
  },
  {
    uuid: 'commande-en-livraison',
    site_uuid: 'site-domicile',
    format: formatsDemo[1],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'en_livraison',
    commission_g: 150,
    mode_paiement: 'mobile_money',
    statut_paiement: 'regle',
    created_at: ilYA(90),
    livraison: {
      id: 1001,
      commande_uuid: 'commande-en-livraison',
      livreur_user_id: 'user-livreur-demo',
      livreur_nom: 'Moussa Ndiaye',
      statut: 'en_route',
      vides_recuperes: null,
      created_at: ilYA(40),
    },
  },
  {
    uuid: 'commande-proposition-maman',
    site_uuid: 'site-maman',
    format: formatsDemo[2],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'proposee',
    commission_g: 200,
    mode_paiement: 'a_la_livraison',
    statut_paiement: 'en_attente',
    created_at: ilYA(20),
    livraison: null,
  },
  {
    uuid: 'commande-livree',
    site_uuid: 'site-domicile',
    format: formatsDemo[0],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'livree',
    commission_g: 100,
    mode_paiement: 'a_la_livraison',
    statut_paiement: 'en_attente',
    created_at: ilYA(60 * 24 * 6),
    livraison: {
      id: 998,
      commande_uuid: 'commande-livree',
      livreur_user_id: 'user-livreur-demo',
      livreur_nom: 'Moussa Ndiaye',
      statut: 'vide_recupere',
      vides_recuperes: 1,
      created_at: ilYA(60 * 24 * 6),
    },
  },
];

// --- Dépôt - stock et commandes entrantes (contrat 10 §4) ---

export const depotStocksDemo: StockFormat[] = [
  { format: formatsDemo[0], pleines: 18, vides: 4, seuil_plein_bas: 10 },
  { format: formatsDemo[1], pleines: 6, vides: 21, seuil_plein_bas: 12 },
  { format: formatsDemo[2], pleines: 9, vides: 2, seuil_plein_bas: 5 },
];

export const depotLivreursDemo: MembreLivreur[] = [
  { user_id: 'user-livreur-demo', nom: 'Moussa Ndiaye' },
  { user_id: 'user-livreur-fatou', nom: 'Fatou Sarr' },
];

export const depotCommandesDemo: CommandeDepot[] = [
  {
    uuid: 'commande-a-preparer',
    site_uuid: 'site-domicile',
    site: { uuid: 'site-domicile', nom: 'Aïcha Diallo', adresse: 'Sacré-Cœur, Dakar' },
    format: formatsDemo[1],
    quantite: 2,
    depot_uuid: orgDepotDemoUuid,
    statut: 'confirmee',
    commission_g: 150,
    mode_paiement: 'a_la_livraison',
    statut_paiement: 'en_attente',
    created_at: ilYA(15),
    livraison: null,
  },
  {
    uuid: 'commande-a-affecter',
    site_uuid: 'site-diop',
    site: { uuid: 'site-diop', nom: 'Famille Diop', adresse: 'Mermoz, Dakar' },
    format: formatsDemo[0],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'preparee',
    commission_g: 100,
    mode_paiement: 'a_la_livraison',
    statut_paiement: 'en_attente',
    created_at: ilYA(45),
    livraison: null,
  },
  {
    uuid: 'commande-en-livraison',
    site_uuid: 'site-domicile',
    site: { uuid: 'site-domicile', nom: 'Aïcha Diallo', adresse: 'Sacré-Cœur, Dakar' },
    format: formatsDemo[1],
    quantite: 1,
    depot_uuid: orgDepotDemoUuid,
    statut: 'en_livraison',
    commission_g: 150,
    mode_paiement: 'mobile_money',
    statut_paiement: 'regle',
    created_at: ilYA(90),
    livraison: {
      id: 1001,
      commande_uuid: 'commande-en-livraison',
      livreur_user_id: 'user-livreur-demo',
      livreur_nom: 'Moussa Ndiaye',
      statut: 'en_route',
      vides_recuperes: null,
      created_at: ilYA(40),
    },
  },
];

// --- Déclenchement automatique (ADR 0009) : file dépôt et réappros ---

export const depotFoyersEnTensionDemo: FoyerEnTension[] = [
  {
    site_uuid: 'site-maman',
    nom: 'Chez Maman',
    zone: 'Thiès',
    format: formatsDemo[2],
    distance_km: 2.4,
  },
];

export const depotReapprosDemo: Reappro[] = [
  {
    uuid: 'reappro-b12-sacre-coeur',
    format: formatsDemo[1],
    quantite: 15,
    statut: 'proposee',
    created_at: ilYA(180),
  },
];

// --- Notifications (doc 11 §3, ADR 0008) ---

export const notificationsLivreurDemo: Notification[] = [
  {
    id: 501,
    type: 'tension_foyer_habituel',
    statut: 'emise',
    message: "Un foyer habituel a besoin d'une recharge.",
    created_at: ilYA(5),
    contexte: { site_nom: 'Chez Maman', zone: 'Thiès', format_code: 'B38' },
  },
];

/**
 * File actionnable du livreur habituel (ADR 0008, précision « maillon C »)
 * : cohérente avec `notificationsLivreurDemo` ci-dessus - même foyer
 * (« Chez Maman », Thiès, B38), pour que le rappel et la file pointent
 * démonstrativement vers la même proposition possible.
 */
export const livreurFoyersEnTensionDemo: FoyerEnTensionLivreur[] = [
  {
    site_uuid: 'site-maman',
    nom: 'Chez Maman',
    zone: 'Thiès',
    format: formatsDemo[2],
  },
];

// --- Livreur - missions (contrat 10 §5) ---

export const missionsLivreurDemo: MissionLivreur[] = [
  {
    livraison_id: 1001,
    commande_uuid: 'commande-en-livraison',
    statut: 'en_route',
    format: formatsDemo[1],
    quantite: 1,
    site: { uuid: 'site-domicile', nom: 'Aïcha Diallo - Sacré-Cœur', adresse: 'Sacré-Cœur, Dakar' },
    a_deposer: 1,
    a_recuperer: 1,
    created_at: ilYA(40),
  },
  {
    livraison_id: 1002,
    commande_uuid: 'commande-a-livrer-diop',
    statut: 'affectee',
    format: formatsDemo[0],
    quantite: 1,
    site: { uuid: 'site-diop', nom: 'Famille Diop - Mermoz', adresse: 'Mermoz, Dakar' },
    a_deposer: 1,
    a_recuperer: 1,
    created_at: ilYA(10),
  },
];

// --- Mandataire - dépôts consolidés et tournées (doc 11 §1) ---

function dateJourDemo(decalageJours: number): string {
  const d = new Date(maintenant);
  d.setDate(d.getDate() + decalageJours);
  return d.toISOString().slice(0, 10);
}

export const mandataireDepotsDemo: DepotConsolide[] = [
  {
    uuid: 'org-depot-sacre-coeur',
    nom: 'Dépôt Sacré-Cœur',
    stocks: [
      { format: formatsDemo[0], pleines: 18, vides: 4, seuil_plein_bas: 10 },
      { format: formatsDemo[1], pleines: 6, vides: 21, seuil_plein_bas: 12 },
      { format: formatsDemo[2], pleines: 9, vides: 2, seuil_plein_bas: 5 },
    ],
    derniere_activite_at: ilYA(30),
  },
  {
    uuid: 'org-depot-ouakam',
    nom: 'Dépôt Ouakam Plage',
    stocks: [
      { format: formatsDemo[0], pleines: 4, vides: 14, seuil_plein_bas: 10 },
      { format: formatsDemo[1], pleines: 22, vides: 3, seuil_plein_bas: 12 },
    ],
    derniere_activite_at: ilYA(240),
  },
];

export const mandataireTourneesDemo: Tournee[] = [
  {
    uuid: 'tournee-du-jour',
    date: dateJourDemo(0),
    statut: 'en_cours',
    livreur_user_id: 'user-livreur-demo',
    livreur_nom: 'Moussa Ndiaye',
    lignes: [
      {
        id: 1,
        depot: { uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur' },
        format: formatsDemo[1],
        pleines: 10,
        vides_a_recuperer: 8,
        statut: 'depose',
      },
      {
        id: 2,
        depot: { uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur' },
        format: formatsDemo[0],
        pleines: 15,
        vides_a_recuperer: 5,
        statut: 'depose',
      },
      {
        id: 3,
        depot: { uuid: 'org-depot-ouakam', nom: 'Dépôt Ouakam Plage' },
        format: formatsDemo[1],
        pleines: 20,
        vides_a_recuperer: 3,
        statut: 'a_faire',
      },
    ],
    created_at: ilYA(120),
  },
  {
    uuid: 'tournee-hier',
    date: dateJourDemo(-1),
    statut: 'terminee',
    livreur_user_id: 'user-livreur-demo',
    livreur_nom: 'Moussa Ndiaye',
    lignes: [
      {
        id: 4,
        depot: { uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur' },
        format: formatsDemo[2],
        pleines: 8,
        vides_a_recuperer: 6,
        statut: 'vides_recuperes',
      },
      {
        id: 5,
        depot: { uuid: 'org-depot-ouakam', nom: 'Dépôt Ouakam Plage' },
        format: formatsDemo[0],
        pleines: 12,
        vides_a_recuperer: 9,
        statut: 'vides_recuperes',
      },
    ],
    created_at: ilYA(60 * 24 + 90),
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

// --- Paiement Mobile Money (contrat §2 bis) : repli démo ---

/**
 * Délai avant qu'un paiement Mobile Money démo passe de `initie` à `regle`
 * (le vrai passage se ferait via un webhook opérateur, hors app) - assez
 * court pour que le polling de l'écran le montre en quelques rafraîchissements.
 */
const DELAI_REGLEMENT_PAIEMENT_DEMO_MS = 6000;

const paiementsDemoParCommande = new Map<string, { paiement: Paiement; initieAt: number }>();

function genererReferencePaiementDemo(): string {
  return `DEMO-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;
}

/** Simule l'initiation d'un paiement Mobile Money (repli si l'appel réel échoue). */
export function initierPaiementDemo(commandeUuid: string, montant = 8500, devise = 'XOF'): Paiement {
  const paiement: Paiement = { reference: genererReferencePaiementDemo(), statut: 'initie', montant, devise };
  paiementsDemoParCommande.set(commandeUuid, { paiement, initieAt: Date.now() });
  return paiement;
}

/**
 * Simule le polling du statut : reste `initie` jusqu'à
 * `DELAI_REGLEMENT_PAIEMENT_DEMO_MS`, puis bascule à `regle` (comme le
 * ferait le webhook opérateur réel).
 */
export function statutPaiementDemo(commandeUuid: string): Paiement {
  const etat = paiementsDemoParCommande.get(commandeUuid);
  if (!etat) {
    return { reference: 'DEMO-INCONNU', statut: 'en_attente', montant: 0, devise: 'XOF' };
  }
  if (etat.paiement.statut === 'initie' && Date.now() - etat.initieAt >= DELAI_REGLEMENT_PAIEMENT_DEMO_MS) {
    etat.paiement = { ...etat.paiement, statut: 'regle' };
  }
  return etat.paiement;
}
