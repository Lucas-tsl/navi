# Navi

<img src="assets/logo.png" alt="Logo Navi" width="96" height="96">

Hub d'engagement flottant pour PrestaShop : un seul bouton flottant qui
regroupe plusieurs modules d'engagement client — consentement cookies
(Google Consent Mode v2), accessibilité (taille du texte, contraste,
curseur), ajout au panier sticky, et bulles vidéo "stories" sur les fiches
produit — chacun s'affichant dans le même objet visuel plutôt que comme des
widgets indépendants.

## État actuel

- ✅ Bouton flottant (FAB) à 3 états : fermé / menu / détail.
- ✅ Bannière + modale de consentement cookies (Google Consent Mode v2).
- ✅ Panneau accessibilité (taille du texte, contraste élevé, curseur
  agrandi, soulignage des liens).
- ✅ Panier sticky sur les fiches produit (suit le vrai bouton "Ajouter au
  panier" du thème, y compris son état rupture de stock).
- ✅ Gestion native de bulles vidéo "stories" par produit : onglet
  d'administration sur la fiche produit (jusqu'à 4 stories, vidéo YouTube
  ou upload MP4, cartes avec aperçu YouTube en direct), rendu front
  (panneau desktop + plein écran mobile). Aucune dépendance à un module
  tiers.
