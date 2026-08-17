# Changelog

## 1.7.0

**Onglet "Stories" de la fiche produit modernisé** (`views/templates/admin/story-fields.tpl`) :

- 4 stories affichées en cartes (grille 2 colonnes) plutôt qu'empilées
  verticalement, avec badge "Vide"/"Configurée" par carte.
- Aperçu en direct : coller une URL ou un identifiant YouTube affiche
  immédiatement sa vignette, sans attendre l'enregistrement.
- Retour visuel sur le fichier importé (nom, taille) avec avertissement
  si la taille dépasse la limite autorisée, avant même de soumettre le
  formulaire.
- Champs "prévisualisation personnalisée" (URL MP4 / import) repliés
  dans un `<details>` — le cas courant (juste un lien YouTube) reste
  visible en premier.
- Corrigé au passage : un quantificateur regex (`{11}`) dans le
  JavaScript embarqué était silencieusement interprété par Smarty comme
  un littéral numérique, avalant les accolades et cassant l'extraction
  d'identifiant YouTube — le script concerné est maintenant protégé par
  `{literal}...{/literal}`.

## 1.6.0

- **Configure réorganisé en onglets** : un onglet par fonctionnalité (Aide,
  Cookies, Accessibilité, Panier sticky, Stories, Mini-panier, Apparence)
  au lieu d'un unique formulaire qui s'allongeait à chaque réglage ajouté.
  Chaque onglet est un formulaire indépendant avec son propre bouton
  "Enregistrer" — modifier un réglage ne renvoie plus tous les autres.
  Navigation par onglets avec `role="tablist"/"tab"/"tabpanel"` et
  synchronisation de `aria-selected` au clic.
- Dépôt GitHub renommé `navi` → `navi-prestashop` (l'auteur prépare un
  dépôt séparé pour une solution WordPress) : toutes les URLs internes du
  module (lien Documentation, création d'issue) mises à jour en
  conséquence.

## 1.5.1

Correction du curseur "épaisseur" ajouté en 1.5.0 : contrôlait par erreur
une bordure CSS sur l'écran vidéo au lieu du **padding du mockup de
téléphone** (l'espace entre le bord du téléphone et l'écran, qui donne
visuellement l'impression d'un cadre) — c'était le vrai sens de la demande
initiale. `NAVI_STORIES_VIDEO_BORDER_WIDTH` remplacé par
`NAVI_STORIES_PHONE_PADDING` ; `upgrade/upgrade-1.5.1.php` reprend la
valeur déjà choisie si elle existe.

## 1.5.0

- **Stories — aspect de la vidéo** : nouveau bloc dans le Configure avec
  deux curseurs gradués (épaisseur de la bordure autour de l'écran vidéo,
  taille du mockup de téléphone) et un aperçu en direct qui se met à jour
  pendant qu'on glisse le curseur. Formulaire indépendant des autres
  réglages Stories (soumission séparée), HelperForm ne proposant pas de
  champ de type curseur.
- `upgrade/upgrade-1.5.0.php` pour les installations déjà en place.

## 1.4.3

- Libellé des bulles stories : passe à la ligne au lieu d'être tronqué
  ("..." ou coupé net) quand il dépasse la largeur de la bulle — même
  règle sur mobile et desktop, la largeur disponible fait le reste.

## 1.4.2

- Retiré le décalage `bottom: 45px` du panneau stories ajouté en 1.4.1 —
  revient à l'espacement standard (20px, hérité de `.navi-fab`).
- `.navi-story-phone` : largeur maximale réduite de 210px à 200px.

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
