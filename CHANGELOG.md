# Changelog

## 1.4.1

Retour en arrière partiel sur le correctif d'affichage de la 1.4.0 :
contraindre `.navi-story-phone` par largeur ET hauteur
(`max-height: min(60vh, 420px)`) déformait/rétrécissait le mockup au lieu
de simplement le repositionner — format cassé sur laptop, pire que le
problème d'origine. `.navi-story-phone` revient à son format d'origine
(largeur + ratio 9:18.5 uniquement). Le panneau lui-même remonte de 25px
supplémentaires (`bottom: 45px` au lieu des 20px hérités) quand une story
est affichée, pour laisser plus de marge sous le mockup sans toucher à
ses proportions.

## 1.4.0

- **Stories** : tentative de correctif d'affichage (remplacée en 1.4.1) —
  le mockup de téléphone pouvait être coupé en bas du panneau sur les
  écrans de hauteur réduite (laptop, fenêtre non maximisée).
- **Stories** : couleur du fond du mockup téléphone, couleur de la croix
  de fermeture, couleur du fond de son bouton, couleur du fond plein écran
  (mobile), toutes personnalisables depuis *Configurer > Stories*. Note :
  le fond du bouton de fermeture passe d'un noir semi-transparent à une
  couleur pleine configurable (nécessaire pour rester personnalisable).
- **Position du bouton flottant** : gauche ou droite, dans *Configurer >
  Apparence*.
- **Aide / Documentation** : nouveau bloc en haut du Configure — lien vers
  le dépôt GitHub et lien pré-rempli pour ouvrir une issue (version du
  module/PrestaShop/thème déjà renseignées).
- `upgrade/upgrade-1.4.0.php` pour les installations déjà en place.

## 1.3.0

- **Visibilité par fonctionnalité** : chaque fonctionnalité pilotée depuis
  le bouton flottant (cookies, accessibilité, panier sticky, stories,
  mini-panier) a maintenant ses propres bascules "Afficher sur ordinateur"
  / "Afficher sur mobile" dans son propre bloc du Configure. Activées par
  défaut (aucun changement de comportement tant que rien n'est désactivé).
- **Stories** : nouveau bloc dédié — afficher ou non le titre de la bulle,
  épaisseur de sa bordure (px), en plus des bascules de visibilité.
  Bulles réalignées au centre de la rangée (`justify-content: center`).
- Réglages back-office désormais répartis en 6 sections (Bannière cookies,
  Accessibilité, Panier sticky, Stories, Mini-panier automatique,
  Apparence).
- `upgrade/upgrade-1.3.0.php` pour les installations déjà en place.

## 1.2.0

- **Mini-panier automatique** : ouverture automatique du mini-panier après
  un ajout au panier, fermeture automatique après quelques secondes.
  Repris de l'ancien module interne `hub-phy` et généricisé (sélecteurs du
  thème PrestaShop "Classic"). Désactivé par défaut, activable dans
  *Configurer > Mini-panier automatique*.
- **Apparence personnalisable** : nouvelle section *Configurer > Apparence*
  — couleur d'accent (+ variante foncée pour le survol/contraste), arrondi
  des boutons, arrondi de l'image produit du panier sticky. Appliqué via
  variables CSS injectées en tête de page, sans avoir à toucher aux
  fichiers du module.
- Réglages back-office réorganisés en 3 sections distinctes (Bannière
  cookies / Mini-panier automatique / Apparence) plutôt qu'un seul
  formulaire.
- Bulles stories : espacement augmenté, alignées à droite de la rangée.

## 1.1.1

Durcissement de `install()` — observé en conditions réelles (installation
via upload zip sur un shop avec de nombreux modules déjà installés) :
l'ancienne chaîne `&&` pouvait s'arrêter silencieusement en cours de route
(certains hooks jamais enregistrés, table jamais créée), sans message
d'erreur exploitable, laissant le module à moitié installé.

- Chaque étape d'installation (hooks, table, dossier d'upload) s'exécute
  désormais indépendamment ; tout échec est collecté et remonté comme
  erreur visible dans le Back Office au lieu d'échouer silencieusement.
- Nouveau filet de sécurité `ensureFullyInstalled()` : si une installation
  précédente s'est arrêtée en route, un simple accès à *Modules > Navi >
  Configurer* complète automatiquement ce qui manque (hooks, table),
  sans nécessiter une désinstallation/réinstallation complète.

## 1.1.0

Gestion native des bulles vidéo "stories" — absorbe la fonctionnalité
auparavant déléguée au module tiers `lstvideostory`, en corrigeant au
passage les problèmes trouvés lors de son analyse :

- Nouvelle table `navi_story` (jusqu'à 4 stories par produit), avec
  `id_shop` dès la conception (`lstvideostory` n'avait pas cette colonne).
- Onglet "Navi" sur la fiche produit du Back Office (`displayAdminProductsExtra`,
  un seul hook — `lstvideostory` en utilisait deux en parallèle pour la
  même fonction et affichait parfois la bulle deux fois côté front).
- Sauvegarde exclusivement via un enregistrement produit réel
  (`actionObjectProductAddAfter`/`UpdateAfter`) : **aucun contrôleur front
  dédié**, contrairement à `lstvideostory` dont le contrôleur de sauvegarde
  ne vérifiait jamais le jeton reçu (n'importe qui pouvait écraser les
  stories d'un produit sans session admin). Ici, la sauvegarde est protégée
  par la session employé et le jeton CSRF que PrestaShop applique lui-même
  au formulaire produit.
- Upload MP4 validé et limité en taille (20 Mo max, réellement appliqué —
  `lstvideostory` ne vérifiait aucune taille), validation centralisée dans
  une seule méthode (dupliquée à 3 endroits dans `lstvideostory`), plus de
  repli permissif sur `application/octet-stream`.
- Dossier d'upload durci (`.htaccess` interdisant toute exécution de
  script) et entièrement nettoyé à la désinstallation, fichiers compris
  (`lstvideostory` ne supprimait que sa table, laissait les vidéos
  orphelines sur le disque).
- Rendu front sur un seul hook (`displayAfterProductThumbs`), branché
  directement sur le moteur `stories.js` (panneau desktop + plein écran
  mobile) déjà présent depuis la 1.0.0 — plus d'interception DOM d'un
  module tiers.

## 1.0.0

Première version publique du module. Bootstrap du hub générique, sans
gestion native des stories (à venir dans une prochaine version) :

- Bouton flottant (FAB) à 3 états (fermé/menu/détail), API JS partagée
  `window.navi`.
- Consentement cookies (Google Consent Mode v2), bannière + modale de
  préférences.
- Panneau accessibilité (taille du texte, contraste, curseur agrandi,
  soulignage des liens).
- Panier sticky sur les fiches produit.
- Moteur d'affichage des stories (panneau desktop + plein écran mobile,
  mockup de téléphone en CSS pur) présent mais pas encore câblé côté PHP —
  aucune bulle n'est rendue tant que la gestion native (base de données,
  formulaire produit, upload vidéo) n'est pas livrée.
- CI (lint PHP/JS, synchronisation de version) et suite de tests Playwright
  (FAB, cookies, panier sticky).

Base dérivée du module interne `hub-phy`, généricisée pour une
distribution publique : couleurs par défaut neutres (exposées en variables
CSS surchargeables), aucune dépendance à un thème ou module tiers
spécifique, aucun asset sans licence claire.
