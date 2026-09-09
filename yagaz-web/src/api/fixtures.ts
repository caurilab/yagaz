// Fixtures typées pour le mode démo (VITE_DEMO_MODE). Conformes aux shapes du
// contrat (docs/11) : chaque fixture respecte les types de `./types`.
import type {
  DemandePoint,
  DepotConsolide,
  Format,
  Granularite,
  Livreur,
  Reappro,
  Tournee,
  User,
  VolumePoint,
  ZoneTension,
} from './types'

// ---- Référentiel ----

export const DEMO_FORMATS: Format[] = [
  { id: 1, code: 'B6', marque: 'Total', tare_nominale_g: 5200, contenance_gaz_g: 6000 },
  { id: 2, code: 'B12', marque: 'Total', tare_nominale_g: 12500, contenance_gaz_g: 12000 },
  { id: 3, code: 'B24', marque: 'Oryx', tare_nominale_g: 22000, contenance_gaz_g: 24000 },
]

export function formatCode(formatId: number): string {
  return DEMO_FORMATS.find((f) => f.id === formatId)?.code ?? `#${formatId}`
}

export const DEMO_ZONES = ['Abidjan', 'Bouaké', 'San-Pédro', 'Yamoussoukro', 'Korhogo', 'Daloa']

export const DEMO_LIVREURS: Livreur[] = [
  { uuid: 'livreur-101', nom: 'Ibrahim Coulibaly' },
  { uuid: 'livreur-102', nom: 'Aya Bamba' },
  { uuid: 'livreur-103', nom: 'Salif Ouattara' },
]

// ---- Utilisateur démo (double casquette mandataire + distributeur) ----

export const DEMO_ORG_MANDATAIRE = {
  uuid: 'org-mandataire-demo',
  nom: 'Mandataire Abidjan Sud',
  type: 'mandataire' as const,
  zone: 'Abidjan',
}

export const DEMO_ORG_DISTRIBUTEUR = {
  uuid: 'org-distributeur-demo',
  nom: "Distributeur Côte d'Ivoire",
  type: 'distributeur' as const,
  zone: null,
}

export const DEMO_USER: User = {
  uuid: 'user-demo-1',
  nom: 'Fatou Koné',
  telephone: '+225 07 00 00 00 01',
  email: null,
  langue: 'fr',
  roles: [
    { role: 'mandataire', organisation: DEMO_ORG_MANDATAIRE },
    { role: 'distributeur', organisation: DEMO_ORG_DISTRIBUTEUR },
  ],
}

// ---- Mandataire : dépôts consolidés ----

interface DepotSeed {
  uuid: string
  nom: string
  zone: string
  derniere_activite_at: string | null
  stocks: Array<{ format_id: number; pleines: number; vides: number; seuil_plein_bas: number }>
}

