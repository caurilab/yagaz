# PRD — Product Requirements Document

**Plateforme de suivi et de distribution du gaz butane — Afrique de l'Ouest**

*Document de cadrage — v1*

---

## 1. Objet du document

Ce document décrit ce que le produit doit faire, acteur par acteur, écran par écran. Il ne traite ni de l'implémentation technique (voir Architecture) ni du sourcing matériel (voir Matériel & sourcing). Il sert de référence commune à la conception UX/UI et au développement.

## 2. Périmètre de la v1

La v1 couvre la chaîne complète mais dans sa forme essentielle : mesurer le gaz chez le foyer, faire circuler l'information jusqu'au distributeur, et permettre la commande et la livraison de recharge. La couche recettes/temps de cuisson n'est pas dans la v1 ; elle est prévue comme extension.

Sont dans la v1 :
- l'ingestion et l'affichage du niveau de gaz chez le foyer, en multi-bouteilles et multi-sites ;
- l'alerte de seuil bas et la notification du livreur ;
- la carte des dépôts et la commande de recharge ;
- le tableau de bord du dépôt (stock plein/vide, commandes) ;
- le tableau de bord du mandataire (dépôts consolidés, tournées) ;
- la vue distributeur (demande régionale agrégée) ;
- l'application livreur (missions, statuts).

## 3. Les acteurs

La plateforme dessert cinq rôles, en remontant la chaîne de distribution.

Le **foyer** est l'utilisateur final domestique. Il possède un ou plusieurs plateaux, suit sa ou ses bouteilles, et commande ses recharges.

Le **dépôt de quartier** est le revendeur qui vend la bouteille au foyer. Il tient un stock de bouteilles pleines et récupère les vides.

Le **mandataire** approvisionne les dépôts. Il gère un portefeuille de dépôts et organise ses tournées de livraison.

Le **distributeur** est la marque en tête de chaîne (TotalEnergies, Oryx et autres). Il lit la demande régionale agrégée.

Le **livreur** exécute les livraisons. Il peut être rattaché à un dépôt ou à un mandataire.

## 4. Concepts transversaux

Avant de décrire les fonctions, quelques notions structurent tout le produit.

**Bouteille.** Une bouteille a un format (B6, B12, B24…), une marque, et un poids à vide (tare) qui lui est propre. La tare est gravée sur la collerette mais varie d'une bouteille à l'autre ; sa gestion est un point sensible traité plus bas.

**Plateau.** Le capteur physique sous la bouteille. Un plateau mesure un poids et l'associe à la bouteille qui est dessus. Un foyer peut avoir plusieurs plateaux.

**Site.** Une adresse physique où se trouvent une ou plusieurs bouteilles. Un compte foyer peut gérer plusieurs sites — son domicile, mais aussi celui d'un parent qu'il surveille à distance.

**Niveau et autonomie.** Le niveau est la masse de gaz restante, déduite du poids mesuré moins la tare. L'autonomie est cette masse traduite en heures de flamme, à partir du débit de consommation observé au fil du temps.

**Commande et livraison.** Une commande est une demande de recharge émise par un foyer ou un dépôt. Une livraison est l'exécution physique de cette commande par un livreur.

## 5. Exigences fonctionnelles par acteur

### 5.1 Foyer

**Enregistrer une bouteille.** Le foyer associe une bouteille à un plateau : il choisit le format et la marque, et renseigne la tare. Deux voies pour la tare — saisie assistée (photo/scan de la collerette) ou calibrage automatique par observation du poids plancher sur plusieurs cycles. Le produit doit fonctionner même si la tare saisie est approximative, en s'affinant avec l'usage.

**Suivre plusieurs bouteilles.** Le foyer voit l'état de toutes ses bouteilles d'un coup d'œil. Le produit distingue la bouteille active de la bouteille de secours : l'alerte de seuil bas ne se déclenche pas de la même façon pour une réserve que pour la bouteille en service.

**Suivre plusieurs sites.** Un même compte suit des bouteilles à plusieurs adresses. L'utilisateur bascule entre ses sites et reçoit les alertes de chacun. Le cas d'usage central : surveiller la bouteille d'un proche âgé.

**Lire le niveau et l'autonomie.** L'écran principal affiche, pour la bouteille active, le niveau de gaz et l'autonomie restante en heures de cuisson. L'information doit être lisible immédiatement, sans interprétation.

**Recevoir les alertes.** Quand le seuil bas est atteint, le foyer est notifié (push, et selon options SMS/WhatsApp). Il choisit ses seuils. Il peut activer une notification automatique de son livreur habituel au franchissement du seuil.

**Trouver et commander une recharge.** Le foyer voit sur une carte les dépôts proches qui ont sa bouteille (format + marque) en stock. Il peut soit s'y rendre, soit commander une livraison. À la commande, il confirme et suit l'état de la livraison.

