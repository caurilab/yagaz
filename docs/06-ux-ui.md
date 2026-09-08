# UX / UI

**Plateforme de suivi et de distribution du gaz butane — Afrique de l'Ouest**

*Document de cadrage — v1*

---

## 1. Objet et principes

Ce document décrit l'expérience et les écrans par acteur et par device. Il ne fixe pas encore la maquette pixel ; il pose la structure des parcours, la hiérarchie de l'information et les principes visuels qui doivent guider la conception détaillée.

Quatre principes traversent toute la plateforme.

**Mobile d'abord, vraiment.** Le mobile n'est pas une version réduite du web. C'est le device principal pour le foyer, le dépôt et le livreur. Les écrans sont conçus pour le pouce, sur petit écran, souvent en pleine activité (cuisine, livraison, comptoir).

**Une information, pas un tableau de bord.** Le foyer ne veut pas lire des chiffres, il veut une réponse : « combien de temps il me reste ». La donnée brute existe, mais l'écran répond d'abord à la question avant de la détailler.

**Réseau faible assumé.** L'interface reste lisible et utile même sans connexion : dernière valeur connue affichée clairement, état de synchronisation visible, aucune page blanche.

**Sobriété.** Peu d'éléments par écran, une action principale évidente, des alertes rares et justifiées. L'encombrement visuel est l'ennemi d'un produit qu'on consulte en un coup d'œil.

## 2. Foyer — application mobile (smartphone + tablette)

C'est l'application la plus soignée, celle qui touche le grand public.

### Écran d'accueil

La question à laquelle il répond : *où en est ma bouteille ?* Au centre, la bouteille active avec son niveau et, mis en avant, l'autonomie en heures de flamme. Un état visuel immédiat — plein, correct, bas, presque vide — lisible sans lire un chiffre. En dessous, l'accès aux autres bouteilles et aux autres sites.

L'autonomie en heures est l'information reine : c'est elle qui permet de décider « je lance cette cuisson ou pas ». Elle prime visuellement sur le pourcentage ou la masse.

### Gestion des bouteilles

La liste des bouteilles du site courant, chacune avec son niveau. La distinction **active / secours** est visible d'emblée : la bouteille en service et la réserve ne se présentent pas pareil. Permuter l'active et la secours se fait en un geste, et l'écran explique la conséquence (l'autonomie et les alertes suivent).

### Enregistrement d'une bouteille

Parcours guidé : choisir le format (B6, B12, B24…) et la marque, puis renseigner la tare. Deux chemins offerts — scanner/photographier la collerette, ou laisser le système calibrer tout seul. Le parcours doit rassurer quand la tare est incertaine : l'utilisateur voit que la mesure s'affinera avec l'usage, plutôt que de rester bloqué sur une saisie qu'il ne maîtrise pas.

### Multi-sites

Un sélecteur de site en tête d'application permet de basculer entre son domicile et, par exemple, celui d'un parent surveillé à distance. Chaque site a ses bouteilles et ses alertes. Le basculement doit être évident et rapide — c'est un usage fréquent, pas un réglage caché.

### Alertes et réglages

L'utilisateur choisit ses seuils et ses canaux (push, SMS, WhatsApp). Il active, s'il le souhaite, la notification automatique de son livreur habituel. Les réglages restent simples : des choix clairs, des valeurs par défaut sensées, pas de panneau d'options intimidant.

### Recharge

Une carte montre les dépôts proches qui ont sa bouteille (bon format, bonne marque) en stock. Pour chacun : distance, disponibilité, option « m'y rendre » ou « me faire livrer ». À la commande, un suivi d'état clair jusqu'à la livraison.

### Proposition de livraison reçue

Quand le dépôt ou le livreur propose une recharge, une notification simple — « votre bouteille est presque vide, on vous livre ? » — avec deux réponses possibles, oui ou non, sans détour.

### Tablette

Sur tablette, l'accueil peut présenter plusieurs bouteilles ou sites de front, dans une vue plus large. C'est le même produit, avec plus d'espace pour la vue d'ensemble — utile pour qui surveille plusieurs sites.

## 3. Dépôt de quartier — application mobile

Un outil de comptoir, consulté vite, entre deux clients.

### Écran principal

