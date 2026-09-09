/**
 * Mode démo : fixtures typées conformes au contrat (docs/09-contrat-api.md),
 * utilisées quand l'API réelle ne répond pas (cet environnement de build,
 * ou tout simplement le foyer hors ligne). Les vrais appels API restent
 * branchés en priorité - voir `avecRepliDemo` dans `src/data/DonneesContext.tsx`.
 */
import type {
  Alerte,
  Analyse,
  Bouteille,
  CleEtapeSuivi,
  Commande,
  CommandeDepot,
  CorpsHistorique,
  Depot,
  DepotConsolide,
  Equipement,
  EtapeSuivi,
  EvenementHistorique,
  Format,
  FoyerEnTension,
  FoyerEnTensionLivreur,
  JourCuissonSerie,
  Marque,
  MembreLivreur,
  MesRoles,
  MissionLivreur,
  Notification,
  Paiement,
  Pagination,
  PeriodeAnalyse,
  PeriodeTemperature,
  PieceBouteille,
  PointCuissonHoraire,
  PointSerieConsommation,
  PointTemperatureHoraire,
  Reappro,
  ReapproMandataire,
  RecuPaiement,
  Site,
  StatutCommande,
  StockFormat,
  SuiviCommande,
  TemperatureAnalyse,
  TemperatureSite,
  Tournee,
  User,
} from './types';
import { couleurs } from '../../theme/couleurs';

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
  { id: 2, nom: 'Oryx', couleur: '#29A9CE' },
  { id: 3, nom: 'Petro Ivoire', couleur: '#26307A' },
  { id: 4, nom: 'Sodigaz', couleur: '#F08A24' },
  { id: 5, nom: 'Corlay', couleur: '#2FA84F' },
];

/** Formats réels CI (B6/B12/B32/B35) pour couvrir la silhouette trapue et les hautes en démo. */
export const formatsDemo: Format[] = [
  { id: 1, code: 'B6', marque: 'Total', tare_nominale_g: 7000, contenance_gaz_g: 6000, couleur: '#E4032E' },
  { id: 2, code: 'B12', marque: 'Oryx', tare_nominale_g: 13000, contenance_gaz_g: 12500, couleur: '#29A9CE' },
  { id: 3, code: 'B32', marque: 'Petro Ivoire', tare_nominale_g: 30000, contenance_gaz_g: 32000, couleur: '#26307A' },
  { id: 4, code: 'B12', marque: 'Sodigaz', tare_nominale_g: 13000, contenance_gaz_g: 12500, couleur: '#F08A24' },
  { id: 5, code: 'B35', marque: 'Corlay', tare_nominale_g: 33000, contenance_gaz_g: 35000, couleur: '#2FA84F' },
];