**Répondre à une proposition de livraison.** Si le livreur ou le dépôt propose une recharge (parce qu'il voit le niveau bas), le foyer reçoit une notification « votre bouteille est presque vide, on vous livre ? » et répond oui/non depuis l'application.

### 5.2 Dépôt de quartier

**Voir son stock.** Le dépôt suit son stock de bouteilles pleines par format et par marque, et son stock de vides à retourner. Le stock se met à jour au fil des ventes et des retours.

**Recevoir les commandes des foyers.** Les commandes de recharge des foyers du quartier arrivent dans une file. Le dépôt les accepte, les prépare, les affecte à un livreur.

**Signaler et préparer le réapprovisionnement.** Quand le stock plein baisse ou que les vides s'accumulent, le dépôt n'a pas à appeler : la demande de réapprovisionnement vers le mandataire se prépare automatiquement à partir de l'état du stock. Le dépôt confirme ou ajuste.

**Répondre au mandataire.** Quand le mandataire propose une livraison, le dépôt reçoit la notification, confirme ou annule la quantité.

### 5.3 Mandataire

**Voir ses dépôts consolidés.** Le mandataire dispose d'une vue de tous ses dépôts : niveau de stock plein, vides accumulés, tensions à venir. C'est sa carte de terrain.

**Préparer les tournées.** À partir de l'état consolidé, la plateforme propose des tournées de livraison. Le mandataire n'attend plus l'appel du dépôt en rupture ; il voit la tournée se dessiner et la valide.

**Proposer la livraison.** Le mandataire pousse une proposition de livraison au dépôt et attend sa confirmation via la plateforme.

**Gérer les retours de vides.** La tournée intègre la récupération des bouteilles vides, pas seulement la dépose des pleines. La logistique inversée est un flux à part entière.

### 5.4 Distributeur

**Lire la demande régionale.** Le distributeur voit la demande agrégée par zone, par format, dans le temps. Pas de données individuelles de foyer — des agrégats.

**Suivre les volumes.** Évolution des volumes distribués, tensions par quartier, saisonnalité. Une lecture du terrain pour orienter la production et l'allocation.

### 5.5 Livreur

**Recevoir ses missions.** Le livreur voit les livraisons qui lui sont affectées, avec adresse, bouteille et quantité.

**Suivre le statut.** Il fait avancer chaque mission (en route, livré, retour de vide récupéré). Le statut remonte au foyer et au dépôt/mandataire concerné.

**Être déclenché par une alerte.** Quand un foyer a activé la notification automatique de son livreur habituel, le livreur reçoit l'alerte de seuil bas et peut proposer la livraison en un geste.

## 6. Exigences non fonctionnelles

**Priorité mobile.** Tous les écrans sont pensés mobile d'abord — smartphone et tablette. Les dashboards mandataire et distributeur peuvent aussi s'utiliser sur ordinateur, mais le mobile n'est jamais un pis-aller.

**Robustesse réseau.** Le contexte impose des connexions instables. L'application foyer doit rester utile hors ligne ou en connexion faible : la dernière mesure connue s'affiche, les mesures se synchronisent quand le réseau revient.

**Fiabilité de la mesure.** La valeur affichée doit être digne de confiance. Une mesure fausse qui fait courir un utilisateur pour rien détruit la confiance plus sûrement qu'une absence de mesure. La gestion de la tare et le lissage du bruit de pesée sont critiques.

**Économie d'énergie du plateau.** Le plateau doit tenir longtemps sur sa source d'énergie. La fréquence d'envoi des mesures est un compromis entre fraîcheur de la donnée et autonomie du capteur.

**Sobriété des notifications.** Trop d'alertes tuent l'alerte. Le produit notifie quand c'est utile — seuil bas, proposition de livraison, statut de commande — et se tait le reste du temps.

**Multilingue.** Français en premier. L'architecture de contenu doit permettre d'ajouter d'autres langues locales sans refonte.

## 7. Points sensibles à trancher en conception

**La tare.** C'est le point technique le plus délicat côté produit. La solution retenue combine saisie assistée et calibrage automatique, mais la conception doit définir précisément le parcours : que voit l'utilisateur si la tare est incertaine, comment le système signale qu'il s'affine, quand il considère la tare fiable.

**Bouteille active vs secours.** La distinction structure l'alerte. La conception doit définir comment le foyer désigne sa bouteille active, ce qui se passe quand il permute active et secours, et comment l'autonomie se recalcule.

**Le déclenchement de la chaîne.** Jusqu'où l'automatisation va-t-elle sans validation humaine ? Le principe retenu : la plateforme prépare et propose, l'humain confirme. La conception doit fixer, à chaque maillon, ce qui est automatique et ce qui demande un clic.

## 8. Hors périmètre v1 (backlog)

- Couche recettes / suggestions de plats selon l'autonomie restante.
- Monétisation de la donnée agrégée auprès des distributeurs.
- Écran de cuisine comme produit à part entière (le hardware est prévu ; son intégration produit peut suivre la v1).
- Paiement en ligne intégré (selon décision ultérieure sur les moyens de paiement locaux).