L'état du stock en un coup d'œil : bouteilles pleines par format et marque, vides à retourner. La tension éventuelle (stock plein qui baisse, vides qui s'accumulent) est signalée visuellement.

### Commandes entrantes

La file des commandes des foyers du quartier. Chaque commande s'accepte, se prépare, s'affecte à un livreur, en gestes simples. L'état de chaque commande est lisible d'un regard.

### Réapprovisionnement

Quand le stock le justifie, une demande de réapprovisionnement vers le mandataire est déjà préparée par la plateforme. Le dépôt confirme ou ajuste la quantité — il n'a pas à composer un appel ni à tout ressaisir.

### Proposition du mandataire

Notification « on vous livre tant de bouteilles ? », confirmée ou annulée en un geste.

## 4. Mandataire — application mobile + dashboard web

Le mandataire vit sur deux surfaces : le terrain (mobile) et le bureau (web).

### Mobile — sur le terrain

Vue de la tournée du jour, dépôt par dépôt : ce qu'on dépose, ce qu'on récupère en vides. Statuts qui avancent au fil de la tournée. Pensé pour être utilisé en déplacement, d'une main.

### Web — le pilotage

La vue consolidée de tous ses dépôts : niveaux de stock, vides accumulés, tensions à venir. C'est ici que la plateforme **propose les tournées** à partir de l'état réel, que le mandataire valide et ajuste. Les tableaux et la vue d'ensemble profitent de la largeur de l'écran d'ordinateur.

La logistique inversée — la récupération des vides — est un flux visible à part entière, pas un à-côté. Le mandataire voit les vides comme un signal de demande autant que le stock plein.

## 5. Distributeur — dashboard web

Surtout du web, pour lire et décider.

### Vue régionale

La demande agrégée par zone, par format, dans le temps. Des cartes et des courbes, jamais de données individuelles de foyer. L'écran répond à : *où la demande monte, où elle tend, comment les volumes évoluent.*

### Suivi des volumes

Évolution dans le temps, comparaison entre zones, saisonnalité. Un outil d'aide à la décision pour orienter production et allocation, pas un outil opérationnel du quotidien.

## 6. Livreur — application mobile

L'application la plus dépouillée, faite pour être utilisée en mouvement.

### Missions

La liste des livraisons affectées : adresse, bouteille, quantité, à déposer et à récupérer. Priorité à la clarté et à la navigation.

### Statuts

Chaque mission avance en gestes larges et sûrs : en route, livré, vide récupéré. Le statut remonte automatiquement au foyer et au dépôt/mandataire. Boutons grands, peu de texte, utilisable au soleil et d'une main.

### Alerte reçue

Quand un foyer a activé la notification de son livreur habituel, le livreur reçoit l'alerte de seuil bas et peut proposer la livraison en un geste, sans quitter son flux de travail.

## 7. Éléments transversaux d'interface

**L'état de la bouteille.** Un même langage visuel de niveau (plein → presque vide) partout où une bouteille apparaît, pour que l'information se lise sans réapprentissage d'un écran à l'autre.

**L'autonomie en heures.** Présentée de façon cohérente côté foyer, comme l'information de décision centrale.

**Les notifications.** Un ton et une forme constants pour les trois moments qui comptent — seuil bas, proposition de livraison, statut de commande — et rien d'autre. La rareté fait la valeur de l'alerte.

**L'état de synchronisation.** Toujours visible quand le réseau est faible : l'utilisateur sait s'il regarde une donnée fraîche ou la dernière connue.

**La hiérarchie par rôle.** Chaque acteur a une application taillée pour sa question centrale — « combien il me reste » pour le foyer, « qu'est-ce que je dois recharger » pour le dépôt, « quelle tournée je prépare » pour le mandataire, « où monte la demande » pour le distributeur, « quelle est ma prochaine course » pour le livreur. L'écran d'accueil de chacun répond d'abord à cette question-là.

## 8. Direction visuelle (à approfondir en maquette)

La conception détaillée devra fixer la palette, la typographie et le système de composants. Quelques orientations de départ : un visuel sobre et lisible en plein soleil et en cuisine, des contrastes forts pour la lecture rapide, des cibles tactiles généreuses pour un usage en activité, et une identité qui inspire confiance sur une donnée — le niveau de gaz — dont l'utilisateur doit pouvoir se fier sans hésiter.
