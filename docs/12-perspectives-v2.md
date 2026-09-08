# Perspectives v2

Ce document prend ce que la v1 a volontairement laissé de côté et l'organise en
une suite cohérente. Il ne remplace pas le PRD : il prolonge sa section « Hors
périmètre v1 » en une feuille de route, avec un ordre, des préalables et une
raison d'être pour chaque brique.

À lire comme une direction, pas comme un engagement figé. La v1 doit d'abord
prouver la chaîne de bout en bout ; ce qui suit ne se décide vraiment qu'à la
lumière de ce qu'elle aura appris.

## Ce que la v1 aura établi

Avant de parler de la suite, rappeler le socle sur lequel elle s'appuie : une
mesure fiable du gaz chez le foyer, sa circulation jusqu'au distributeur, et la
boucle commande–livraison de recharge. Tout ce qui suit suppose ce socle en
place et en fonctionnement réel, avec un premier parc de plateaux déployés.

## Les briques de la v2

Quatre chantiers étaient dans le backlog v1. Ils n'ont pas le même poids ni les
mêmes préalables. Les voici dans l'ordre où ils gagnent à être abordés.

### 1. Paiement en ligne (Mobile Money)

**Pourquoi en premier.** C'est la brique qui manque à la boucle commande–
livraison pour être complète. Tant qu'elle n'est pas là, le paiement se fait à
la livraison — ce qui marche, mais laisse de la friction et prive la plateforme
d'un point de contrôle sur la transaction, donc sur la commission.

**Préalable.** Une décision sur les moyens de paiement locaux, jamais tranchée à
ce stade : quels opérateurs Mobile Money viser en priorité, via quel agrégateur
ou quelle intégration directe. C'est le premier arbitrage à ouvrir.

**Ce que ça suppose techniquement.** Le cadrage v1 prévoit déjà un point
d'extension propre pour brancher le paiement sans réécrire la commande. La v2
consiste à le raccorder : intégration de l'opérateur, gestion des états de
paiement, réconciliation, et le lien avec la commission déjà prévue au modèle
économique.

### 2. Écran de cuisine comme produit à part entière

**Pourquoi ensuite.** Le hardware est déjà prévu dès la v1 (une des trois
références matérielles). Ce qui manque, c'est son intégration produit : en faire
autre chose qu'un afficheur de niveau. C'est un chantier borné, sans dépendance
lourde, qui valorise un matériel déjà conçu.

**Préalable.** Un premier retour du terrain sur l'usage réel de l'écran :
qu'attend la personne qui cuisine et qui n'a pas forcément l'application ?

**Ce que ça suppose.** Définir le rôle propre de l'écran (alertes, autonomie en
heures, peut-être plus tard le lien avec les recettes) et son autonomie
vis-à-vis de l'app foyer.

### 3. Couche recettes / suggestions de plats

**Pourquoi à ce moment.** C'est l'habillage que la vision décrit depuis le
début : « quel plat puis-je cuisiner avec le gaz qu'il me reste ». Il ne prend
son sens qu'une fois la mesure d'autonomie fiable et éprouvée — donc pas avant
que la v1 ait fait ses preuves. C'est aussi là que les primitives AI de
Laravel 13, déjà dans la stack, trouvent leur emploi.

**Préalable.** Une mesure d'autonomie dont on est sûr : suggérer un plat qui
« tient » dans l'autonomie restante n'a de valeur que si l'estimation est juste.

**Ce que ça suppose.** Un corpus de recettes avec temps et intensité de cuisson,
une logique de correspondance entre autonomie restante et faisabilité d'un plat,
et une intégration soignée dans l'app foyer et, éventuellement, l'écran de
cuisine. C'est une couche au-dessus du cœur, pas une modification du cœur.

### 4. Monétisation de la donnée agrégée

**Pourquoi en dernier.** Non par importance — c'est potentiellement la source de
revenu à plus forte marge — mais par préalable : elle n'a de valeur que lorsque
le parc est assez dense pour que les agrégats de consommation soient
significatifs et représentatifs. C'est mécaniquement le dernier chantier
mûr.

**Préalable.** La densité. Un nombre de plateaux actifs suffisant, sur un
territoire donné, pour que « la consommation par quartier, par format, dans le
temps » veuille dire quelque chose de fiable.

**Ce que ça suppose.** Les fondations sont déjà posées : TimescaleDB conserve
l'histoire des mesures et permet les agrégats régionaux. La v2 consiste à en
faire un produit pour les distributeurs qui pilotent aujourd'hui à l'aveugle —
vues, tendances, signaux de demande — avec les garanties d'anonymisation et de
cloisonnement que cela impose. La sécurité et le respect de la donnée des foyers
sont ici un préalable non négociable, pas une option.

## L'ordre en un coup d'œil

| Ordre | Brique | Débloquée par | Nature |
|---|---|---|---|
| 1 | Paiement Mobile Money | Décision moyens de paiement | Complète la boucle |
| 2 | Écran de cuisine produit | Retour terrain sur l'usage | Valorise un hardware existant |
| 3 | Couche recettes | Mesure d'autonomie éprouvée | Habillage au-dessus du cœur |
| 4 | Donnée agrégée | Densité du parc | Nouvelle source de revenu |

## Ce que la v2 n'est pas

Elle n'est pas une refonte. Le cœur — mesurer, faire circuler, commander,
livrer — reste celui de la v1. La v2 ajoute des couches et une source de revenu ;
elle ne rejoue pas les fondations. Toute brique qui demanderait de défaire le
socle serait le signe qu'elle a été mal cadrée, ou qu'elle relève d'une décision
plus lourde à documenter en ADR.

## Pour le yagaz-pm

Ce document est une matière à prioriser, pas un planning. Les quatre briques
sont dans un ordre logique de préalables, mais c'est le terrain de la v1 qui
dira lesquelles avancer, à quel rythme, et si l'ordre tient. Chaque passage en
développement effectif d'une de ces briques mérite son propre cadrage et, s'il
engage l'architecture, son ADR.
