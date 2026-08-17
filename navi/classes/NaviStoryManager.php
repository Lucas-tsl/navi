<?php
/**
 * Couche données + upload des "stories" vidéo produit — extrait de navi.php
 * (qui approchait 1600 lignes) pour séparer la logique métier des stories
 * du reste du module (hub d'engagement, formulaires BO). Les hooks
 * PrestaShop restent déclarés sur la classe Navi (obligatoire : le noyau
 * appelle les méthodes hookXxx par nom sur l'instance du module), mais
 * délèguent ici pour tout ce qui touche la table `navi_story` et les
 * fichiers uploadés.
 *
 * Absorbe la fonctionnalité auparavant déléguée au module tiers
 * lstvideostory, en corrigeant au passage les points trouvés lors de
 * l'analyse de ce module : sauvegarde exposée sur un contrôleur front
 * public non protégé par un jeton vérifié (ici : aucun contrôleur front
 * du tout, la sauvegarde ne passe QUE par le formulaire produit réel du
 * Back Office, donc déjà couverte par la session employé et le jeton CSRF
 * que PrestaShop applique lui-même) ; validation d'upload dupliquée à
 * plusieurs endroits (ici centralisée) ; aucune limite de taille
 * réellement appliquée (ici : MAX_UPLOAD_BYTES) ; aucun nettoyage des
 * fichiers uploadés à la désinstallation (ici : uninstallUploadDir()).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class NaviStoryManager
{
    const STORIES_TABLE = 'navi_story';
    const STORY_LIMIT = 4;
    const UPLOAD_SUBDIR = 'views/uploads';
    const MAX_UPLOAD_BYTES = 20971520; // 20 Mo

    /** @var Navi */
    private $module;

    /**
     * Un flag statique évite un double traitement si plusieurs hooks du
     * cycle de sauvegarde produit se déclenchaient pour la même requête
     * (le bug inverse observé sur lstvideostory : un upload ne peut être
     * déplacé qu'une seule fois, un second passage sur le même $_FILES
     * échouerait silencieusement et effacerait la story qui venait d'être
     * créée).
     */
    private static $storiesSavedThisRequest = [];

    public function __construct(Navi $module)
    {
        $this->module = $module;
    }

    public function installTable()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::STORIES_TABLE . '` (
                `id_navi_story` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `story_index` TINYINT UNSIGNED NOT NULL,
                `youtube` VARCHAR(32) NOT NULL,
                `preview` VARCHAR(255) NOT NULL,
                `label` VARCHAR(128) NOT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_navi_story`),
                UNIQUE KEY `shop_product_story` (`id_shop`, `id_product`, `story_index`),
                KEY `id_product` (`id_product`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public function uninstallTable()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::STORIES_TABLE . '`');
    }

    public function getUploadDirectory()
    {
        return _PS_MODULE_DIR_ . $this->module->name . '/' . self::UPLOAD_SUBDIR . '/';
    }

    public function installUploadDir()
    {
        $dir = $this->getUploadDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        return true;
    }

    /**
     * Contrairement à lstvideostory, qui ne supprimait que sa table à la
     * désinstallation : les fichiers vidéo uploadés (potentiellement
     * volumineux, et des données du marchand) doivent partir avec le
     * module, pas rester orphelins sur le disque indéfiniment.
     */
    public function uninstallUploadDir()
    {
        $dir = $this->getUploadDirectory();
        if (!is_dir($dir)) {
            return true;
        }

        foreach (glob($dir . '*.mp4') as $file) {
            @unlink($file);
        }

        return true;
    }

    /**
     * Récupère les stories du produit pour la boutique courante — scoping
     * par id_shop dès la conception (contrairement à lstvideostory, qui
     * n'avait pas cette colonne et mélangeait les données entre boutiques
     * d'un même multiboutique).
     */
    public function getStoriesForProduct($idProduct, $idShop = null)
    {
        if ($idShop === null) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::STORIES_TABLE . '`
             WHERE `id_product` = ' . (int) $idProduct . '
             AND `id_shop` = ' . (int) $idShop . '
             ORDER BY `story_index` ASC'
        );
    }

    public function deleteStoriesForProduct($idProduct, $idShop)
    {
        return Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::STORIES_TABLE . '`
             WHERE `id_product` = ' . (int) $idProduct . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Accepte une URL YouTube complète (watch?v=, youtu.be/, /shorts/) ou
     * un identifiant brut de 11 caractères déjà saisi tel quel.
     */
    public function extractYoutubeId($input)
    {
        $input = trim((string) $input);
        if ($input === '') {
            return '';
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        return '';
    }

    /**
     * Validation centralisée (contrairement à lstvideostory, dupliquée à 3
     * endroits) : extension + MIME stricts (pas de repli sur
     * application/octet-stream, trop permissif), taille plafonnée.
     * Retourne un message d'erreur, ou '' si le fichier est accepté.
     */
    public function validateMp4Upload(array $file)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->module->l('Erreur lors du transfert du fichier.');
        }

        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            return sprintf($this->module->l('Le fichier dépasse la taille maximale autorisée (%d Mo).'), self::MAX_UPLOAD_BYTES / 1048576);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'mp4') {
            return $this->module->l('Seuls les fichiers .mp4 sont acceptés.');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'video/mp4') {
                return $this->module->l('Le fichier ne semble pas être une vidéo MP4 valide.');
            }
        }

        return '';
    }

    /**
     * Déplace un upload validé vers le dossier du module et retourne son
     * URL publique, ou null si aucun fichier valide n'a été soumis pour cet
     * index (absence de fichier n'est pas une erreur : l'admin peut avoir
     * laissé ce champ vide volontairement).
     */
    public function handleUploadedPreview($index, &$errors)
    {
        $field = 'navi_story_preview_file_' . (int) $index;
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        $error = $this->validateMp4Upload($file);
        if ($error !== '') {
            $errors[] = sprintf('#%d — %s', $index, $error);

            return null;
        }

        $filename = 'story_' . (int) $index . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.mp4';
        $destination = $this->getUploadDirectory() . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = sprintf('#%d — %s', $index, $this->module->l("Échec de l'enregistrement du fichier."));

            return null;
        }

        // Module::$_path est protégée (inaccessible depuis cette classe) ;
        // reconstruite ici avec la même formule que ModuleCore::__construct().
        return __PS_BASE_URI__ . 'modules/' . $this->module->name . '/' . self::UPLOAD_SUBDIR . '/' . $filename;
    }

    /**
     * Point d'entrée UNIQUE de sauvegarde, appelé uniquement depuis un
     * enregistrement produit réel (voir hookActionObjectProduct*After sur
     * Navi) — jamais depuis un contrôleur front public.
     */
    public function handleProductSave($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct || !Tools::isSubmit('navi_story_submitted')) {
            return;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $requestKey = $idShop . ':' . $idProduct;
        if (isset(self::$storiesSavedThisRequest[$requestKey])) {
            return;
        }
        self::$storiesSavedThisRequest[$requestKey] = true;

        $errors = [];
        $rows = [];
        $now = date('Y-m-d H:i:s');

        for ($index = 1; $index <= self::STORY_LIMIT; $index++) {
            $youtube = $this->extractYoutubeId(Tools::getValue('navi_story_youtube_' . $index));
            if ($youtube === '') {
                continue; // slot vide : pas de story à cet emplacement.
            }

            $uploadedUrl = $this->handleUploadedPreview($index, $errors);
            $preview = $uploadedUrl
                ?: (string) Tools::getValue('navi_story_preview_' . $index)
                ?: 'https://img.youtube.com/vi/' . $youtube . '/maxresdefault.jpg';

            $rows[] = [
                'id_product' => $idProduct,
                'id_shop' => $idShop,
                'story_index' => $index,
                'youtube' => pSQL($youtube),
                'preview' => pSQL($preview),
                'label' => pSQL((string) Tools::getValue('navi_story_label_' . $index)),
                'date_add' => $now,
                'date_upd' => $now,
            ];
        }

        $this->deleteStoriesForProduct($idProduct, $idShop);
        foreach ($rows as $row) {
            Db::getInstance()->insert(self::STORIES_TABLE, $row);
        }

        if (!empty($errors)) {
            Context::getContext()->controller->errors[] = $this->module->l('Stories Navi : certains fichiers ont été ignorés.') . ' ' . implode(' ', $errors);
        }
    }
}
