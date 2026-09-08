# Matériel & sourcing

**Plateforme de suivi et de distribution du gaz butane — Afrique de l'Ouest**

*Document de cadrage — v1*

---

## 1. Le principe : simple, plat, bon marché

Le plateau doit coûter peu, se poser sous n'importe quelle bouteille domestique, rester discret, et mesurer un poids de façon fiable. Aucune de ces contraintes ne demande de composant exotique. Le principe est celui d'une balance de salle de bain connectée, adapté au format bouteille.

Cible de coût de revient : **8 à 15 USD à l'unité en volume**. C'est ce qui permet la vente sèche à un prix accessible, sans subvention ni abonnement.

## 2. La chaîne de mesure

La mesure repose sur quatre briques simples, éprouvées et disponibles partout :

**Les cellules de charge.** Quatre capteurs de force, disposés aux quatre coins du plateau, en configuration pont. C'est le même principe que dans une balance de salle de bain. Elles transforment le poids en signal électrique.

**Le convertisseur.** Un module **HX711**, convertisseur analogique-numérique dédié aux cellules de charge. Il amplifie et numérise le signal pour que le microcontrôleur puisse le lire. Composant standard, très bon marché, omniprésent dans les projets de pesée.

**Le microcontrôleur.** Un **ESP32**, avec Wi-Fi intégré. Il lit le signal du HX711, applique un premier lissage, et publie la mesure en MQTT vers la plateforme. Le Wi-Fi intégré évite tout module réseau supplémentaire.

**Le boîtier.** Un châssis plat qui porte le plateau, loge l'électronique et les cellules, et présente une surface stable pour la bouteille. C'est la partie à industrialiser proprement — le reste est de l'assemblage de modules connus.

## 3. Les trois références produit

**Le plateau seul.** La configuration de base ci-dessus. Le produit d'entrée, celui qui doit équiper le plus grand nombre de foyers.

**Le pack plateau + écran de cuisine.** Le plateau, plus un petit écran qui affiche le niveau et l'autonomie directement, sans passer par le téléphone. L'écran communique avec le plateau en direct.

**L'écran de cuisine seul.** Le même écran, vendu séparément à ceux qui ont déjà le plateau. Coût additionnel très faible : un afficheur, un petit boîtier, une liaison avec le plateau. La valeur d'usage est réelle — c'est souvent la personne qui cuisine, et non le propriétaire du téléphone, qui a besoin de voir le niveau dans la cuisine.

## 4. La question de l'alimentation

Le plateau doit tenir longtemps sans intervention. Deux voies, à trancher au prototypage :

- **Sur secteur**, si une prise est disponible près du point de cuisson — le plus simple, pas d'autonomie à gérer.
- **Sur batterie**, pour la liberté de placement — mais impose de soigner la consommation, donc d'espacer les envois de mesure (voir compromis ci-dessous).

Le compromis clé, si batterie : la fréquence d'envoi. Une mesure très fréquente donne une donnée fraîche mais épuise la batterie ; une mesure espacée préserve l'autonomie mais retarde l'alerte. Un rythme adaptatif — mesurer souvent quand la consommation est active, s'endormir quand la bouteille ne bouge pas — concilie les deux.

## 5. Le sourcing

**Où.** Les composants sont tous disponibles sur les places de marché B2B et grand public asiatiques — **Alibaba** pour le volume et la négociation fournisseur, **AliExpress** pour le prototypage à l'unité. Cellules de charge, HX711 et ESP32 y sont des produits de catalogue courants, avec de nombreux fournisseurs concurrents.

**Comment procéder.** La démarche recommandée :

1. **Prototypage à l'unité** sur AliExpress — commander cellules, HX711 et ESP32 séparément pour valider la chaîne de mesure et le firmware.
2. **Validation de la mesure** — vérifier la précision, le bruit, la stabilité dans le temps, la gestion de la tare sur plusieurs formats de bouteille (B6, B12, B24).
3. **Recherche fournisseur volume** sur Alibaba — une fois la configuration figée, chercher un fournisseur capable de livrer le module assemblé ou les composants en quantité, et négocier le prix cible.
4. **Boîtier** — faire concevoir ou adapter un boîtier plat, éventuellement auprès du même fournisseur ou d'un spécialiste de l'injection plastique.

**Ce qu'il faut vérifier chez le fournisseur.** La constance de qualité entre lots (une cellule qui dérive fausse la mesure), la capacité de volume, les délais, et la possibilité de personnalisation du boîtier. Commander des échantillons de plusieurs fournisseurs avant de s'engager.

## 6. Les points de vigilance matériels

**La précision de la tare.** Le défi n'est pas de peser — c'est de connaître le poids à vide de la bouteille pour en déduire le gaz. La chaîne de mesure doit être assez précise et stable pour que la déduction reste juste sur toute la durée de vie du plateau. Une cellule qui dérive avec la température ou le temps compromet tout le service.

**Le bruit de pesée.** Une bouteille qu'on frôle, un plateau posé de travers, une vibration — autant de sources de mesures parasites. Le lissage se fait en partie dans le firmware (moyenne, filtrage), en partie côté plateforme. À prévoir dès le prototype.

**La robustesse en cuisine.** Le plateau vit au sol d'une cuisine : chaleur, projections, chocs, poussière. Le boîtier doit encaisser cet environnement, ce qui pèse sur le choix des matériaux et l'étanchéité.

**La constance entre unités.** Chaque plateau vendu doit mesurer comme les autres. Une dispersion trop forte entre unités rendrait l'autonomie annoncée peu fiable d'un foyer à l'autre. D'où l'importance d'un fournisseur régulier et, éventuellement, d'une calibration en fin de fabrication.

## 7. Récapitulatif des composants

| Brique | Composant type | Rôle | Disponibilité |
|---|---|---|---|
| Mesure de force | Cellules de charge (×4, pont) | Poids → signal électrique | Alibaba / AliExpress |
| Conversion | Module HX711 | Amplification + numérisation | Alibaba / AliExpress |
| Traitement + réseau | ESP32 (Wi-Fi) | Lecture, lissage, émission MQTT | Alibaba / AliExpress |
| Structure | Boîtier plat sur mesure | Support bouteille + logement électronique | À industrialiser |
| Affichage (option) | Écran + boîtier | Niveau/autonomie visible en cuisine | Alibaba / AliExpress |
| Alimentation | Secteur ou batterie | À trancher au prototypage | — |

Rien dans cette liste n'est rare ou coûteux. Le travail n'est pas de trouver les pièces, mais de figer une configuration précise, régulière et robuste, puis de sécuriser un fournisseur qui la reproduit à l'identique en volume.
