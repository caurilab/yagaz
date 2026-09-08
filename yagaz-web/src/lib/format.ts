const NUMBER_FORMAT = new Intl.NumberFormat('fr-FR')
const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const DATETIME_FORMAT = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
})

export function formatNombre(valeur: number): string {
  return NUMBER_FORMAT.format(valeur)
}

export function formatDate(iso: string): string {
  return DATE_FORMAT.format(new Date(iso))
}

export function formatDateHeure(iso: string): string {
  return DATETIME_FORMAT.format(new Date(iso))
}