/** Pièces amovibles de bouteille (repli démo - mêmes clés/deltas que le référentiel réel `config/bouteille.php`). */
export const piecesBouteilleDemo: PieceBouteille[] = [
  { cle: 'collerette', libelle: 'Collerette / arceau de protection', delta_g: 250 },
  { cle: 'poignee', libelle: 'Poignée', delta_g: 150 },
  { cle: 'chapeau', libelle: 'Chapeau de valve', delta_g: 80 },
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
    // Balance et capteur de température connectés (cf. `equipementsDemo`) -
    // écran non encore affecté à ce site (ADR 0012, gating).
    a_balance: true,
    a_temperature: true,
    a_ecran: false,
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
    // Aucun équipement affecté à ce site - démontre l'état grisé/CTA.
    a_balance: false,
    a_temperature: false,
    a_ecran: false,
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

// --- Équipements (registre unifié foyer, ADR 0012) : repli démo ---

/**
 * Cohérent avec les capacités `a_balance`/`a_temperature`/`a_ecran` de
 * `sitesDemo` ci-dessus : Domicile a une balance et un capteur de
 * température actifs, un écran encore non affecté attend d'être connecté.
 */
export const equipementsDemo: Equipement[] = [
  {
    uuid: 'equip-balance-domicile',
    type: 'balance',
    reference: 'BAL-DKR-2201',
    statut: 'actif',
    site: { uuid: 'site-domicile', nom: 'Domicile' },
    dernier_vu_at: ilYA(8),
    created_at: ilYA(60 * 24 * 40),
    updated_at: ilYA(8),
  },
  {
    uuid: 'equip-temperature-domicile',
    type: 'temperature',
    reference: 'TEMP-DKR-0091',
    statut: 'actif',
    site: { uuid: 'site-domicile', nom: 'Domicile' },
    dernier_vu_at: ilYA(18),
    created_at: ilYA(60 * 24 * 20),
    updated_at: ilYA(18),
  },
  {
    uuid: 'equip-ecran-a-connecter',
    type: 'ecran',
    reference: 'ECR-DKR-0004',
    statut: 'a_connecter',
    site: null,
    dernier_vu_at: null,
    created_at: ilYA(60 * 3),
    updated_at: ilYA(60 * 3),
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
    format_id: 2,
    marque: 'Total',
  },
  {
    uuid: 'org-depot-ouakam',
    nom: 'Dépôt Ouakam Plage',
    adresse: 'Corniche Ouest, Dakar',
    distance_km: 3.8,
    disponible: true,
    format_id: 2,
    marque: 'Total',
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
    type: 'echange',
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
    type: 'echange',
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
    type: 'echange',
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
    type: 'echange',
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
    type: 'echange',
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
    type: 'echange',
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
    type: 'echange',
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
    contexte: { site_nom: 'Chez Maman', zone: 'Thiès', format_code: 'B32' },
  },
];

/**
 * File actionnable du livreur habituel (ADR 0008, précision « maillon C »)
 * : cohérente avec `notificationsLivreurDemo` ci-dessus - même foyer
 * (« Chez Maman », Thiès, B32), pour que le rappel et la file pointent
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
    zone: 'Sacré-Cœur, Dakar',
    en_tension: true,
    vides_a_recuperer: 21,
    derniere_activite_at: ilYA(30),
    stocks: [
      { format_id: formatsDemo[0].id, format_code: formatsDemo[0].code, format_marque: formatsDemo[0].marque, pleines: 18, vides: 4, seuil_plein_bas: 10, tension: false },
      { format_id: formatsDemo[1].id, format_code: formatsDemo[1].code, format_marque: formatsDemo[1].marque, pleines: 6, vides: 21, seuil_plein_bas: 12, tension: true },
      { format_id: formatsDemo[2].id, format_code: formatsDemo[2].code, format_marque: formatsDemo[2].marque, pleines: 9, vides: 2, seuil_plein_bas: 5, tension: false },
    ],
  },
  {
    uuid: 'org-depot-ouakam',
    nom: 'Dépôt Ouakam Plage',
    zone: 'Ouakam, Dakar',
    en_tension: true,
    vides_a_recuperer: 14,
    derniere_activite_at: ilYA(240),
    stocks: [
      { format_id: formatsDemo[0].id, format_code: formatsDemo[0].code, format_marque: formatsDemo[0].marque, pleines: 4, vides: 14, seuil_plein_bas: 10, tension: true },
      { format_id: formatsDemo[1].id, format_code: formatsDemo[1].code, format_marque: formatsDemo[1].marque, pleines: 22, vides: 3, seuil_plein_bas: 12, tension: false },
    ],
  },
];

// --- Mandataire - réappros des dépôts (parité web, doc 11 §1) : repli démo ---

export const mandataireReapprosDemo: ReapproMandataire[] = [
  {
    uuid: 'reappro-mandataire-b12-sacre-coeur',
    depot: { uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur', zone: 'Sacré-Cœur, Dakar' },
    format: formatsDemo[1],
    quantite: 15,
    statut: 'proposee',
    created_at: ilYA(180),
  },
  {
    uuid: 'reappro-mandataire-b6-ouakam',
    depot: { uuid: 'org-depot-ouakam', nom: 'Dépôt Ouakam Plage', zone: 'Ouakam, Dakar' },
    format: formatsDemo[0],
    quantite: 20,
    statut: 'confirmee',
    created_at: ilYA(300),
  },
  {
    uuid: 'reappro-mandataire-b32-sacre-coeur',
    depot: { uuid: 'org-depot-sacre-coeur', nom: 'Dépôt Sacré-Cœur', zone: 'Sacré-Cœur, Dakar' },
    format: formatsDemo[2],
    quantite: 6,
    statut: 'confirmee',
    created_at: ilYA(720),
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

// --- Historique unifié (foyer, doc 13 §1) : repli démo ---

/**
 * Une quinzaine d'événements variés (recharges, paiements, alertes, cuisson)
 * cohérents avec `commandesFoyerDemo`/`alertesDemo` ci-dessus, triés récents
 * d'abord comme l'exige le contrat.
 */
export const historiqueDemo: EvenementHistorique[] = [
  {
    type: 'session_cuisson',
    date: ilYA(18),
    titre: 'Session de cuisson',
    detail: 'Domicile · 42 min de cuisson enregistrées',
    icone: 'cuisson',
  },
  {
    type: 'alerte_seuil_bas',
    date: ilYA(4),
    titre: 'Seuil bas atteint',
    detail: 'La bouteille de réserve (B6) est sous le seuil bas.',
    statut: 'Nouvelle',
    icone: 'alerte',
  },
  {
    type: 'paiement_regle',
    date: ilYA(80),
    titre: 'Paiement Mobile Money réglé',
    detail: 'Commande B12 - Oryx · 1 bouteille',
    montant: 8500,
    statut: 'Réglé',
    icone: 'paiement',
  },
  {
    type: 'commande_en_livraison',
    date: ilYA(90),
    titre: 'Commande en livraison',
    detail: 'B12 - Oryx · Moussa Ndiaye en route',
    montant: 8500,
    statut: 'En livraison',
    icone: 'commande',
  },
  {
    type: 'session_cuisson',
    date: ilYA(60 * 20),
    titre: 'Session de cuisson',
    detail: 'Domicile · 35 min de cuisson enregistrées',
    icone: 'cuisson',
  },
  {
    type: 'alerte_seuil_bas',
    date: ilYA(9 * 60),
    titre: 'Seuil bas atteint',
    detail: 'Chez Maman : bouteille active presque vide.',
    statut: 'Nouvelle',
    icone: 'alerte',
  },
  {
    type: 'jalon_tare_fiable',
    date: ilYA(60 * 24 * 2),
    titre: 'Tare calibrée fiable',
    detail: 'Bouteille active - la mesure de tare est désormais fiable.',
    icone: 'commande',
  },
  {
    type: 'commande_livree',
    date: ilYA(60 * 24 * 6),
    titre: 'Commande livrée',
    detail: 'B6 - Total · 1 bouteille · vide récupérée',
    montant: 6000,
    statut: 'Livrée',
    icone: 'commande',
  },
  {
    type: 'session_cuisson',
    date: ilYA(60 * 24 * 6 + 3),
    titre: 'Session de cuisson',
    detail: 'Domicile · 51 min de cuisson enregistrées',
    icone: 'cuisson',
  },
  {
    type: 'paiement_initie',
    date: ilYA(60 * 24 * 7),
    titre: 'Paiement Mobile Money initié',
    detail: 'Commande B12 - Oryx · en attente de confirmation opérateur',
    montant: 8500,
    statut: 'Initié',
    icone: 'paiement',
  },
  {
    type: 'commande_confirmee',
    date: ilYA(60 * 24 * 8),
    titre: 'Commande confirmée',
    detail: 'B32 - Petro Ivoire · 1 bouteille',
    montant: 12000,
    statut: 'Confirmée',
    icone: 'commande',
  },
  {
    type: 'session_cuisson',
    date: ilYA(60 * 24 * 9),
    titre: 'Session de cuisson',
    detail: 'Domicile · 28 min de cuisson enregistrées',
    icone: 'cuisson',
  },
  {
    type: 'jalon_bouteille_changee',
    date: ilYA(60 * 24 * 12),
    titre: 'Bouteille changée',
    detail: 'Nouvelle bouteille B6 enregistrée en secours.',
    icone: 'commande',
  },
  {
    type: 'alerte_resolue',
    date: ilYA(60 * 24 * 13),
    titre: 'Alerte résolue',
    detail: 'Seuil bas - résolu après livraison.',
    statut: 'Résolue',
    icone: 'alerte',
  },
  {
    type: 'paiement_regle',
    date: ilYA(60 * 24 * 14),
    titre: 'Paiement à la livraison réglé',
    detail: 'Commande B12 - Oryx · 1 bouteille',
    montant: 8500,
    statut: 'Réglé',
    icone: 'paiement',
  },
];

/**
 * Page démo conforme au contrat `{ data, pagination }` - filtre côté client
 * sur `icone` (le contrat ne détaille pas les valeurs de `type`, voir
 * `IconeEvenementHistorique`) et pagine la liste ci-dessus.
 */
export function historiquePageDemo(
  params: CorpsHistorique = {}
): { data: EvenementHistorique[]; pagination: Pagination } {
  const parPage = params.par_page ?? 15;
  const page = params.page ?? 1;
  const filtres = params.type ? historiqueDemo.filter((e) => e.icone === params.type) : historiqueDemo;
  const debut = (page - 1) * parPage;
  const data = filtres.slice(debut, debut + parPage);
  return {
    data,
    pagination: {
      page,
      par_page: parPage,
      total: filtres.length,
      total_pages: Math.max(1, Math.ceil(filtres.length / parPage)),
    },
  };
}

// --- Analyses (tableau de bord foyer, doc 13 §2) : repli démo ---

function jourISO(decalageJours: number): string {
  const d = new Date(maintenant);
  d.setDate(d.getDate() - decalageJours);
  return d.toISOString().slice(0, 10);
}

/** Série journalière de cuisson démo, motif simple mais non uniforme (pas de `Math.random`, pour rester stable). */
function serieJournaliereDemo(nbJours: number): JourCuissonSerie[] {
  const points: JourCuissonSerie[] = [];
  for (let i = nbJours - 1; i >= 0; i--) {
    const motif = (nbJours - i) % 5;
    const sessions = motif === 0 ? 0 : motif === 4 ? 3 : motif >= 2 ? 2 : 1;
    points.push({ date: jourISO(i), sessions, duree_min: sessions * 22 });
  }
  return points;
}

function serieConsommationJournaliereDemo(nbJours: number): PointSerieConsommation[] {
  const points: PointSerieConsommation[] = [];
  for (let i = nbJours - 1; i >= 0; i--) {
    const base = 0.35 + ((nbJours - i) % 4) * 0.18;
    points.push({ periode: jourISO(i), consommation_kg: Math.round(base * 100) / 100 });
  }
  return points;
}

/** Agrégation mensuelle démo (12 points) pour la période "année". */
function serieConsommationMensuelleDemo(): PointSerieConsommation[] {
  const points: PointSerieConsommation[] = [];
  for (let i = 11; i >= 0; i--) {
    const d = new Date(maintenant);
    d.setMonth(d.getMonth() - i, 1);
    const base = 9 + (i % 5);
    points.push({ periode: d.toISOString().slice(0, 7), consommation_kg: base });
  }
  return points;
}

export const analyseDemoParPeriode: Record<PeriodeAnalyse, Analyse> = {
  semaine: {
    consommation_kg: 3.2,
    consommation_tendance_pct: -8,
    depense_fcfa: 0,
    depense_tendance_pct: 0,
    recharges: { nombre: 0, cout_moyen_fcfa: 0, frequence_jours: 0 },
    repartition: {
      par_bouteille: [
        { libelle: 'B12 - Oryx', valeur: 2.4, couleur: '#1E63B8' },
        { libelle: 'B6 - Total', valeur: 0.8, couleur: '#E4032E' },
      ],
      par_site: [
        { libelle: 'Domicile', valeur: 2.6, couleur: couleurs.rouge },
        { libelle: 'Chez Maman', valeur: 0.6, couleur: couleurs.ambre },
      ],
    },
    jours_cuisine: { nombre: 6, serie_journaliere: serieJournaliereDemo(7) },
    autonomie_moyenne_h: 54,
    projection_prochaine_recharge_jours: 9,
    serie_consommation: serieConsommationJournaliereDemo(7),
  },
  mois: {
    consommation_kg: 13.6,
    consommation_tendance_pct: 5,
    depense_fcfa: 17500,
    depense_tendance_pct: 12,
    recharges: { nombre: 2, cout_moyen_fcfa: 8750, frequence_jours: 14 },
    repartition: {
      par_bouteille: [
        { libelle: 'B12 - Oryx', valeur: 10.1, couleur: '#1E63B8' },
        { libelle: 'B6 - Total', valeur: 2.3, couleur: '#E4032E' },
        { libelle: 'B32 - Petro Ivoire', valeur: 1.2, couleur: '#26307A' },
      ],
      par_site: [
        { libelle: 'Domicile', valeur: 11.4, couleur: couleurs.rouge },
        { libelle: 'Chez Maman', valeur: 2.2, couleur: couleurs.ambre },
      ],
    },
    jours_cuisine: { nombre: 24, serie_journaliere: serieJournaliereDemo(30) },
    autonomie_moyenne_h: 50,
    projection_prochaine_recharge_jours: 6,
    serie_consommation: serieConsommationJournaliereDemo(30),
  },
  annee: {
    consommation_kg: 142,
    consommation_tendance_pct: -3,
    depense_fcfa: 142000,
    depense_tendance_pct: -4,
    recharges: { nombre: 16, cout_moyen_fcfa: 8875, frequence_jours: 22 },
    repartition: {
      par_bouteille: [
        { libelle: 'B12 - Oryx', valeur: 96, couleur: '#1E63B8' },
        { libelle: 'B6 - Total', valeur: 28, couleur: '#E4032E' },
        { libelle: 'B32 - Petro Ivoire', valeur: 18, couleur: '#26307A' },
      ],
      par_site: [
        { libelle: 'Domicile', valeur: 118, couleur: couleurs.rouge },
        { libelle: 'Chez Maman', valeur: 24, couleur: couleurs.ambre },
      ],
    },
    jours_cuisine: { nombre: 210, serie_journaliere: serieJournaliereDemo(90) },
    autonomie_moyenne_h: 48,
    projection_prochaine_recharge_jours: 5,
    serie_consommation: serieConsommationMensuelleDemo(),
  },
};

/** Repli démo des conseils IA (ADR 0013) - offline/sans clé. */
export function insightsDemo(periode: PeriodeAnalyse): { insights: string; periode: PeriodeAnalyse } {
  return {
    periode,
    insights:
      'Aperçu hors connexion : ta consommation reste stable. Garde une bouteille de secours pleine pour ne jamais tomber en panne, et recharge avant de descendre sous 15%.',
  };
}

// --- Température & cuisson (ADR 0011, doc 13 §3) : repli démo ---

export const temperatureDemoParSite: Record<string, TemperatureSite> = {
  'site-domicile': {
    temp_courante_c: 62,
    frais: true,
    cuisson_en_cours: true,
    debut_cuisson_at: ilYA(18),
  },
  'site-maman': {
    temp_courante_c: 24,
    frais: false,
    cuisson_en_cours: false,
    debut_cuisson_at: null,
  },
};

/** Courbe horaire démo : deux pics (déjeuner ~13h, dîner ~20h), creux la nuit - pas de `Math.random`, motif stable. */
function courbeHoraireDemo(base: number, amplitude: number): PointTemperatureHoraire[] {
  const points: PointTemperatureHoraire[] = [];
  for (let heure = 0; heure < 24; heure++) {
    const picDejeuner = Math.exp(-((heure - 13) ** 2) / 8);
    const picDiner = Math.exp(-((heure - 20) ** 2) / 6);
    const valeur = base + amplitude * Math.max(picDejeuner, picDiner * 0.85);
    points.push({ heure, temp_moyenne_c: Math.round(valeur * 10) / 10 });
  }
  return points;
}

/** Histogramme démo des cuissons par heure - concentré déjeuner/dîner. */
function histogrammeCuissonsDemo(): PointCuissonHoraire[] {
  const parHeure: Record<number, number> = { 7: 1, 12: 3, 13: 4, 14: 1, 19: 2, 20: 4, 21: 2 };
  return Array.from({ length: 24 }, (_, heure) => ({ heure, sessions: parHeure[heure] ?? 0 }));
}

/** Repli démo de l'analyse détaillée température (doc 13 §3), une entrée par période. */
export const temperatureAnalyseDemoParPeriode: Record<PeriodeTemperature, TemperatureAnalyse> = {
  jour: {
    courbe_horaire: courbeHoraireDemo(26, 55),
    cuisson_par_heure: histogrammeCuissonsDemo(),
    heure_pointe_cuisson: 13,
    periode_dominante: 'midi',
    frequence: { jours_cuisine: 1, sessions_par_jour: 3, duree_moyenne_min: 28 },
  },
  semaine: {
    courbe_horaire: courbeHoraireDemo(25, 50),
    cuisson_par_heure: histogrammeCuissonsDemo(),
    heure_pointe_cuisson: 13,
    periode_dominante: 'midi',
    frequence: { jours_cuisine: 6, sessions_par_jour: 3, duree_moyenne_min: 26 },
  },
  mois: {
    courbe_horaire: courbeHoraireDemo(24, 48),
    cuisson_par_heure: histogrammeCuissonsDemo(),
    heure_pointe_cuisson: 20,
    periode_dominante: 'soir',
    frequence: { jours_cuisine: 24, sessions_par_jour: 3, duree_moyenne_min: 27 },
  },
};

/** Repli démo de la note de sécurité cuisine (ADR 0013, brique 3) - offline/sans clé. */
export function insightsCuisineDemo(periode: PeriodeTemperature): { insights: string; periode: PeriodeTemperature } {
  return {
    periode,
    insights:
      'Aperçu hors connexion : ta cuisine ne montre rien d\'anormal sur la période. Pense à ne jamais laisser le gaz allumé sans surveillance, surtout lors des cuissons longues.',
  };
}

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

// --- Suivi de commande (contrat API `GET /commandes/{uuid}/suivi`) : repli démo ---

const ORDRE_ETAPES_SUIVI_DEMO: CleEtapeSuivi[] = ['passee', 'confirmee', 'preparee', 'en_livraison', 'livree'];

const LIBELLES_ETAPES_SUIVI_DEMO: Record<CleEtapeSuivi, string> = {
  passee: 'Commande passée',
  confirmee: 'Commande confirmée',
  preparee: 'Commande préparée',
  en_livraison: 'En livraison',
  livree: 'Livrée',
};

function indexEtapeAtteinteDemo(statut: StatutCommande): number {
  switch (statut) {
    case 'confirmee':
      return 1;
    case 'preparee':
      return 2;
    case 'en_livraison':
      return 3;
    case 'livree':
      return 4;
    default:
      return 0;
  }
}

/**
 * Repli démo du suivi d'une commande (même timeline que
 * `App\Services\Commande\SuiviCommande`, dérivée de son statut courant) -
 * dates approximées, pas d'ETA GPS live (même limitation documentée côté API).
 */
export function suiviCommandeDemo(commande: Commande): SuiviCommande {
  const indexAtteint = indexEtapeAtteinteDemo(commande.statut);
  const etapes: EtapeSuivi[] = ORDRE_ETAPES_SUIVI_DEMO.map((cle, position) => ({
    cle,
    libelle: LIBELLES_ETAPES_SUIVI_DEMO[cle],
    atteinte: position <= indexAtteint,
    date: position <= indexAtteint ? ilYA((indexAtteint - position) * 25 + 5) : null,
    courante: position === indexAtteint,
  }));

  const enLivraison = commande.statut === 'en_livraison';

  return {
    etapes,
    statut_courant: commande.statut,
    livraison: {
      statut: commande.livraison?.statut ?? null,
      livreur: commande.livraison?.livreur_nom ?? null,
    },
    depot: { nom: 'Dépôt Sacré-Cœur', telephone: '+225 07 00 00 00 00' },
    distance_km: enLivraison || commande.statut === 'preparee' ? 3.4 : null,
    eta_minutes: enLivraison ? 18 : null,
  };
}

// --- Reçu de paiement (contrat API `GET /commandes/{uuid}/recu`) : repli démo ---

/** Repli démo du reçu d'une commande - même hypothèse de tarif que `PaiementController` (6 500 FCFA/bouteille). */
export function recuCommandeDemo(commande: Commande): RecuPaiement {
  const paiementConnu = paiementsDemoParCommande.get(commande.uuid)?.paiement;
  const montantEstime = commande.quantite * 6_500;
  const statutPaiementDemoValeur = paiementConnu?.statut ?? commande.statut_paiement;
  const nomSite = sitesDemo.find((s) => s.uuid === commande.site_uuid)?.nom ?? 'Mon site';

  return {
    reference: paiementConnu?.reference ?? (statutPaiementDemoValeur === 'regle' ? `DEMO-${commande.uuid.slice(-6).toUpperCase()}` : null),
    montant: paiementConnu?.montant ?? montantEstime,
    devise: paiementConnu?.devise ?? 'XOF',
    statut_paiement: statutPaiementDemoValeur,
    mode_paiement: commande.mode_paiement,
    date: commande.created_at,
    commande: {
      uuid: commande.uuid,
      format: { code: commande.format.code, marque: commande.format.marque },
      quantite: commande.quantite,
      depot: { nom: 'Dépôt Sacré-Cœur' },
    },
    site: { nom: nomSite },
  };
}
