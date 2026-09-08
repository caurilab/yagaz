/**
 * Réglages > Matériels (registre unifié foyer, ADR 0012) : liste des
 * équipements (balance/pèse-bouteille, capteur de température, écran de
 * cuisine) affectés au foyer, avec statut et dernière activité - et le flux
 * d'ajout (scan QR ou saisie manuelle du code, wifi/bluetooth "bientôt").
 * Câblage : `useDonnees()` (liste + ajout + suppression, repli démo inclus).
 */
import { useRef, useState } from 'react';
import { CameraView, useCameraPermissions } from 'expo-camera';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BadgeStatutEquipement } from '../../components/BadgeStatut';
import { Bouton } from '../../components/Bouton';
import { Champ } from '../../components/Champ';
import { Icone, type NomIcone } from '../../components/icones';
import { useDonnees } from '../../data/DonneesContext';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { formaterDateRelative } from '../../utils/date';
import type { Equipement, TypeEquipement } from '../../api/types';

const TYPES_EQUIPEMENT: TypeEquipement[] = ['balance', 'temperature', 'ecran'];

const LIBELLES_TYPE_EQUIPEMENT: Record<TypeEquipement, string> = {
  balance: 'Pèse-bouteille',
  temperature: 'Capteur de cuisine',
  ecran: 'Afficheur',
};

const ICONES_TYPE_EQUIPEMENT: Record<TypeEquipement, NomIcone> = {
  balance: 'balance',
  temperature: 'thermometre',
  ecran: 'ecran',
};

type EtapeAjout = 'fermee' | 'choix' | 'scan' | 'manuel';

/** Interprète le contenu d'un QR d'équipement : JSON `{type, reference}`, `"type:reference"`, ou référence brute seule. */
function parserQrEquipement(donnee: string): { type: TypeEquipement | null; reference: string } {
  const contenu = donnee.trim();

  try {
    const json = JSON.parse(contenu) as { type?: unknown; reference?: unknown };
    if (json && typeof json.reference === 'string' && json.reference.trim()) {
      const type = TYPES_EQUIPEMENT.includes(json.type as TypeEquipement) ? (json.type as TypeEquipement) : null;
      return { type, reference: json.reference.trim() };
    }
  } catch {
    // Pas du JSON : la référence brute est directement le contenu du QR.
  }

  const correspondance = contenu.match(/^(balance|temperature|ecran)\s*[:|-]\s*(.+)$/i);
  if (correspondance) {
    return { type: correspondance[1].toLowerCase() as TypeEquipement, reference: correspondance[2].trim() };
  }

  return { type: null, reference: contenu };
}

