/**
 * Système de dialogue propre à l'app (remplace la modale native du système
 * d'exploitation) : une carte centrée au design maison (orange, arrondie)
 * pour les confirmations et alertes. Un seul dialogue affiché à la fois -
 * une nouvelle demande remplace la précédente.
 */
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';

import { Bouton } from '../components/Bouton';
import { couleurs, espacements, rayons } from '../../theme/couleurs';

interface OptionsAlerte {
  titre: string;
  message?: string;
  texteBouton?: string;
}

interface OptionsConfirmation {
  titre: string;
  message?: string;
  texteConfirmer?: string;
  texteAnnuler?: string;
  destructif?: boolean;
}

interface EtatDialogue {
  titre: string;
  message?: string;
  boutons: Array<{ texte: string; variante: 'plein' | 'discret'; danger?: boolean; valeur: boolean }>;
  resoudre: (valeur: boolean) => void;
}

interface DialogueContextValeur {
  alerter: (options: OptionsAlerte) => Promise<void>;
  confirmer: (options: OptionsConfirmation) => Promise<boolean>;
}

const DialogueContext = createContext<DialogueContextValeur | undefined>(undefined);

export function DialogueProvider({ children }: { children: ReactNode }) {
  const [dialogue, setDialogue] = useState<EtatDialogue | null>(null);
  // Garde la référence du dialogue courant pour pouvoir résoudre proprement
  // même si un nouveau dialogue vient le remplacer entre-temps.
  const enCoursRef = useRef<EtatDialogue | null>(null);

  const fermer = useCallback((valeur: boolean) => {
    const courant = enCoursRef.current;
    if (!courant) return;
    enCoursRef.current = null;
    setDialogue(null);
    courant.resoudre(valeur);
  }, []);

  const alerter = useCallback((options: OptionsAlerte) => {
    return new Promise<void>((resoudre) => {
      // Un nouveau dialogue remplace le précédent : on le résout à false pour
      // ne jamais laisser une promesse en attente indéfiniment.
      enCoursRef.current?.resoudre(false);
      const etat: EtatDialogue = {
        titre: options.titre,
        message: options.message,
        boutons: [{ texte: options.texteBouton ?? 'OK', variante: 'plein', valeur: true }],
        resoudre: () => resoudre(),
      };
      enCoursRef.current = etat;
      setDialogue(etat);
    });
  }, []);

  const confirmer = useCallback((options: OptionsConfirmation) => {
    return new Promise<boolean>((resoudre) => {
      enCoursRef.current?.resoudre(false);
      const etat: EtatDialogue = {
        titre: options.titre,
        message: options.message,
        boutons: [
          { texte: options.texteAnnuler ?? 'Annuler', variante: 'discret', valeur: false },
          { texte: options.texteConfirmer ?? 'Confirmer', variante: 'plein', danger: options.destructif, valeur: true },
        ],
        resoudre,
      };
      enCoursRef.current = etat;
      setDialogue(etat);
    });
  }, []);

  const valeur = useMemo(() => ({ alerter, confirmer }), [alerter, confirmer]);

  return (
    <DialogueContext.Provider value={valeur}>
      {children}
      <Modal transparent animationType="fade" visible={dialogue !== null} onRequestClose={() => fermer(false)}>
        <Pressable style={styles.fond} onPress={() => fermer(false)}>
          <Pressable style={styles.carte} onPress={() => {}}>
            {dialogue && (
              <>
                <Text style={styles.titre}>{dialogue.titre}</Text>
                {dialogue.message ? <Text style={styles.message}>{dialogue.message}</Text> : null}
                <View style={dialogue.boutons.length > 1 ? styles.rangeeBoutons : styles.colonneBoutons}>
                  {dialogue.boutons.map((bouton) => (
                    <Bouton
                      key={bouton.texte}
                      titre={bouton.texte}
                      variante={bouton.variante}
                      onPress={() => fermer(bouton.valeur)}
                      style={[styles.boutonFlexible, bouton.danger && styles.boutonDanger]}
                    />
                  ))}
                </View>
              </>
            )}
          </Pressable>
        </Pressable>
      </Modal>
    </DialogueContext.Provider>
  );
}

export function useDialogue() {
  const contexte = useContext(DialogueContext);
  if (!contexte) {
    throw new Error('useDialogue doit être utilisé à l\'intérieur de DialogueProvider');
  }
  return contexte;
}

const styles = StyleSheet.create({
  fond: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: espacements.lg,
  },
  carte: {
    width: '100%',
    maxWidth: 400,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
  },
  titre: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  message: {
    fontSize: 15,
    color: couleurs.texteDoux,
    marginBottom: espacements.lg,
    lineHeight: 21,
  },
  colonneBoutons: {
    flexDirection: 'column',
  },
  rangeeBoutons: {
    flexDirection: 'row',
    gap: espacements.sm,
  },
  boutonFlexible: {
    flex: 1,
  },
  boutonDanger: {
    backgroundColor: couleurs.danger,
  },
});
