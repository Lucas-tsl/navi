# Changelog

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
