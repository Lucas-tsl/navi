# Navi

Hub d'engagement flottant pour PrestaShop : un seul bouton flottant qui
regroupe plusieurs modules d'engagement client — consentement cookies
(Google Consent Mode v2), accessibilité (taille du texte, contraste,
curseur), ajout au panier sticky sur les fiches produit, et (à venir)
gestion native de bulles vidéo "stories" sur les fiches produit — chacun
s'affichant dans le même objet visuel plutôt que comme des widgets
indépendants.

## État actuel

- ✅ Bouton flottant (FAB) à 3 états : fermé / menu / détail.
- ✅ Bannière + modale de consentement cookies (Google Consent Mode v2).
- ✅ Panneau accessibilité (taille du texte, contraste élevé, curseur
  agrandi, soulignage des liens).
- ✅ Panier sticky sur les fiches produit (suit le vrai bouton "Ajouter au
  panier" du thème, y compris son état rupture de stock).
- 🚧 Gestion native de bulles vidéo "stories" par produit (base de données,
  formulaire d'administration produit, upload de vidéo, rendu front) — le
  moteur d'affichage (panneau desktop + plein écran mobile) existe déjà
  (`views/js/stories.js`, `views/css/stories.css`) mais n'est pas encore
  câblé côté PHP : aucune bulle n'est rendue tant que ce chantier n'est pas
  terminé.

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
└── views/
    ├── css/       core.css, cookie-consent.css, accessibility.css,
    │              sticky-cart.css, stories.css
    ├── js/        core.js, cookie-consent.js, accessibility.js,
    │              sticky-cart.js, stories.js
    └── templates/hook/footer.tpl   # Coquille du bouton flottant + panneaux
```

Le bouton flottant (`#navi-fab`) est un **seul objet DOM** qui traverse 3
états (`data-state="closed|menu|detail"`) plutôt que plusieurs widgets
indépendants — voir `views/js/core.js` pour l'API exposée
(`window.navi.showDetail/hideDetail/backToMenu/forceClose/wireCloseButton`)
que chaque module (cookies, accessibilité, panier sticky, stories) utilise
pour s'intégrer au même objet.

Les couleurs sont exposées en variables CSS (`--navi-color-*`,
`views/css/core.css`) et peuvent être surchargées par surcouche CSS du
thème sans toucher aux fichiers du module.

## Développement

### Synchronisation de version

`navi.php` (`$this->version`) et `config.xml` (`<version>`) doivent rester
identiques — vérifié par `scripts/check-version-sync.sh`, exécuté en CI.

### Tests

Suite Playwright dans `tests/` — voir `tests/README.md`. Nécessite une
vraie instance PrestaShop (pas branchée en CI, à lancer manuellement).

### CI

`.github/workflows/ci.yml` : lint PHP (`php -l`), vérification syntaxe JS
(`node --check`), synchronisation de version.

## Limitations connues

- Panier sticky : pas de sélecteur de variation/déclinaison pour les
  produits avec combinaisons.
- Traductions : les chaînes passent par `$this->l()`/`{l}` mais aucun
  fichier `translations/<iso>.php` n'existe encore — le module est
  aujourd'hui francophone par défaut.
- Multiboutique : non testé/optimisé spécifiquement.

## Licence

MIT — voir [`LICENSE`](LICENSE).
