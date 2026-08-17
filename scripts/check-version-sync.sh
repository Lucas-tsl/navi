#!/usr/bin/env bash
# Vérifie que la version déclarée dans navi.php ($this->version) et celle
# de config.xml (<version>) sont identiques.
#
# Pourquoi : PrestaShop lit principalement config.xml pour l'affichage/la
# détection de mise à jour dans le Back Office, donc un oubli de bump sur
# l'un des deux fichiers passe inaperçu jusqu'à ce que le numéro affiché ne
# corresponde plus au vrai comportement du module.
#
# Usage : ./scripts/check-version-sync.sh (exit 0 si synchronisées, 1 sinon)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

PHP_FILE="$REPO_ROOT/navi/navi.php"
XML_FILE="$REPO_ROOT/navi/config.xml"

php_version=$(grep -oP "\\\$this->version\s*=\s*'\K[0-9]+\.[0-9]+\.[0-9]+" "$PHP_FILE" || true)
xml_version=$(grep -oP "<version><!\[CDATA\[\K[0-9]+\.[0-9]+\.[0-9]+" "$XML_FILE" || true)

if [ -z "$php_version" ]; then
    echo "Erreur : version introuvable dans $PHP_FILE" >&2
    exit 1
fi

if [ -z "$xml_version" ]; then
    echo "Erreur : version introuvable dans $XML_FILE" >&2
    exit 1
fi

if [ "$php_version" != "$xml_version" ]; then
    echo "Versions désynchronisées :" >&2
    echo "  navi.php   : $php_version" >&2
    echo "  config.xml : $xml_version" >&2
    exit 1
fi

echo "OK : versions synchronisées ($php_version)"
