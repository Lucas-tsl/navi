#!/usr/bin/env bash
# Déploie le module navi/ de ce dépôt vers l'instance PrestaShop de dev
# locale (conteneur Docker), puis vide le cache qui masquerait sinon les
# changements côté front.
#
# Pourquoi ce script : jusqu'ici ce cycle (docker cp fichier par fichier,
# chown www-data, purge du cache CCC, vérification via curl) était refait
# à la main à chaque itération — source d'oublis (voir memory du projet :
# un oubli de -u www-data lors d'un test a pollué le cache Smarty en root
# et cassé le site pour de vrais visiteurs ; un oubli de purge CCC a déjà
# fait courir après un bug déjà corrigé côté PHP mais invisible côté front).
#
# Usage : ./scripts/deploy-local.sh [--no-verify]
#   NAVI_DEPLOY_CONTAINER : nom du conteneur (défaut presta_web)
#   NAVI_DEPLOY_THEME     : thème dont on purge le cache CCC (défaut physiomins)
#   NAVI_DEPLOY_BASE_URL  : URL utilisée pour la vérification finale
#                            (défaut http://localhost:8080)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
MODULE_SRC="$REPO_ROOT/navi"

CONTAINER="${NAVI_DEPLOY_CONTAINER:-presta_web}"
THEME="${NAVI_DEPLOY_THEME:-physiomins}"
BASE_URL="${NAVI_DEPLOY_BASE_URL:-http://localhost:8080}"
MODULE_DEST="/var/www/html/modules/navi"

VERIFY=1
for arg in "$@"; do
    case "$arg" in
        --no-verify) VERIFY=0 ;;
        *)
            echo "Argument inconnu : $arg" >&2
            exit 1
            ;;
    esac
done

if [ ! -d "$MODULE_SRC" ]; then
    echo "Erreur : $MODULE_SRC introuvable" >&2
    exit 1
fi

if ! docker exec "$CONTAINER" true 2>/dev/null; then
    echo "Erreur : conteneur '$CONTAINER' inaccessible (docker exec a échoué)" >&2
    exit 1
fi

echo "==> Copie de $MODULE_SRC vers $CONTAINER:$MODULE_DEST"
docker exec "$CONTAINER" mkdir -p "$MODULE_DEST"
docker cp "$MODULE_SRC/." "$CONTAINER:$MODULE_DEST"

# Toujours en www-data : docker cp copie avec l'UID de l'hôte, et tout
# script exécuté ensuite en root (install/upgrade/rendu Smarty) créerait
# des fichiers root-owned que www-data ne pourrait plus écrire à côté
# (var/cache/prod/smarty/compile/...) — cf memory "cache-clearing-caution".
echo "==> chown www-data:www-data sur $MODULE_DEST"
docker exec "$CONTAINER" chown -R www-data:www-data "$MODULE_DEST"

echo "==> Purge du cache CCC (thème $THEME)"
docker exec "$CONTAINER" sh -c "rm -f /var/www/html/themes/$THEME/assets/cache/*.css /var/www/html/themes/$THEME/assets/cache/*.js" 2>/dev/null || true

if [ "$VERIFY" -eq 1 ]; then
    echo "==> Vérification HTTP ($BASE_URL)"
    status=$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL" || echo "000")
    if [ "$status" != "200" ]; then
        echo "Attention : $BASE_URL a répondu $status (pas 200) — vérifier manuellement avant de continuer" >&2
        exit 1
    fi
    echo "OK : $BASE_URL répond 200"

    stale=$(docker exec "$CONTAINER" sh -c "find /var/www/html/var/cache -not -user www-data 2>/dev/null | wc -l" || echo "?")
    if [ "$stale" != "0" ]; then
        echo "Attention : $stale fichier(s) de cache non détenus par www-data — voir memory 'cache-clearing-caution'" >&2
    fi
fi

echo "==> Déploiement terminé"
