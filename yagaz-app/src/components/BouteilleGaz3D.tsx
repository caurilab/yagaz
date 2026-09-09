/**
 * Bouteille de gaz en 3D temps réel (three.js via expo-gl), colorée par la
 * marque, en rotation lente + rotation au doigt. Modèle PROCÉDURAL pour
 * l'instant (primitives three) : aucun asset requis, fonctionne tout de
 * suite. Un vrai modèle `.glb` (export Blender par format) se branchera plus
 * tard ici, à la place de `construireBouteilleProcedurale`.
 *
 * SÛRETÉ : si `expo-gl` n'est pas disponible (ex. Expo Go sans le module
 * natif) ou si la création du contexte GL échoue, on retombe sur `fallback`
 * (la bouteille SVG). L'app ne casse jamais à cause de la 3D.
 */
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { PanResponder, View } from 'react-native';

// Chargement défensif : si un de ces modules manque à l'exécution, on garde
// `disponible = false` et on rendra le repli. Typés `any` : `expo-three` (v8)
// et `three` (v0.186) ne fournissent pas de types cohérents ensemble, et ces
// modules sont chargés dynamiquement.
/* eslint-disable @typescript-eslint/no-explicit-any */
let GLView: any;
let Renderer: any;
let THREE: any;
let modules3dDisponibles = false;
try {
  GLView = require('expo-gl').GLView;
  Renderer = require('expo-three').Renderer;
  THREE = require('three');
  modules3dDisponibles = Boolean(GLView && Renderer && THREE);
} catch {
  modules3dDisponibles = false;
}

interface Props {
  /** Couleur de marque (hex). */
  couleur: string;
  /** Côté du rendu (px). */
  taille: number;
  /** Rendu de repli quand la 3D n'est pas disponible (bouteille SVG). */
  fallback: ReactNode;
}

/** Construit une bouteille approximative à partir de primitives three. */
function construireBouteille(three: any, couleurHex: string): any {
  const groupe = new three.Group();
  const materiau = new three.MeshStandardMaterial({
    color: new three.Color(couleurHex),
    roughness: 0.35,
    metalness: 0.15,
  });
  const materiauMetal = new three.MeshStandardMaterial({ color: new three.Color('#9aa3b2'), roughness: 0.4, metalness: 0.6 });

  const corps = new three.Mesh(new three.CylinderGeometry(0.72, 0.72, 1.9, 40), materiau);
  corps.position.y = -0.15;
  groupe.add(corps);

  const epaule = new three.Mesh(new three.SphereGeometry(0.72, 40, 24, 0, Math.PI * 2, 0, Math.PI / 2), materiau);
  epaule.position.y = 0.8;
  groupe.add(epaule);

  const socle = new three.Mesh(new three.CylinderGeometry(0.74, 0.68, 0.14, 40), materiau);
  socle.position.y = -1.12;
  groupe.add(socle);

  const collerette = new three.Mesh(new three.TorusGeometry(0.34, 0.07, 16, 32), materiauMetal);
  collerette.rotation.x = Math.PI / 2;
  collerette.position.y = 1.12;
  groupe.add(collerette);

  const valve = new three.Mesh(new three.CylinderGeometry(0.12, 0.12, 0.28, 20), materiauMetal);
  valve.position.y = 1.3;
  groupe.add(valve);

  groupe.rotation.x = 0.18;
  return groupe;
}

export function BouteilleGaz3D({ couleur, taille, fallback }: Props) {
  const [echec, setEchec] = useState(!modules3dDisponibles);
  const contexteCree = useRef(false);
  const rotationY = useRef(0);
  const groupeRef = useRef<any>(null);
  const rafRef = useRef<number | null>(null);
  const monteRef = useRef(true);

  // Arrête la boucle de rendu au démontage : sans ça, `requestAnimationFrame`
  // continue d'appeler le renderer sur un contexte GL mort et la surface 3D
  // « persiste » par-dessus les écrans suivants.
  useEffect(() => {
    monteRef.current = true;
    return () => {
      monteRef.current = false;
      if (rafRef.current !== null) {
        cancelAnimationFrame(rafRef.current);
        rafRef.current = null;
      }
    };
  }, []);

  // Si le contexte GL ne se crée pas rapidement (module natif absent en Expo
  // Go), on bascule sur le repli plutôt que de laisser une zone vide.
  useEffect(() => {
    if (echec) return;
    const minuterie = setTimeout(() => {
      if (!contexteCree.current) setEchec(true);
    }, 2000);
    return () => clearTimeout(minuterie);
  }, [echec]);

  const panResponder = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_e, g) => Math.abs(g.dx) > 2,
      onPanResponderMove: (_e, g) => {
        if (groupeRef.current) {
          groupeRef.current.rotation.y = rotationY.current + g.dx * 0.01;
        }
      },
      onPanResponderRelease: (_e, g) => {
        rotationY.current += g.dx * 0.01;
      },
    })
  ).current;

  if (echec || !GLView || !Renderer || !THREE) {
    return <>{fallback}</>;
  }

  const three = THREE;
  const RendererClasse = Renderer;

  return (
    <View style={{ width: taille, height: taille }} {...panResponder.panHandlers}>
      <GLView
        style={{ width: taille, height: taille }}
        onContextCreate={(gl: any) => {
          try {
            contexteCree.current = true;
            const renderer = new RendererClasse({ gl });
            renderer.setSize(gl.drawingBufferWidth, gl.drawingBufferHeight);
            renderer.setClearColor(0x000000, 0);

            const scene = new three.Scene();
            const camera = new three.PerspectiveCamera(
              45,
              gl.drawingBufferWidth / gl.drawingBufferHeight,
              0.1,
              100
            );
            camera.position.set(0, 0, 4.6);

            scene.add(new three.AmbientLight(0xffffff, 0.9));
            const lumiere = new three.DirectionalLight(0xffffff, 0.7);
            lumiere.position.set(2, 4, 5);
            scene.add(lumiere);

            const bouteille = construireBouteille(three, couleur);
            groupeRef.current = bouteille;
            scene.add(bouteille);

            const boucle = () => {
              if (!monteRef.current) return; // écran démonté : on stoppe la boucle
              rafRef.current = requestAnimationFrame(boucle);
              // rotation lente auto + rotation manuelle (via rotationY courant)
              bouteille.rotation.y += 0.006;
              rotationY.current = bouteille.rotation.y;
              renderer.render(scene, camera);
              gl.endFrameEXP();
            };
            boucle();
          } catch {
            setEchec(true);
          }
        }}
      />
    </View>
  );
}
