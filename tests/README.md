# Tests navi

Suite de non-régression Playwright — tests d'INTÉGRATION, pas des tests
unitaires : ils s'exécutent contre une vraie instance PrestaShop avec
`navi` installé et actif, pas dans le vide.

## Ce que ça n'est pas

Pas branchés en CI pour l'instant (nécessite une instance PrestaShop
complète, catalogue compris) — à lancer manuellement en local avant de
merger un changement qui touche au comportement du module.

## Prérequis

- Une instance PrestaShop avec `navi` installé et actif, accessible en HTTP
  (par défaut `http://localhost:8080`).
- Le catalogue doit contenir :
  - un produit **en stock** (`NAVI_TEST_PRODUCT_IN_STOCK`, défaut `1`)
  - un produit **en rupture** (`NAVI_TEST_PRODUCT_OUT_OF_STOCK`, défaut `2`)
  - optionnel : un produit avec **au moins une story** configurée depuis
    l'onglet Navi (`NAVI_TEST_PRODUCT_WITH_STORY`, aucun défaut — la spec
    `stories.spec.js` est entièrement `skip()`ée si cette variable n'est
    pas définie)

  Ces identifiants sont propres au catalogue de l'environnement testé — à
  passer en variable d'environnement. Les tests concernés se `skip()`
  proprement (pas d'échec) si le produit configuré n'a pas le contenu
  attendu.

## Installation et lancement

```bash
cd tests
npm install
npx playwright install chromium --with-deps   # une seule fois
npm test
```

Contre un environnement différent de `localhost:8080` :

```bash
NAVI_TEST_BASE_URL=https://exemple.com npm test
```

## Ajouter un test

Un bug réel trouvé en session mérite un test de non-régression — courte
spec ciblée, commentaire pointant vers l'entrée `CHANGELOG.md`
correspondante, `test.skip()` propre plutôt qu'un échec si les données de
test ne s'y prêtent pas sur un autre environnement.
