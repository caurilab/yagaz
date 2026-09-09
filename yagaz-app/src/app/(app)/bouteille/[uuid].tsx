import { useEffect, useMemo, useRef, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Alert, Animated, Easing, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { BadgeEtat } from '../../../components/BadgeEtat';
import { BandeauSync } from '../../../components/BandeauSync';
import { Bouton } from '../../../components/Bouton';
import { BouteilleGaz } from '../../../components/BouteilleGaz';
import { Champ } from '../../../components/Champ';
import { EncartConnecterMateriel } from '../../../components/EncartConnecterMateriel';
import { EncartTemperature } from '../../../components/EncartTemperature';
import { Icone } from '../../../components/icones';
import { SelecteurFormat } from '../../../components/SelecteurFormat';
import { useDonnees } from '../../../data/DonneesContext';
import { useTemperatureSite } from '../../../data/useTemperatureSite';
import { couleurs, espacements, rayons } from '../../../../theme/couleurs';
import { formaterAutonomie } from '../../../utils/niveau';
import { couleurPourFormat, teinteMarque } from '../../../utils/marque';
import type { CorpsMajBouteille, Format, RoleBouteille } from '../../../api/types';

const PAS_SEUIL = 5;
const SEUIL_MIN = 5;
const SEUIL_MAX = 50;

export default function EcranDetailBouteille() {
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { sites, bouteilles, formats, marques, modifierBouteille, supprimerBouteille, rafraichir } = useDonnees();
  const bouteille = bouteilles.find((b) => b.uuid === uuid);
  const site = sites.find((s) => s.uuid === bouteille?.site_uuid);
  const aBalance = site?.a_balance ?? true;
  const { temperature } = useTemperatureSite(bouteille?.site_uuid);

  const [modeEdition, setModeEdition] = useState(false);
  const [roleEdit, setRoleEdit] = useState<RoleBouteille>(bouteille?.role_bouteille ?? 'active');
  const [seuilEdit, setSeuilEdit] = useState(bouteille?.seuil_bas_pct ?? 20);
  const [tareTexte, setTareTexte] = useState(bouteille?.tare_g != null ? String(bouteille.tare_g) : '');
  const [codeEdit, setCodeEdit] = useState<string | null>(bouteille?.format.code ?? null);
  const [formatEdit, setFormatEdit] = useState<Format | null>(bouteille?.format ?? null);
  const [enCours, setEnCours] = useState(false);

  const codes = useMemo(() => Array.from(new Set(formats.map((f) => f.code))), [formats]);
  const formatsDuCodeEdit = useMemo(
    () => formats.filter((f) => f.code === codeEdit),
    [formats, codeEdit]
  );

  // Entrée de la bouteille (grandit/avance/se pose) au montage de l'écran.
  const opaciteBouteille = useRef(new Animated.Value(0)).current;
  const echelleBouteille = useRef(new Animated.Value(0.72)).current;
  const translationBouteille = useRef(new Animated.Value(-18)).current;
  const opaciteInfos = useRef(new Animated.Value(0)).current;
  const largeurNiveau = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    opaciteBouteille.setValue(0);
    echelleBouteille.setValue(0.72);
    translationBouteille.setValue(-18);
    opaciteInfos.setValue(0);

    const entreeBouteille = Animated.parallel([
      Animated.spring(opaciteBouteille, { toValue: 1, friction: 6, tension: 90, useNativeDriver: true }),
      Animated.spring(echelleBouteille, { toValue: 1, friction: 6, tension: 90, useNativeDriver: true }),
      Animated.spring(translationBouteille, { toValue: 0, friction: 6, tension: 90, useNativeDriver: true }),
    ]);
    const apparitionInfos = Animated.timing(opaciteInfos, {
      toValue: 1,
      duration: 300,
      delay: 90,
      easing: Easing.out(Easing.ease),
      useNativeDriver: true,
    });

    entreeBouteille.start();
    apparitionInfos.start();

    return () => {
      entreeBouteille.stop();
      apparitionInfos.stop();
    };
  }, [uuid, opaciteBouteille, echelleBouteille, translationBouteille, opaciteInfos]);

  // Remplissage animé de la barre de niveau (jamais quand le niveau est grisé par le gating).
  useEffect(() => {
    if (!bouteille || !aBalance) return;
    const pct = Math.max(0, Math.min(100, bouteille.niveau.niveau_pct));
    largeurNiveau.setValue(0);
    const animationNiveau = Animated.timing(largeurNiveau, {
      toValue: pct,
      duration: 600,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: false,
    });
    animationNiveau.start();
    return () => animationNiveau.stop();
  }, [bouteille?.uuid, bouteille?.niveau.niveau_pct, aBalance, largeurNiveau]);

  if (!bouteille) {
    return (
      <SafeAreaView style={styles.conteneur}>
        <View style={styles.centre}>
          <Text style={styles.texteVide}>Bouteille introuvable.</Text>
        </View>
      </SafeAreaView>
    );
  }

  const { niveau } = bouteille;
  const couleurBouteille = couleurPourFormat(formatEdit ?? bouteille.format, marques);

  function demarrerEdition() {
    setRoleEdit(bouteille!.role_bouteille);
    setSeuilEdit(bouteille!.seuil_bas_pct);
    setTareTexte(bouteille!.tare_g != null ? String(bouteille!.tare_g) : '');
    setCodeEdit(bouteille!.format.code);
    setFormatEdit(bouteille!.format);
    setModeEdition(true);
  }

  function annulerEdition() {
    setModeEdition(false);
  }

  function choisirCodeEdit(code: string) {
    setCodeEdit(code);
    const premier = formats.find((f) => f.code === code) ?? null;
    setFormatEdit(premier);
  }

  async function enregistrerModifications() {
    if (!formatEdit) {
      Alert.alert('Format requis', 'Choisissez le format de la bouteille.');
      return;
    }
    const tareNombre = tareTexte.trim() ? Number(tareTexte.replace(',', '.')) : undefined;
    if (tareTexte.trim() && (Number.isNaN(tareNombre) || (tareNombre ?? 0) <= 0)) {
      Alert.alert('Tare invalide', 'Saisissez un poids en grammes, ou laissez vide.');
      return;
    }

    const corps: CorpsMajBouteille = {};
    if (roleEdit !== bouteille!.role_bouteille) corps.role_bouteille = roleEdit;
    if (seuilEdit !== bouteille!.seuil_bas_pct) corps.seuil_bas_pct = seuilEdit;
    if (formatEdit.id !== bouteille!.format.id) corps.format_id = formatEdit.id;
    if (tareNombre !== undefined && tareNombre !== bouteille!.tare_g) {
      corps.tare_g = tareNombre;
      corps.tare_source = 'saisie';
    }

    if (Object.keys(corps).length === 0) {
      setModeEdition(false);
      return;
    }

    setEnCours(true);
    try {
      await modifierBouteille(bouteille!.uuid, corps);
      await rafraichir();
      setModeEdition(false);
      Alert.alert('Bouteille mise à jour', 'Les modifications ont été enregistrées.');
    } catch {
      Alert.alert('Erreur', "Impossible d'enregistrer les modifications.");
    } finally {
      setEnCours(false);
    }
  }

  function confirmerSuppression() {
    Alert.alert(
      'Supprimer la bouteille',
      'Cette action est définitive. Voulez-vous vraiment supprimer cette bouteille ?',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: async () => {
            setEnCours(true);
            try {
              await supprimerBouteille(bouteille!.uuid);
              await rafraichir();
              router.back();
            } catch {
              Alert.alert('Erreur', 'Impossible de supprimer la bouteille.');
            } finally {
              setEnCours(false);
            }
          },
        },
      ]
    );
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.contenu}>
        <View style={[styles.enTete, { backgroundColor: teinteMarque(couleurBouteille, 0.12) }]}>
          <View style={styles.enTeteFormat}>
            <View style={[styles.pastille, { backgroundColor: couleurBouteille }]} />
            <Text style={styles.format}>
              {bouteille.format.code} - {bouteille.format.marque}
            </Text>
          </View>
          <View style={styles.enTeteActions}>
            {aBalance ? <BadgeEtat etat={niveau.etat} /> : null}
            {!modeEdition ? (
              <Pressable onPress={demarrerEdition} hitSlop={10} style={styles.boutonModifier}>
                <Icone nom="crayon" taille={20} couleur={couleurs.rouge} />
                <Text style={styles.texteModifier}>Modifier</Text>
              </Pressable>
            ) : null}
          </View>
        </View>

        {aBalance && !niveau.frais ? <BandeauSync texte="Dernière valeur connue - hors ligne" variante="alerte" /> : null}

        <View style={styles.carte}>
          <View style={styles.heroNiveau}>
            <Animated.View
              style={{
                opacity: opaciteBouteille,
                transform: [{ scale: echelleBouteille }, { translateY: translationBouteille }],
              }}>
              <BouteilleGaz
                couleur={aBalance ? couleurBouteille : null}
                code={bouteille.format.code}
                marqueNom={bouteille.format.marque}
                niveauPct={aBalance ? niveau.niveau_pct : undefined}
                taille={150}
              />
            </Animated.View>
            <Animated.View style={[styles.blocAutonomie, { opacity: opaciteInfos }]}>
              <Text style={[styles.chiffreAutonomie, !aBalance && styles.chiffreDesactive]}>
                {aBalance ? formaterAutonomie(niveau.autonomie_heures) : '--'}
              </Text>
              <Text style={styles.libelle}>d'autonomie restante</Text>
            </Animated.View>
          </View>

          {aBalance ? (
            <Animated.View style={[styles.blocNiveauAnime, { opacity: opaciteInfos }]}>
              <View style={styles.barreNiveau}>
                <Animated.View
                  style={[
                    styles.barreNiveauRemplie,
                    { width: largeurNiveau.interpolate({ inputRange: [0, 100], outputRange: ['0%', '100%'] }) },
                  ]}
                />
              </View>
              <Text style={styles.texteSecondaire}>Niveau : {niveau.niveau_pct} % ({niveau.gaz_g} g)</Text>
              {niveau.estimation ? (
                <Text style={styles.texteEstimation}>Estimation en cours d'affinage</Text>
              ) : null}
            </Animated.View>
          ) : (
            <Animated.View style={[styles.blocNiveauAnime, { opacity: opaciteInfos }]}>
              <EncartConnecterMateriel texte="Connectez votre pèse-bouteille pour voir le niveau" />
            </Animated.View>
          )}
        </View>

        <View style={styles.blocTemperature}>
          <EncartTemperature
            temperature={temperature}
            siteUuid={bouteille.site_uuid}
            connecte={site?.a_temperature ?? true}
          />
        </View>

        {!modeEdition ? (
          <>
            <Text style={styles.sectionTitre}>Rôle</Text>
            <View style={styles.carteInfo}>
              <Text style={styles.texteInfo}>{bouteille.role_bouteille === 'active' ? 'Active' : 'Secours'}</Text>
            </View>

            <Text style={styles.sectionTitre}>Tare</Text>
            <View style={styles.carteInfo}>
              <Text style={styles.texteInfo}>
                {bouteille.tare_g != null ? `${bouteille.tare_g} g` : 'Non renseignée (valeur nominale utilisée)'}
              </Text>
              <Text style={[styles.statutTare, bouteille.tare_fiable ? styles.statutFiable : styles.statutEnCours]}>
                {bouteille.tare_fiable ? 'Fiable' : "En cours d'affinage"}
              </Text>
            </View>

            <Text style={styles.sectionTitre}>Seuil d'alerte bas</Text>
            <View style={styles.carteInfo}>
              <Text style={styles.texteInfo}>{bouteille.seuil_bas_pct} %</Text>
              <Text style={styles.texteRassurant}>
                Une alerte sera envoyée dès que le niveau descend sous ce seuil.
              </Text>
            </View>

            <Text style={styles.sectionTitre}>Courbe de niveau</Text>
            <View style={styles.placeholderCourbe}>
              <Icone nom="tendance" taille={26} couleur={couleurs.grisNeutre} />
              <Text style={styles.texteVide}>Courbe de niveau bientôt disponible</Text>
            </View>
          </>
        ) : (
          <>
            <Text style={styles.sectionTitre}>Format et marque</Text>
            <SelecteurFormat codes={codes} codeChoisi={codeEdit} onChoisir={choisirCodeEdit} />
            <View style={[styles.rangee, styles.rangeeMarques]}>
              {formatsDuCodeEdit.map((format) => (
                <Pressable
                  key={format.id}
                  style={[styles.chip, styles.chipAvecPastille, formatEdit?.id === format.id && styles.chipActif]}
                  onPress={() => setFormatEdit(format)}>
                  <View style={[styles.pastilleChip, { backgroundColor: couleurPourFormat(format, marques) }]} />
                  <Text style={[styles.chipTexte, formatEdit?.id === format.id && styles.chipTexteActif]}>
                    {format.marque}
                  </Text>
                </Pressable>
              ))}
            </View>

            <Text style={styles.sectionTitre}>Rôle</Text>
            <View style={styles.rangee}>
              <Pressable
                style={[styles.chip, roleEdit === 'active' && styles.chipActif]}
                onPress={() => setRoleEdit('active')}>
                <Text style={[styles.chipTexte, roleEdit === 'active' && styles.chipTexteActif]}>Active</Text>
              </Pressable>
              <Pressable
                style={[styles.chip, roleEdit === 'secours' && styles.chipActif]}
                onPress={() => setRoleEdit('secours')}>
                <Text style={[styles.chipTexte, roleEdit === 'secours' && styles.chipTexteActif]}>Secours</Text>
              </Pressable>
            </View>
            {roleEdit === 'active' && bouteille.role_bouteille !== 'active' ? (
              <Text style={styles.texteRassurant}>
                La bouteille actuellement active passera automatiquement en secours.
              </Text>
            ) : null}

            <Text style={styles.sectionTitre}>Seuil d'alerte bas</Text>
            <View style={styles.carteInfo}>
              <View style={styles.ligneSeuil}>
                <Bouton
                  titre="-"
                  variante="contour"
                  onPress={() => setSeuilEdit((s) => Math.max(SEUIL_MIN, s - PAS_SEUIL))}
                  style={styles.boutonSeuil}
                />
                <Text style={styles.valeurSeuil}>{seuilEdit} %</Text>
                <Bouton
                  titre="+"
                  variante="contour"
                  onPress={() => setSeuilEdit((s) => Math.min(SEUIL_MAX, s + PAS_SEUIL))}
                  style={styles.boutonSeuil}
                />
              </View>
              <Text style={styles.texteRassurant}>
                Une alerte sera envoyée dès que le niveau descend sous ce seuil.
              </Text>
            </View>

            <Text style={styles.sectionTitre}>Tare</Text>
            <Champ
              etiquette="Poids de la bouteille vide, en grammes"
              valeur={tareTexte}
              onChangeText={setTareTexte}
              clavier="numeric"
              placeholder={formatEdit ? `Ex. ${formatEdit.tare_nominale_g}` : 'Ex. 14800'}
            />

            <View style={styles.ligneBoutonsEdition}>
              <Bouton titre="Annuler" variante="contour" onPress={annulerEdition} style={styles.boutonMoitie} />
              <Bouton
                titre="Enregistrer"
                onPress={enregistrerModifications}
                enCours={enCours}
                style={styles.boutonMoitie}
              />
            </View>

            <Pressable
              style={styles.boutonSupprimer}
              onPress={confirmerSuppression}
              disabled={enCours}
              accessibilityRole="button">
              <Icone nom="corbeille" taille={18} couleur={couleurs.danger} />
              <Text style={styles.texteSupprimer}>Supprimer la bouteille</Text>
            </Pressable>
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  conteneur: {
    flex: 1,
    backgroundColor: couleurs.fond,
  },
  centre: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  contenu: {
    padding: espacements.lg,
    paddingBottom: espacements.xxl,
  },
  enTete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderRadius: rayons.md,
    paddingHorizontal: espacements.md,
    paddingVertical: espacements.sm,
    marginBottom: espacements.md,
    gap: espacements.sm,
  },
  enTeteFormat: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.sm,
    flexShrink: 1,
  },
  enTeteActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.md,
  },
  pastille: {
    width: 12,
    height: 12,
    borderRadius: 6,
  },
  boutonModifier: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 4,
  },
  texteModifier: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.rouge,
  },
  format: {
    fontSize: 18,
    fontWeight: '700',
    color: couleurs.texte,
    flexShrink: 1,
  },
  carte: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.xl,
    padding: espacements.lg,
    alignItems: 'center',
    marginBottom: espacements.lg,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  heroNiveau: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.lg,
    marginBottom: espacements.md,
  },
  blocAutonomie: {
    alignItems: 'center',
  },
  blocNiveauAnime: {
    alignSelf: 'stretch',
    alignItems: 'center',
  },
  chiffreAutonomie: {
    fontSize: 56,
    fontWeight: '800',
    color: couleurs.texte,
  },
  libelle: {
    fontSize: 15,
    color: couleurs.texteDoux,
  },
  chiffreDesactive: {
    color: couleurs.grisNeutre,
  },
  barreNiveau: {
    alignSelf: 'stretch',
    height: 10,
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rougeClair,
    overflow: 'hidden',
  },
  barreNiveauRemplie: {
    height: '100%',
    borderRadius: rayons.rond,
    backgroundColor: couleurs.rouge,
  },
  texteSecondaire: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.sm,
  },
  texteEstimation: {
    fontSize: 12,
    color: couleurs.ambre,
    marginTop: espacements.xs,
    fontWeight: '600',
  },
  blocTemperature: {
    marginBottom: espacements.lg,
  },
  sectionTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginBottom: espacements.sm,
  },
  carteInfo: {
    backgroundColor: couleurs.carte,
    borderRadius: rayons.lg,
    padding: espacements.lg,
    marginBottom: espacements.lg,
    gap: espacements.sm,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  texteInfo: {
    fontSize: 15,
    color: couleurs.texte,
  },
  statutTare: {
    fontSize: 13,
    fontWeight: '700',
    alignSelf: 'flex-start',
  },
  statutFiable: {
    color: couleurs.vertOk,
  },
  statutEnCours: {
    color: couleurs.ambre,
  },
  rangee: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.sm,
    marginBottom: espacements.md,
  },
  rangeeMarques: {
    marginTop: espacements.xs,
  },
  chip: {
    minHeight: 48,
    paddingHorizontal: espacements.lg,
    justifyContent: 'center',
    borderRadius: rayons.rond,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
  },
  chipAvecPastille: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  pastilleChip: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  chipActif: {
    backgroundColor: couleurs.rouge,
    borderColor: couleurs.rouge,
  },
  chipTexte: {
    fontSize: 15,
    fontWeight: '600',
    color: couleurs.texte,
  },
  chipTexteActif: {
    color: couleurs.blanc,
  },
  ligneSeuil: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.lg,
  },
  boutonSeuil: {
    width: 56,
    minHeight: 56,
    paddingHorizontal: 0,
  },
  valeurSeuil: {
    fontSize: 28,
    fontWeight: '800',
    color: couleurs.texte,
    minWidth: 80,
    textAlign: 'center',
  },
  texteRassurant: {
    fontSize: 13,
    color: couleurs.texteDoux,
    textAlign: 'center',
  },
  ligneBoutonsEdition: {
    flexDirection: 'row',
    gap: espacements.md,
    marginTop: espacements.md,
  },
  boutonMoitie: {
    flex: 1,
  },
  boutonSupprimer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.xs,
    marginTop: espacements.lg,
    paddingVertical: espacements.sm,
  },
  texteSupprimer: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.danger,
  },
  placeholderCourbe: {
    height: 140,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espacements.xs,
    backgroundColor: couleurs.carte,
  },
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 14,
  },
});
