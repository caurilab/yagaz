import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import {
  ajusterTournee,
  creerTournee,
  getLivreurs,
  getReappros,
  getTournees,
} from '../../api/mandataire'
import { DEMO_DEPOTS_CONSOLIDES, DEMO_FORMATS } from '../../api/fixtures'
import { formatDate } from '../../lib/format'
import type { CreerTourneeLignePayload, StatutTournee } from '../../api/types'
import './TourneeDetail.css'

const STATUT_LABEL: Record<StatutTournee, string> = {
  proposee: 'Proposée',
  validee: 'Validée',
  en_cours: 'En cours',
  terminee: 'Terminée',
}

interface LigneEdition extends CreerTourneeLignePayload {
  cle: string
}

function ligneVersEdition(l: CreerTourneeLignePayload): LigneEdition {
  return { ...l, cle: `${l.depot_uuid}-${l.format_id}-${Math.random().toString(36).slice(2, 8)}` }
}

export function MandataireTourneeDetail() {
  const { uuid } = useParams<{ uuid: string }>()
  const estNouvelle = uuid === 'nouvelle'
  const { organisationCourante } = useAuth()
  const orgUuid = organisationCourante?.uuid ?? ''
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const tourneesQuery = useQuery({
    queryKey: ['mandataire', orgUuid, 'tournees'],
    queryFn: () => getTournees(orgUuid),
    enabled: orgUuid !== '' && !estNouvelle,
  })

  const reapprosQuery = useQuery({
    queryKey: ['mandataire', orgUuid, 'reappros'],
    queryFn: () => getReappros(orgUuid),
    enabled: orgUuid !== '',
  })

  const livreursQuery = useQuery({
    queryKey: ['mandataire', orgUuid, 'livreurs'],
    queryFn: () => getLivreurs(orgUuid),
    enabled: orgUuid !== '',
  })

  const tourneeExistante = useMemo(
    () => tourneesQuery.data?.find((t) => t.uuid === uuid),
    [tourneesQuery.data, uuid],
  )

  const [date, setDate] = useState('2026-09-08')
  const [livreurId, setLivreurId] = useState<number | ''>('')
  const [statut, setStatut] = useState<StatutTournee>('proposee')
  const [lignes, setLignes] = useState<LigneEdition[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [initialise, setInitialise] = useState(false)

  useEffect(() => {
    if (initialise) return
    if (estNouvelle && reapprosQuery.data) {
      // Seules les demandes confirmées par le dépôt sont fermes : ce sont
      // elles que la plateforme propose d'intégrer à une tournée.
      const suggestions: LigneEdition[] = reapprosQuery.data
        .filter((r) => r.statut === 'confirmee')
        .map((r) =>
          ligneVersEdition({
            depot_uuid: r.depot.uuid,
            format_id: r.format.id,
            pleines: r.quantite,
            vides_a_recuperer:
              DEMO_DEPOTS_CONSOLIDES.find((d) => d.uuid === r.depot.uuid)?.stocks.find(
                (s) => s.format_id === r.format.id,
              )?.vides ?? 0,
          }),
        )
      setLignes(suggestions)
      setStatut('validee')
      setInitialise(true)
    } else if (!estNouvelle && tourneeExistante) {
      setDate(tourneeExistante.date)
      setLivreurId(tourneeExistante.livreur_user_id ?? '')
      setStatut(tourneeExistante.statut)
      setLignes(tourneeExistante.lignes.map(ligneVersEdition))
      setInitialise(true)
    }
  }, [estNouvelle, reapprosQuery.data, tourneeExistante, initialise])

  const mutation = useMutation({
    mutationFn: async () => {
      const payloadLignes = lignes.map(({ cle: _cle, ...reste }) => reste)
      if (estNouvelle) {
        return creerTournee(orgUuid, {
          date,
          livreur_user_id: livreurId === '' ? undefined : livreurId,
          lignes: payloadLignes,
        })
      }
      return ajusterTournee(uuid ?? '', {
        statut,
        livreur_user_id: livreurId === '' ? undefined : livreurId,
        lignes: payloadLignes,
      })
    },
    onSuccess: (tournee) => {
      queryClient.invalidateQueries({ queryKey: ['mandataire', orgUuid, 'tournees'] })
      setMessage(
        `Tournée ${estNouvelle ? 'validée' : 'mise à jour'} (mode démo - ${tournee.lignes.length} ligne(s)).`,
      )
      if (estNouvelle) {
        navigate('/mandataire/tournees', { replace: true })
      }
    },
  })

  function majLigne(cle: string, champ: 'pleines' | 'vides_a_recuperer', valeur: number) {
    setLignes((prev) => prev.map((l) => (l.cle === cle ? { ...l, [champ]: Math.max(0, valeur) } : l)))
  }

  function supprimerLigne(cle: string) {
    setLignes((prev) => prev.filter((l) => l.cle !== cle))
  }

  function ajouterLigne() {
    const premierDepot = DEMO_DEPOTS_CONSOLIDES[0]
    const premierFormat = DEMO_FORMATS[0]
    setLignes((prev) => [
      ...prev,
      ligneVersEdition({
        depot_uuid: premierDepot.uuid,
        format_id: premierFormat.id,
        pleines: 10,
        vides_a_recuperer: 10,
      }),
    ])
  }

  function changerDepot(cle: string, depotUuid: string) {
    setLignes((prev) => prev.map((l) => (l.cle === cle ? { ...l, depot_uuid: depotUuid } : l)))
  }

  function changerFormat(cle: string, formatId: number) {
    setLignes((prev) => prev.map((l) => (l.cle === cle ? { ...l, format_id: formatId } : l)))
  }

  const totalPleines = lignes.reduce((sum, l) => sum + l.pleines, 0)
  const totalVides = lignes.reduce((sum, l) => sum + l.vides_a_recuperer, 0)

  const enChargement = (!estNouvelle && tourneesQuery.isLoading) || reapprosQuery.isLoading

  return (
    <div className="tournee-detail">
      <header className="tournee-detail__header">
        <div>
          <Link to="/mandataire/tournees" className="tournee-detail__retour">
            &lt; Retour aux tournées
          </Link>
          <h1>{estNouvelle ? 'Nouvelle tournée' : `Tournée du ${formatDate(tourneeExistante?.date ?? date)}`}</h1>
          <p>
            La plateforme propose les lignes à partir des réappros ; ajustez dépôt, format, pleines à
            déposer et vides à récupérer avant de valider.
          </p>
        </div>
      </header>

      {enChargement ? (
        <p className="tournee-detail__vide">Chargement...</p>
      ) : (
        <>
          <section className="tournee-detail__reglages">
            <label>
              <span>Date</span>
              <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </label>

            <label>
              <span>Livreur</span>
              <select
                value={livreurId}
                onChange={(e) => setLivreurId(e.target.value === '' ? '' : Number(e.target.value))}
              >
                <option value="">Non affecté</option>
                {livreursQuery.data?.map((livreur) => (
                  <option key={livreur.user_id} value={livreur.user_id}>
                    {livreur.nom}
                  </option>
                ))}
              </select>
            </label>

            {!estNouvelle ? (
              <label>
                <span>Statut</span>
                <select value={statut} onChange={(e) => setStatut(e.target.value as StatutTournee)}>
                  {Object.entries(STATUT_LABEL).map(([valeur, libelle]) => (
                    <option key={valeur} value={valeur}>
                      {libelle}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}
          </section>

          <section className="tournee-detail__panel">
            <div className="tournee-detail__panel-header">
              <h2>Lignes de la tournée</h2>
              <button type="button" className="tournee-detail__ajouter" onClick={ajouterLigne}>
                + Ajouter une ligne
              </button>
            </div>

            {lignes.length === 0 ? (
              <p className="tournee-detail__vide">Aucune ligne. Ajoutez un dépôt à visiter.</p>
            ) : (
              <table className="tournee-detail__table">
                <thead>
                  <tr>
                    <th>Dépôt</th>
                    <th>Format</th>
                    <th>Pleines à déposer</th>
                    <th>Vides à récupérer</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {lignes.map((ligne) => (
                    <tr key={ligne.cle}>
                      <td>
                        <select value={ligne.depot_uuid} onChange={(e) => changerDepot(ligne.cle, e.target.value)}>
                          {DEMO_DEPOTS_CONSOLIDES.map((depot) => (
                            <option key={depot.uuid} value={depot.uuid}>
                              {depot.nom}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td>
                        <select
                          value={ligne.format_id}
                          onChange={(e) => changerFormat(ligne.cle, Number(e.target.value))}
                        >
                          {DEMO_FORMATS.map((format) => (
                            <option key={format.id} value={format.id}>
                              {format.code}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td>
                        <input
                          type="number"
                          min={0}
                          value={ligne.pleines}
                          onChange={(e) => majLigne(ligne.cle, 'pleines', Number(e.target.value))}
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          min={0}
                          className="tournee-detail__input-vide"
                          value={ligne.vides_a_recuperer}
                          onChange={(e) => majLigne(ligne.cle, 'vides_a_recuperer', Number(e.target.value))}
                        />
                      </td>
                      <td>
                        <button
                          type="button"
                          className="tournee-detail__supprimer"
                          onClick={() => supprimerLigne(ligne.cle)}
                          aria-label="Retirer cette ligne"
                        >
                          Retirer
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td colSpan={2}>Total</td>
                    <td className="tournee-detail__total">{totalPleines}</td>
                    <td className="tournee-detail__total tournee-detail__total--vides">{totalVides}</td>
                    <td />
                  </tr>
                </tfoot>
              </table>
            )}
          </section>

          <div className="tournee-detail__actions">
            {message ? <p className="tournee-detail__message">{message}</p> : null}
            <button
              type="button"
              className="tournee-detail__valider"
              disabled={mutation.isPending || lignes.length === 0}
              onClick={() => mutation.mutate()}
            >
              {mutation.isPending ? 'Enregistrement...' : estNouvelle ? 'Valider la tournée' : 'Enregistrer les ajustements'}
            </button>
          </div>
        </>
      )}
    </div>
  )
}