export default function EcranMateriels() {
  const { siteActif, equipements, enregistrerEquipement, supprimerEquipement } = useDonnees();

  const [etapeAjout, setEtapeAjout] = useState<EtapeAjout>('fermee');
  const [typeSaisi, setTypeSaisi] = useState<TypeEquipement>('balance');
  const [referenceSaisie, setReferenceSaisie] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [uuidSuppressionEnCours, setUuidSuppressionEnCours] = useState<string | null>(null);

  function fermerAjout() {
    setEtapeAjout('fermee');
    setReferenceSaisie('');
    setTypeSaisi('balance');
  }

  async function soumettreAjout(type: TypeEquipement, referenceBrute: string) {
    const reference = referenceBrute.trim();
    if (!reference) {
      Alert.alert('Code requis', 'Saisissez ou scannez le code du matériel.');
      return;
    }
    setEnCours(true);
    try {
      await enregistrerEquipement({ type, reference, site_uuid: siteActif?.uuid });
      fermerAjout();
    } catch {
      Alert.alert('Erreur', "Impossible d'ajouter ce matériel pour l'instant. Vérifiez le code et réessayez.");
    } finally {
      setEnCours(false);
    }
  }

  function gererScan(donnee: string) {
    const analyse = parserQrEquipement(donnee);
    setReferenceSaisie(analyse.reference);
    if (analyse.type) {
      setTypeSaisi(analyse.type);
      soumettreAjout(analyse.type, analyse.reference);
    } else {
      // Le QR ne précise pas le type : on demande via le sélecteur avant l'envoi.
      setEtapeAjout('manuel');
    }
  }

  function confirmerSuppression(equipement: Equipement) {
    Alert.alert(
      'Supprimer ce matériel',
      `Voulez-vous vraiment supprimer "${equipement.reference}" ? Cette action est définitive.`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: async () => {
            setUuidSuppressionEnCours(equipement.uuid);
            try {
              await supprimerEquipement(equipement.uuid);
            } catch {
              Alert.alert('Erreur', 'Impossible de supprimer ce matériel pour l\'instant.');
            } finally {
              setUuidSuppressionEnCours(null);
            }
          },
        },
      ]
    );
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.contenu}>
        <Text style={styles.intro}>
          Balance, capteur de température, écran de cuisine : gérez ici le matériel connecté de votre foyer.
        </Text>

        {TYPES_EQUIPEMENT.map((type) => {
          const items = equipements.filter((e) => e.type === type);
          return (
            <View key={type} style={styles.section}>
              <View style={styles.sectionEntete}>
                <Icone nom={ICONES_TYPE_EQUIPEMENT[type]} taille={18} couleur={couleurs.rouge} />
                <Text style={styles.sectionTitre}>{LIBELLES_TYPE_EQUIPEMENT[type]}</Text>
              </View>
              <View style={styles.carte}>
                {items.length === 0 ? (
                  <Text style={styles.texteVideSection}>Aucun appareil de ce type pour l'instant.</Text>
                ) : (
                  items.map((equipement, index) => (
                    <View key={equipement.uuid}>
                      <View style={styles.ligneEquipement}>
                        <View style={styles.ligneEquipementInfo}>
                          <Text style={styles.referenceEquipement} numberOfLines={1}>
                            {equipement.reference}
                          </Text>
                          <Text style={styles.detailEquipement} numberOfLines={1}>
                            {equipement.site ? equipement.site.nom : 'Non affecté à un site'}
                            {equipement.dernier_vu_at ? ` · vu ${formaterDateRelative(equipement.dernier_vu_at)}` : ''}
                          </Text>
                        </View>
                        <BadgeStatutEquipement statut={equipement.statut} />
                        <Pressable
                          onPress={() => confirmerSuppression(equipement)}
                          disabled={uuidSuppressionEnCours === equipement.uuid}
                          hitSlop={10}
                          style={styles.boutonSupprimerLigne}
                          accessibilityRole="button"
                          accessibilityLabel={`Supprimer ${equipement.reference}`}>
                          {uuidSuppressionEnCours === equipement.uuid ? (
                            <ActivityIndicator color={couleurs.danger} size="small" />
                          ) : (
                            <Icone nom="corbeille" taille={18} couleur={couleurs.danger} />
                          )}
                        </Pressable>
                      </View>
                      {index < items.length - 1 ? <View style={styles.separateur} /> : null}
                    </View>
                  ))
                )}
              </View>
            </View>
          );
        })}

        <Bouton titre="Ajouter un matériel" onPress={() => setEtapeAjout('choix')} style={styles.boutonAjouter} />
      </ScrollView>

      <Modal
        visible={etapeAjout !== 'fermee'}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={fermerAjout}>
        <SafeAreaView style={styles.modalConteneur} edges={['top', 'bottom']}>
          <View style={styles.modalEntete}>
            <Text style={styles.modalTitre}>Ajouter un matériel</Text>
            <Pressable onPress={fermerAjout} hitSlop={12} accessibilityRole="button" accessibilityLabel="Fermer">
              <Icone nom="fermer" taille={20} couleur={couleurs.texteDoux} />
            </Pressable>
          </View>

          {etapeAjout === 'choix' ? (
            <View style={styles.choixListe}>
              <OptionAjout icone="qr" titre="Scanner un QR code" onPress={() => setEtapeAjout('scan')} />
              <OptionAjout icone="plus" titre="Saisir le code manuellement" onPress={() => setEtapeAjout('manuel')} />
              <OptionAjout icone="wifi" titre="Wifi" sousTitre="Bientôt disponible" desactive />
              <OptionAjout icone="bluetooth" titre="Bluetooth" sousTitre="Bientôt disponible" desactive />
            </View>
          ) : null}

          {etapeAjout === 'scan' ? (
            <EtapeScan onScanned={gererScan} onAnnuler={() => setEtapeAjout('choix')} />
          ) : null}

          {etapeAjout === 'manuel' ? (
            <ScrollView contentContainerStyle={styles.formulaireManuel} keyboardShouldPersistTaps="handled">
              <Champ
                etiquette="Code du matériel"
                valeur={referenceSaisie}
                onChangeText={setReferenceSaisie}
                placeholder="Ex. BAL-DKR-2201"
                aide="Inscrit sur l'étiquette du boîtier."
              />
              <Text style={styles.etiquetteType}>Type de matériel</Text>
              <View style={styles.rangeeTypes}>
                {TYPES_EQUIPEMENT.map((type) => {
                  const actif = typeSaisi === type;
                  return (
                    <Pressable
                      key={type}
                      style={[styles.chipType, actif && styles.chipTypeActif]}
                      onPress={() => setTypeSaisi(type)}
                      accessibilityRole="button"
                      accessibilityState={{ selected: actif }}>
                      <Icone
                        nom={ICONES_TYPE_EQUIPEMENT[type]}
                        taille={18}
                        couleur={actif ? couleurs.blanc : couleurs.rouge}
                      />
                      <Text style={[styles.chipTypeTexte, actif && styles.chipTypeTexteActif]}>
                        {LIBELLES_TYPE_EQUIPEMENT[type]}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
              <Bouton
                titre="Ajouter"
                onPress={() => soumettreAjout(typeSaisi, referenceSaisie)}
                enCours={enCours}
                style={styles.boutonValiderManuel}
              />
            </ScrollView>
          ) : null}
        </SafeAreaView>
      </Modal>
    </SafeAreaView>
  );
}

function OptionAjout({
  icone,
  titre,
  sousTitre,
  desactive = false,
  onPress,
}: {
  icone: NomIcone;
  titre: string;
  sousTitre?: string;
  desactive?: boolean;
  onPress?: () => void;
}) {
  return (
    <Pressable
      style={({ pressed }) => [
        styles.optionAjout,
        desactive && styles.optionAjoutDesactivee,
        pressed && !desactive && styles.optionAjoutPressee,
      ]}
      onPress={desactive ? undefined : onPress}
      disabled={desactive}
      accessibilityRole="button"
      accessibilityState={{ disabled: desactive }}>
      <View style={styles.optionAjoutIcone}>
        <Icone nom={icone} taille={20} couleur={desactive ? couleurs.grisNeutre : couleurs.rouge} />
      </View>
      <View style={styles.optionAjoutTexte}>
        <Text style={[styles.optionAjoutTitre, desactive && styles.optionAjoutTitreDesactive]}>{titre}</Text>
        {sousTitre ? <Text style={styles.optionAjoutSousTitre}>{sousTitre}</Text> : null}
      </View>
      {!desactive ? <Icone nom="chevron" taille={16} couleur={couleurs.texteDoux} /> : null}
    </Pressable>
  );
}

/** Scan QR (SDK 57 : `CameraView` + `useCameraPermissions`), avec gestion de la permission. */
function EtapeScan({ onScanned, onAnnuler }: { onScanned: (donnee: string) => void; onAnnuler: () => void }) {
  const [permission, demanderPermission] = useCameraPermissions();
  const dejaTraite = useRef(false);

  if (!permission) {
    return (
      <View style={styles.zoneScanCentre}>
        <ActivityIndicator color={couleurs.rouge} />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.zoneScanCentre}>
        <Icone nom="qr" taille={32} couleur={couleurs.texteDoux} />
        <Text style={styles.texteExplicationScan}>
          {permission.canAskAgain
            ? "Autorisez l'accès à la caméra pour scanner le QR code de votre matériel."
            : "L'accès à la caméra est refusé. Autorisez-le dans les réglages de l'appareil."}
        </Text>
        <Bouton
          titre={permission.canAskAgain ? 'Autoriser la caméra' : 'Ouvrir les réglages'}
          onPress={() => (permission.canAskAgain ? demanderPermission() : Linking.openSettings())}
          style={styles.boutonPermissionScan}
        />
        <Bouton titre="Annuler" variante="discret" onPress={onAnnuler} />
      </View>
    );
  }

  return (
    <View style={styles.zoneCamera}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={(resultat) => {
          if (dejaTraite.current) return;
          dejaTraite.current = true;
          onScanned(resultat.data);
        }}
      />
      <View style={styles.cadreScan} pointerEvents="none" />
      <Text style={styles.texteAideScan}>Visez le QR code du matériel</Text>
      <Bouton titre="Annuler" variante="discret" onPress={onAnnuler} style={styles.boutonAnnulerScan} />
    </View>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  intro: {
    fontSize: 14,
    color: couleurs.texteDoux,
    marginBottom: espacements.lg,
    lineHeight: 20,
  },
  section: {
    marginBottom: espacements.lg,
  },
  sectionEntete: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    marginBottom: espacements.sm,
  },
  sectionTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    paddingHorizontal: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 6 },
    elevation: 3,
  },
  texteVideSection: {
    fontSize: 13,
    color: couleurs.texteDoux,
    paddingVertical: espacements.lg,
  },
  ligneEquipement: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    paddingVertical: espacements.md,
  },
  ligneEquipementInfo: {
    flex: 1,
    gap: 2,
  },
  referenceEquipement: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  detailEquipement: {
    fontSize: 12,
    color: couleurs.texteDoux,
  },
  boutonSupprimerLigne: {
    padding: espacements.xs,
  },
  separateur: {
    height: 1,
    backgroundColor: couleurs.bordure,
  },
  boutonAjouter: {
    marginTop: espacements.sm,
  },
  // --- Modal ajout ---
  modalConteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  modalEntete: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: espacements.lg,
    paddingVertical: espacements.md,
  },
  modalTitre: {
    fontSize: 18,
    fontWeight: '800',
    color: couleurs.texte,
  },
  choixListe: {
    padding: espacements.lg,
    gap: espacements.sm,
  },
  optionAjout: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.md,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  optionAjoutPressee: {
    opacity: 0.85,
  },
  optionAjoutDesactivee: {
    opacity: 0.5,
  },
  optionAjoutIcone: {
    width: 40,
    height: 40,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    alignItems: 'center',
    justifyContent: 'center',
  },
  optionAjoutTexte: {
    flex: 1,
  },
  optionAjoutTitre: {
    fontSize: 15,
    fontWeight: '700',
    color: couleurs.texte,
  },
  optionAjoutTitreDesactive: {
    color: couleurs.texteDoux,
  },
  optionAjoutSousTitre: {
    fontSize: 12,
    color: couleurs.texteDoux,
    marginTop: 2,
  },
  formulaireManuel: {
    padding: espacements.lg,
  },
  etiquetteType: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  rangeeTypes: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.sm,
  },
  chipType: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
    minHeight: 48,
    paddingHorizontal: espacements.md,
    borderRadius: rayons.rond,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
  },
  chipTypeActif: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  chipTypeTexte: {
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
  },
  chipTypeTexteActif: {
    color: couleurs.blanc,
  },
  boutonValiderManuel: {
    marginTop: espacements.lg,
  },
  // --- Scan QR ---
  zoneScanCentre: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: espacements.xl,
    gap: espacements.md,
  },
  texteExplicationScan: {
    fontSize: 14,
    color: couleurs.texteDoux,
    textAlign: 'center',
    lineHeight: 20,
  },
  boutonPermissionScan: {
    alignSelf: 'stretch',
  },
  zoneCamera: {
    flex: 1,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cadreScan: {
    position: 'absolute',
    width: 220,
    height: 220,
    borderRadius: rayons.lg,
    borderWidth: 3,
    borderColor: 'rgba(255,255,255,0.85)',
  },
  texteAideScan: {
    position: 'absolute',
    bottom: 120,
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.blanc,
  },
  boutonAnnulerScan: {
    position: 'absolute',
    bottom: 40,
  },
});
