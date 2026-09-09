import { useEffect, useMemo, useState } from 'react';
import { router } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Bouton } from '../../components/Bouton';
import { BouteilleGaz } from '../../components/BouteilleGaz';
import { Champ } from '../../components/Champ';
import { Icone } from '../../components/icones';
import { SelecteurFormat } from '../../components/SelecteurFormat';
import { useDialogue } from '../../data/DialogueContext';
import { useDonnees } from '../../data/DonneesContext';
import { piecesBouteille } from '../../api/endpoints';
import { avecRepliDemo, piecesBouteilleDemo } from '../../api/demo';
import { couleurs, espacements, rayons } from '../../../theme/couleurs';
import { couleurPourFormat } from '../../utils/marque';
import type { Format, PieceBouteille, RoleBouteille } from '../../api/types';

/** Parcours guidé d'enregistrement d'une bouteille (UX §2). */
export default function EcranEnregistrerBouteille() {
  const { formats, marques, bouteilles, enregistrerBouteille } = useDonnees();
  const { alerter } = useDialogue();

  const codes = useMemo(() => Array.from(new Set(formats.map((f) => f.code))), [formats]);
  const [codeChoisi, setCodeChoisi] = useState<string | null>(codes[0] ?? null);
  const formatsDuCode = useMemo(
    () => formats.filter((f) => f.code === codeChoisi),
    [formats, codeChoisi]
  );
  const [formatChoisi, setFormatChoisi] = useState<Format | null>(formatsDuCode[0] ?? null);
  const [tareTexte, setTareTexte] = useState('');
  const [role, setRole] = useState<RoleBouteille>(bouteilles.length === 0 ? 'active' : 'secours');
  const [enCours, setEnCours] = useState(false);

  // Tare ajustable (pièces manquantes) : référentiel des pièces amovibles,
  // chargé une fois (avec repli démo si l'API ne répond pas).
  const [piecesReferentiel, setPiecesReferentiel] = useState<PieceBouteille[]>([]);
  const [piecesCochees, setPiecesCochees] = useState<string[]>([]);

  useEffect(() => {
    avecRepliDemo(() => piecesBouteille().then((r) => r.data), piecesBouteilleDemo).then(setPiecesReferentiel);
  }, []);

  function basculerPiece(cle: string) {
    setPiecesCochees((precedent) =>
      precedent.includes(cle) ? precedent.filter((c) => c !== cle) : [...precedent, cle]
    );
  }

  const deltaTotalCoche = useMemo(
    () =>
      piecesReferentiel
        .filter((piece) => piecesCochees.includes(piece.cle))
        .reduce((somme, piece) => somme + piece.delta_g, 0),
    [piecesReferentiel, piecesCochees]
  );

  const tareEstimee =
    formatChoisi && piecesCochees.length > 0 ? Math.max(0, formatChoisi.tare_nominale_g - deltaTotalCoche) : null;

  function choisirCode(code: string) {
    setCodeChoisi(code);
    const premier = formats.find((f) => f.code === code) ?? null;
    setFormatChoisi(premier);
  }

  async function valider() {
    if (!formatChoisi) {
      void alerter({ titre: 'Format requis', message: 'Choisissez le format de la bouteille.' });
      return;
    }
    const tareNombre = tareTexte.trim() ? Number(tareTexte.replace(',', '.')) : undefined;
    if (tareTexte.trim() && (Number.isNaN(tareNombre) || (tareNombre ?? 0) <= 0)) {
      void alerter({ titre: 'Tare invalide', message: 'Saisissez un poids en grammes, ou laissez vide.' });
      return;
    }

    setEnCours(true);
    try {
      await enregistrerBouteille({
        format_id: formatChoisi.id,
        tare_g: tareNombre,
        tare_source: tareNombre ? 'saisie' : 'nominale',
        role_bouteille: role,
        pieces_manquantes: piecesCochees.length > 0 ? piecesCochees : undefined,
      });
      router.back();
    } catch {
      void alerter({ titre: 'Erreur', message: "L'enregistrement a échoué. Réessayez." });
    } finally {
      setEnCours(false);
    }
  }

  return (
    <SafeAreaView style={styles.conteneur} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <Text style={styles.etapeTitre}>1. Format</Text>
        <SelecteurFormat codes={codes} codeChoisi={codeChoisi} onChoisir={choisirCode} />

        <Text style={styles.etapeTitre}>2. Marque</Text>
        <View style={styles.rangee}>
          {formatsDuCode.map((format) => (
            <Pressable
              key={format.id}
              style={[styles.tuileFormat, formatChoisi?.id === format.id && styles.tuileFormatActive]}
              onPress={() => setFormatChoisi(format)}>
              <BouteilleGaz
                couleur={couleurPourFormat(format, marques)}
                code={format.code}
                marqueNom={format.marque}
                niveauPct={100}
                taille={62}
                reflet={false}
              />
              <Text
                style={[styles.tuileTexte, formatChoisi?.id === format.id && styles.tuileTexteActive]}
                numberOfLines={1}>
                {format.marque}
              </Text>
            </Pressable>
          ))}
          {formatsDuCode.length === 0 ? <Text style={styles.texteVide}>Choisissez d'abord un format.</Text> : null}
        </View>

        <Text style={styles.etapeTitre}>3. Tare (optionnelle)</Text>
        <Champ
          etiquette="Poids de la bouteille vide, en grammes"
          valeur={tareTexte}
          onChangeText={setTareTexte}
          clavier="numeric"
          placeholder={formatChoisi ? `Ex. ${formatChoisi.tare_nominale_g}` : 'Ex. 14800'}
        />
        <Text style={styles.texteRassurant}>
          Vous ne connaissez pas la tare exacte ? Laissez ce champ vide : la mesure s'affinera
          automatiquement avec l'usage.
        </Text>

        <Text style={styles.etapeTitre}>Éléments manquants (optionnel)</Text>
        <Text style={styles.texteRassurant}>
          Cochez les pièces absentes sur cette bouteille : leur poids est retranché de la tare de
          référence du format pour ajuster automatiquement la tare.
        </Text>
        <View style={styles.listePieces}>
          {piecesReferentiel.map((piece) => {
            const cochee = piecesCochees.includes(piece.cle);
            return (
              <Pressable
                key={piece.cle}
                style={[styles.cartePiece, cochee && styles.cartePieceActive]}
                onPress={() => basculerPiece(piece.cle)}>
                <Text style={styles.libellePiece}>{piece.libelle}</Text>
                <View style={styles.zoneDeltaPiece}>
                  <Text style={styles.deltaPiece}>-{piece.delta_g} g</Text>
                  {cochee ? <Icone nom="check" taille={20} couleur={couleurs.rouge} /> : null}
                </View>
              </Pressable>
            );
          })}
        </View>
        {tareEstimee !== null ? (
          <Text style={styles.texteApercuTare}>Tare estimée : {tareEstimee} g</Text>
        ) : null}

        <Text style={styles.etapeTitre}>4. Rôle</Text>
        <View style={styles.rangee}>
          <Pressable style={[styles.chip, role === 'active' && styles.chipActif]} onPress={() => setRole('active')}>
            <Text style={[styles.chipTexte, role === 'active' && styles.chipTexteActif]}>Active</Text>
          </Pressable>
          <Pressable style={[styles.chip, role === 'secours' && styles.chipActif]} onPress={() => setRole('secours')}>
            <Text style={[styles.chipTexte, role === 'secours' && styles.chipTexteActif]}>Secours</Text>
          </Pressable>
        </View>
        {role === 'active' && bouteilles.some((b) => b.role_bouteille === 'active') ? (
          <Text style={styles.texteRassurant}>
            La bouteille actuellement active passera automatiquement en secours.
          </Text>
        ) : null}
      </ScrollView>

      <View style={styles.piedDePage}>
        <Bouton titre="Enregistrer la bouteille" onPress={valider} enCours={enCours} />
      </View>
    </SafeAreaView>
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
  etapeTitre: {
    fontSize: 16,
    fontWeight: '700',
    color: couleurs.texte,
    marginTop: espacements.lg,
    marginBottom: espacements.sm,
  },
  rangee: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: espacements.sm,
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
  tuileFormat: {
    width: 88,
    paddingVertical: espacements.sm,
    alignItems: 'center',
    gap: espacements.xs,
    borderRadius: rayons.lg,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    backgroundColor: couleurs.carte,
  },
  tuileFormatActive: {
    borderWidth: 2,
    borderColor: couleurs.rouge,
  },
  tuileTexte: {
    fontSize: 12,
    fontWeight: '600',
    color: couleurs.texteDoux,
  },
  tuileTexteActive: {
    color: couleurs.rouge,
    fontWeight: '700',
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
  texteVide: {
    color: couleurs.texteDoux,
    fontSize: 13,
  },
  texteRassurant: {
    fontSize: 13,
    color: couleurs.texteDoux,
    marginTop: espacements.sm,
    lineHeight: 18,
  },
  listePieces: {
    gap: espacements.sm,
    marginTop: espacements.sm,
  },
  cartePiece: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: couleurs.carte,
    borderRadius: rayons.md,
    borderWidth: 1,
    borderColor: couleurs.bordure,
    padding: espacements.md,
  },
  cartePieceActive: {
    borderColor: couleurs.rouge,
    borderWidth: 2,
  },
  libellePiece: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: couleurs.texte,
  },
  zoneDeltaPiece: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espacements.xs,
  },
  deltaPiece: {
    fontSize: 13,
    color: couleurs.texteDoux,
    fontWeight: '600',
  },
  texteApercuTare: {
    fontSize: 14,
    fontWeight: '700',
    color: couleurs.rouge,
    marginTop: espacements.sm,
  },
  piedDePage: {
    padding: espacements.lg,
    borderTopWidth: 1,
    borderTopColor: couleurs.bordure,
    backgroundColor: couleurs.fond,
  },
});