const DEPOTS_SEED: DepotSeed[] = [
  {
    uuid: 'depot-abobo',
    nom: 'Dépôt Abobo',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T07:40:00Z',
    stocks: [
      { format_id: 1, pleines: 6, vides: 34, seuil_plein_bas: 20 },
      { format_id: 2, pleines: 9, vides: 41, seuil_plein_bas: 25 },
      { format_id: 3, pleines: 14, vides: 12, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-koumassi',
    nom: 'Dépôt Koumassi',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T08:05:00Z',
    stocks: [
      { format_id: 1, pleines: 12, vides: 22, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 7, vides: 33, seuil_plein_bas: 20 },
      { format_id: 3, pleines: 18, vides: 9, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-yopougon',
    nom: 'Dépôt Yopougon',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T06:15:00Z',
    stocks: [
      { format_id: 1, pleines: 28, vides: 10, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 24, vides: 14, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 20, vides: 6, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-cocody',
    nom: 'Dépôt Cocody',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-07T18:20:00Z',
    stocks: [
      { format_id: 1, pleines: 30, vides: 8, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 26, vides: 11, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 22, vides: 5, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-marcory',
    nom: 'Dépôt Marcory',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T09:02:00Z',
    stocks: [
      { format_id: 1, pleines: 9, vides: 30, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 11, vides: 26, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 8, vides: 15, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-treichville',
    nom: 'Dépôt Treichville',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T05:50:00Z',
    stocks: [
      { format_id: 1, pleines: 19, vides: 16, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 21, vides: 13, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 16, vides: 8, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-adjame',
    nom: 'Dépôt Adjamé',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-06T14:10:00Z',
    stocks: [
      { format_id: 1, pleines: 4, vides: 38, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 6, vides: 35, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 10, vides: 14, seuil_plein_bas: 10 },
    ],
  },
  {
    uuid: 'depot-port-bouet',
    nom: 'Dépôt Port-Bouët',
    zone: 'Abidjan',
    derniere_activite_at: '2026-09-08T08:45:00Z',
    stocks: [
      { format_id: 1, pleines: 23, vides: 12, seuil_plein_bas: 15 },
      { format_id: 2, pleines: 19, vides: 15, seuil_plein_bas: 18 },
      { format_id: 3, pleines: 17, vides: 7, seuil_plein_bas: 10 },
    ],
  },
]

export const DEMO_DEPOTS_CONSOLIDES: DepotConsolide[] = DEPOTS_SEED.map((seed) => {
  const stocks = seed.stocks.map((s) => ({
    format_id: s.format_id,
    format_code: formatCode(s.format_id),
    format_marque: DEMO_FORMATS.find((f) => f.id === s.format_id)?.marque ?? '',
    pleines: s.pleines,
    vides: s.vides,
    seuil_plein_bas: s.seuil_plein_bas,
    tension: s.pleines < s.seuil_plein_bas,
  }))
  return {
    uuid: seed.uuid,
    nom: seed.nom,
    zone: seed.zone,
    en_tension: stocks.some((s) => s.tension),
    vides_a_recuperer: stocks.reduce((sum, s) => sum + s.vides, 0),
    derniere_activite_at: seed.derniere_activite_at,
    stocks,
  }
})

export function depotNom(depotUuid: string): string {
  return DEMO_DEPOTS_CONSOLIDES.find((d) => d.uuid === depotUuid)?.nom ?? depotUuid
}

// ---- Réappros (commande origine=depot, cible=mandataire) ----
// Forme alignée sur ReapproResource (yagaz-api) : depot={uuid,nom,zone},
// format=objet Format complet (pas de format_id/format_code à plat).

function reapprosNonTries(): Reappro[] {
  return DEMO_DEPOTS_CONSOLIDES.flatMap((depot) =>
    depot.stocks
      .filter((s) => s.tension)
      .map((s, index) => ({
        uuid: `reappro-${depot.uuid}-${s.format_id}`,
        depot: { uuid: depot.uuid, nom: depot.nom, zone: depot.zone },
        format: DEMO_FORMATS.find((f) => f.id === s.format_id) ?? DEMO_FORMATS[0],
        quantite: s.seuil_plein_bas - s.pleines + 10,
        statut: index % 3 === 0 ? 'confirmee' : ('proposee' as const),
        created_at: '2026-09-08T06:00:00Z',
      })),
  )
}

// L'API réelle renvoie les `confirmee` en tête (voir docs/11 §1).
export const DEMO_REAPPROS: Reappro[] = [...reapprosNonTries()].sort((a, b) => {
  const rang = (r: Reappro) => (r.statut === 'confirmee' ? 0 : 1)
  return rang(a) - rang(b)
})

// ---- Tournées ----

export const DEMO_TOURNEES: Tournee[] = [
  {
    uuid: 'tournee-2026-09-08-a',
    date: '2026-09-08',
    statut: 'proposee',
    livreur_uuid: null,
    livreur_nom: null,
    lignes: [
      { depot_uuid: 'depot-abobo', depot_nom: 'Dépôt Abobo', format_id: 1, format_code: 'B6', pleines: 20, vides_a_recuperer: 34 },
      { depot_uuid: 'depot-abobo', depot_nom: 'Dépôt Abobo', format_id: 2, format_code: 'B12', pleines: 20, vides_a_recuperer: 41 },
      { depot_uuid: 'depot-adjame', depot_nom: 'Dépôt Adjamé', format_id: 1, format_code: 'B6', pleines: 18, vides_a_recuperer: 38 },
    ],
  },
  {
    uuid: 'tournee-2026-09-08-b',
    date: '2026-09-08',
    statut: 'validee',
    livreur_uuid: 'livreur-101',
    livreur_nom: 'Ibrahim Coulibaly',
    lignes: [
      { depot_uuid: 'depot-marcory', depot_nom: 'Dépôt Marcory', format_id: 1, format_code: 'B6', pleines: 15, vides_a_recuperer: 30 },
      { depot_uuid: 'depot-marcory', depot_nom: 'Dépôt Marcory', format_id: 3, format_code: 'B24', pleines: 10, vides_a_recuperer: 15 },
    ],
  },
  {
    uuid: 'tournee-2026-09-08-c',
    date: '2026-09-08',
    statut: 'en_cours',
    livreur_uuid: 'livreur-102',
    livreur_nom: 'Aya Bamba',
    lignes: [
      { depot_uuid: 'depot-koumassi', depot_nom: 'Dépôt Koumassi', format_id: 2, format_code: 'B12', pleines: 16, vides_a_recuperer: 33 },
    ],
  },
  {
    uuid: 'tournee-2026-09-07-a',
    date: '2026-09-07',
    statut: 'terminee',
    livreur_uuid: 'livreur-103',
    livreur_nom: 'Salif Ouattara',
    lignes: [
      { depot_uuid: 'depot-treichville', depot_nom: 'Dépôt Treichville', format_id: 1, format_code: 'B6', pleines: 12, vides_a_recuperer: 16 },
      { depot_uuid: 'depot-port-bouet', depot_nom: 'Dépôt Port-Bouët', format_id: 2, format_code: 'B12', pleines: 14, vides_a_recuperer: 15 },
    ],
  },
  {
    uuid: 'tournee-2026-09-06-a',
    date: '2026-09-06',
    statut: 'terminee',
    livreur_uuid: 'livreur-101',
    livreur_nom: 'Ibrahim Coulibaly',
    lignes: [
      { depot_uuid: 'depot-cocody', depot_nom: 'Dépôt Cocody', format_id: 3, format_code: 'B24', pleines: 8, vides_a_recuperer: 5 },
    ],
  },
]

// ---- Générateur déterministe (pas de dépendance à Math.random) ----

function mulberry32(seed: number) {
  let state = seed
  return function next(): number {
    state |= 0
    state = (state + 0x6d2b79f5) | 0
    let t = Math.imul(state ^ (state >>> 15), 1 | state)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

const AUJOURDHUI = new Date('2026-09-08T00:00:00Z')

function periodesJour(nb: number): string[] {
  return Array.from({ length: nb }, (_, i) => {
    const d = new Date(AUJOURDHUI)
    d.setUTCDate(d.getUTCDate() - (nb - 1 - i))
    return d.toISOString().slice(0, 10)
  })
}

function periodesSemaine(nb: number): string[] {
  return Array.from({ length: nb }, (_, i) => {
    const d = new Date(AUJOURDHUI)
    d.setUTCDate(d.getUTCDate() - (nb - 1 - i) * 7)
    const debutAnnee = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
    const semaine = Math.ceil(((d.getTime() - debutAnnee.getTime()) / 86400000 + debutAnnee.getUTCDay() + 1) / 7)
    return `S${String(semaine).padStart(2, '0')} ${d.getUTCFullYear()}`
  })
}

function periodesMois(nb: number): string[] {
  const MOIS = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jul', 'aoû', 'sep', 'oct', 'nov', 'déc']
  return Array.from({ length: nb }, (_, i) => {
    const d = new Date(AUJOURDHUI)
    d.setUTCMonth(d.getUTCMonth() - (nb - 1 - i))
    return `${MOIS[d.getUTCMonth()]} ${d.getUTCFullYear()}`
  })
}

function periodesPourGranularite(pas: Granularite): string[] {
  if (pas === 'jour') return periodesJour(30)
  if (pas === 'semaine') return periodesSemaine(12)
  return periodesMois(12)
}

// Poids relatifs pour rendre les zones/formats visuellement distincts.
const POIDS_ZONE: Record<string, number> = {
  Abidjan: 1.6,
  Bouaké: 1.0,
  'San-Pédro': 0.8,
  Yamoussoukro: 0.7,
  Korhogo: 0.6,
  Daloa: 0.55,
}

const POIDS_FORMAT: Record<string, number> = { B6: 0.7, B12: 1, B24: 0.5 }

function baseVolume(zone: string, format: string, periodeIndex: number, totalPeriodes: number, rng: () => number): number {
  const poidsZone = POIDS_ZONE[zone] ?? 0.6
  const poidsFormat = POIDS_FORMAT[format] ?? 0.6
  // légère tendance haussière + saisonnalité simple + bruit déterministe
  const tendance = 1 + (periodeIndex / totalPeriodes) * 0.35
  const saison = 1 + 0.15 * Math.sin((periodeIndex / totalPeriodes) * Math.PI * 2)
  const bruit = 0.85 + rng() * 0.3
  return Math.round(38 * poidsZone * poidsFormat * tendance * saison * bruit)
}

function genererDemande(pas: Granularite): DemandePoint[] {
  const periodes = periodesPourGranularite(pas)
  const rng = mulberry32(pas === 'jour' ? 11 : pas === 'semaine' ? 22 : 33)
  const points: DemandePoint[] = []
  periodes.forEach((periode, periodeIndex) => {
    DEMO_ZONES.forEach((zone) => {
      DEMO_FORMATS.forEach((format) => {
        points.push({
          periode,
          zone,
          format_code: format.code,
          volume: baseVolume(zone, format.code, periodeIndex, periodes.length, rng),
        })
      })
    })
  })
  return points
}

const DEMANDE_PAR_GRANULARITE: Record<Granularite, DemandePoint[]> = {
  jour: genererDemande('jour'),
  semaine: genererDemande('semaine'),
  mois: genererDemande('mois'),
}

export function getDemandeFixture(pas: Granularite): DemandePoint[] {
  return DEMANDE_PAR_GRANULARITE[pas]
}

/** Volumes = somme de la demande sur tous les formats, par zone/période. */
function genererVolumes(pas: Granularite): VolumePoint[] {
  const demande = DEMANDE_PAR_GRANULARITE[pas]
  const cles = new Map<string, VolumePoint>()
  for (const point of demande) {
    const cle = `${point.periode}__${point.zone}`
    const existant = cles.get(cle)
    if (existant) {
      existant.volume += point.volume
    } else {
      cles.set(cle, { periode: point.periode, zone: point.zone, volume: point.volume })
    }
  }
  return Array.from(cles.values())
}

const VOLUMES_PAR_GRANULARITE: Record<Granularite, VolumePoint[]> = {
  jour: genererVolumes('jour'),
  semaine: genererVolumes('semaine'),
  mois: genererVolumes('mois'),
}

export function getVolumesFixture(pas: Granularite): VolumePoint[] {
  return VOLUMES_PAR_GRANULARITE[pas]
}

// ---- Tensions par zone ----

export const DEMO_ZONES_TENSION: ZoneTension[] = [
  { zone: 'Abidjan', depots_en_rupture: 4, depots_total: 8, vides_accumules: 191, niveau: 'critique' },
  { zone: 'Bouaké', depots_en_rupture: 2, depots_total: 5, vides_accumules: 64, niveau: 'eleve' },
  { zone: 'San-Pédro', depots_en_rupture: 1, depots_total: 4, vides_accumules: 28, niveau: 'modere' },
  { zone: 'Yamoussoukro', depots_en_rupture: 1, depots_total: 3, vides_accumules: 19, niveau: 'modere' },
  { zone: 'Korhogo', depots_en_rupture: 0, depots_total: 3, vides_accumules: 9, niveau: 'faible' },
  { zone: 'Daloa', depots_en_rupture: 0, depots_total: 2, vides_accumules: 6, niveau: 'faible' },
]
