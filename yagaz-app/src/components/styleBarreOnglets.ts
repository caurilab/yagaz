import { couleurs, espacements, rayons } from '../../theme/couleurs';

/**
 * Style partagé de la barre d'onglets flottante en pilule (identité orange,
 * cf. `_reference_design/`) : fond blanc arrondi, ombre douce, détachée du
 * bord de l'écran. Pas de `position: absolute` - la barre reste dans le flux
 * normal (espace réservé sous le contenu), seule sa marge la fait "flotter".
 * Appliqué aux 4 navigations (foyer/dépôt/livreur/mandataire) via `screenOptions`.
 */
export const styleBarreOnglets = {
  tabBarStyle: {
    marginHorizontal: espacements.md,
    marginBottom: espacements.md,
    height: 64,
    borderRadius: rayons.xl,
    backgroundColor: couleurs.carte,
    borderTopWidth: 0,
    paddingTop: espacements.sm,
    paddingBottom: espacements.sm,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 8 },
    elevation: 8,
  },
  tabBarLabelStyle: {
    fontSize: 11,
    fontWeight: '700' as const,
  },
  tabBarItemStyle: {
    borderRadius: rayons.lg,
  },
};