- ✅ Mini-panier automatique (optionnel, désactivé par défaut).
- ✅ Apparence personnalisable depuis le Back Office (couleur d'accent,
  arrondi des boutons, arrondi de l'image produit).
- ✅ Visibilité par fonctionnalité et par appareil ("Afficher sur
  ordinateur"/"Afficher sur mobile", indépendamment pour chaque
  fonctionnalité), titre et bordure des bulles stories personnalisables.
- ✅ Position du bouton flottant (gauche/droite), couleurs du panneau
  stories (mockup, croix, plein écran mobile) personnalisables.
- ✅ Bloc Aide/Documentation dans le Configure (lien repo + création
  d'issue pré-remplie).
- ✅ Épaisseur du cadre et taille du mockup de téléphone des stories
  réglables via curseurs gradués, avec aperçu en direct.
- ✅ Configure organisé en onglets (un par fonctionnalité), chacun avec
  son propre formulaire.

## Installation

1. Copier le dossier `navi/` dans `modules/` de l'installation PrestaShop
   cible.
2. Installer le module depuis le Back Office (Modules > Gestionnaire de
   modules).
3. Configurer la bannière cookies (Modules > Navi > Configurer) : logo,
   texte, liens politique de confidentialité / mentions légales.

Compatible PrestaShop 1.7 → 8.x (voir `ps_versions_compliancy` dans
`navi/navi.php`).

## Architecture

```
navi/
├── navi.php                        # Hooks, config back-office
├── config.xml
├── classes/
│   └── NaviStoryManager.php        # Table navi_story, upload/validation MP4, YouTube
└── views/
    ├── css/       core.css, cookie-consent.css, accessibility.css,
    │              sticky-cart.css, stories.css
    ├── js/        core.js, cookie-consent.js, accessibility.js,
    │              sticky-cart.js, stories.js, mini-cart.js
    ├── uploads/                     # Vidéos MP4 uploadées (stories), .htaccess durci
    └── templates/
        ├── hook/footer.tpl          # Coquille du bouton flottant + panneaux
        ├── hook/story-bubbles.tpl   # Bulles stories en fiche produit
        └── admin/story-fields.tpl   # Onglet stories sur la fiche produit (BO)
```

### Stories

Gestion entièrement native (table `navi_story`, jusqu'à 4 stories par
produit) — pas de dépendance à un module tiers. La sauvegarde ne passe
QUE par un enregistrement produit réel (`actionObjectProductAddAfter`/
`UpdateAfter`) : il n'existe aucun contrôleur front dédié, donc aucune
surface exposée sans la session employé et le jeton CSRF déjà appliqués
par PrestaShop au formulaire produit. Upload MP4 validé (extension +
MIME stricts) et limité en taille ; dossier d'upload durci contre toute
exécution de script et entièrement nettoyé à la désinstallation.

Le bouton flottant (`#navi-fab`) est un **seul objet DOM** qui traverse 3
états (`data-state="closed|menu|detail"`) plutôt que plusieurs widgets
indépendants — voir `views/js/core.js` pour l'API exposée
(`window.navi.showDetail/hideDetail/backToMenu/forceClose/wireCloseButton`)
que chaque module (cookies, accessibilité, panier sticky, stories) utilise
pour s'intégrer au même objet.

Les couleurs sont exposées en variables CSS (`--navi-color-*`,
`views/css/core.css`) et peuvent être surchargées par surcouche CSS du
thème sans toucher aux fichiers du module.

## Consentement cookies — fonctionnement et intégration

### Comment ça marche

1. **Avant même que la bannière s'affiche**, `hookDisplayHeader()` injecte
   un `<script>` en tout début de `<head>` qui pose un
   [Google Consent Mode v2](https://developers.google.com/tag-platform/security/guides/consent)
   par défaut : tout refusé (`denied`) tant que l'utilisateur n'a pas fait
   de choix. Un stub `gtag()` minimal (qui empile juste les appels dans
   `window.dataLayer`) est défini à cet instant si aucun `gtag` réel
   n'existe encore — l'appel `consent/default` est donc pris en compte
   quel que soit l'ordre de chargement réel de Google Analytics/Tag
   Manager, **à condition que ce hook s'exécute avant leur propre
   script** (voir Mise en place ci-dessous).
2. La bannière (bas de l'écran) et la modale de préférences (accessible
   depuis le bouton flottant) laissent l'utilisateur choisir : Tout
   accepter / Tout refuser / Personnaliser (statistiques et marketing
   séparément).
3. Le choix est stocké dans 4 cookies (`samesite=strict`, `secure` si
   HTTPS, expiration 1 an) : `navi_consent_all` (un choix a été fait),
   `navi_consent_version`, `navi_consent_stats`, `navi_consent_mkt`
   (`1`/`0` chacun).
4. À l'enregistrement du choix (`views/js/cookie-consent.js`) :
   - si un `gtag` réel existe déjà, `gtag('consent', 'update', {...})`
     est appelé avec les nouveaux statuts (`ad_storage`, `ad_user_data`,
     `ad_personalization`, `analytics_storage`) ;
   - un événement est poussé dans `window.dataLayer` :
     `{ event: 'navi_cookie_consent_updated' }`.

### Mise en place

- **L'ordre des hooks `displayHeader` n'est pas garanti par PrestaShop à
  l'installation.** Si le `consent/default` de Navi s'exécute *après* le
  script Google Analytics/Tag Manager, le blocage ne sert à rien (le
  tracking a déjà eu l'occasion de démarrer). À vérifier une fois après
  installation : *Back Office > Modules > Positions > `displayHeader`* —
  Navi doit apparaître **avant** tout module Analytics/Tag Manager/pixel
  dans la liste.
- Configurer la bannière dans *Modules > Navi > Configurer > Cookies*
  (logo, texte, liens politique de confidentialité / mentions légales).

### Synchroniser avec vos autres outils

**Google Analytics (GA4) / Google Tag Manager, avec les tags natifs
Google (`gtag.js`, balises GA4/Google Ads dans GTM)** : rien à faire —
ces tags respectent nativement le Consent Mode v2, les signaux posés par
Navi suffisent, à condition que l'ordre des hooks soit correct (voir
ci-dessus).

**Tout le reste (Meta/Facebook Pixel, TikTok Pixel, Hotjar, Clarity, un
tag HTML personnalisé dans GTM...)** : le Consent Mode v2 ne concerne
*que* les produits Google — ces outils ne réagissent pas automatiquement
aux signaux `gtag('consent', ...)`. Deux façons de les synchroniser :

- **Dans Google Tag Manager** : déclencher ces balises sur un
  événement personnalisé nommé `navi_cookie_consent_updated`
  (Trigger > Custom Event), puis lire `navi_consent_mkt`/
  `navi_consent_stats` (cookies, `1` = accepté) dans une variable pour
  n'activer la balise que si le consentement correspondant a été donné.
- **En JS personnalisé (hors GTM)** : écouter l'événement et vérifier le
  cookie avant d'injecter le script tiers, par exemple :

  ```js
  document.addEventListener('DOMContentLoaded', function () {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push = (function (originalPush) {
          return function (data) {
              if (data && data.event === 'navi_cookie_consent_updated') {
                  var mktGranted = document.cookie.indexOf('navi_consent_mkt=1') !== -1;
                  if (mktGranted) {
                      // charger ici le script du Pixel Meta / TikTok / etc.
                  }
              }
              return originalPush.apply(window.dataLayer, arguments);
          };
      })(window.dataLayer.push);
  });
  ```

  (Vérifier aussi l'état au chargement de la page, pas seulement au
  changement — un visiteur qui revient avec un consentement déjà donné
  ne déclenche pas de nouvel événement.)

## Développement

### Synchronisation de version

`navi.php` (`$this->version`) et `config.xml` (`<version>`) doivent rester
identiques — vérifié par `scripts/check-version-sync.sh`, exécuté en CI.

### Déploiement local

`scripts/deploy-local.sh` copie `navi/` vers une instance PrestaShop de dev
(Docker), corrige les permissions (`chown www-data`) et purge le cache
CCC du thème — le cycle manuel (`docker cp` + `chown` + purge de
`themes/<thème>/assets/cache/`) refait jusque-là à chaque itération.
Variables d'environnement : `NAVI_DEPLOY_CONTAINER` (défaut `presta_web`),
`NAVI_DEPLOY_THEME` (défaut `physiomins`), `NAVI_DEPLOY_BASE_URL` (défaut
`http://localhost:8080`).

### Tests

Suite Playwright dans `tests/` — voir `tests/README.md`. Nécessite une
vraie instance PrestaShop (pas branchée en CI, à lancer manuellement).

### CI

`.github/workflows/ci.yml` : lint PHP (`php -l`), vérification syntaxe JS
(`node --check`), synchronisation de version.

## Limitations connues

- Panier sticky : pas de sélecteur de variation/déclinaison pour les
  produits avec combinaisons — voir
  [issue #1](https://github.com/Lucas-tsl/navi-prestashop/issues/1),
  contributions bienvenues.
- Traductions : les chaînes passent par `$this->l()`/`{l}` mais aucun
  fichier `translations/<iso>.php` n'existe encore — le module est
  aujourd'hui francophone par défaut.
- Multiboutique : non testé/optimisé spécifiquement.

## Licence

MIT — voir [`LICENSE`](LICENSE).
