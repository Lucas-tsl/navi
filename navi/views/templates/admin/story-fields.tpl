{*
  Onglet "Navi" (bulles vidéo stories) sur la fiche produit — voir
  Navi::hookDisplayAdminProductsExtra(). Les champs sont lus directement
  depuis $_POST/$_FILES par Navi::handleProductSave() lors de
  l'enregistrement du produit : ce bloc fait partie du même formulaire que
  le reste de la fiche produit, pas un formulaire séparé — les noms de
  champs (navi_story_youtube_N, navi_story_label_N, navi_story_preview_N,
  navi_story_preview_file_N, navi_story_submitted) ne doivent pas changer.
*}
<style>
    .navi-story-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 16px;
    }
    .navi-story-card {
        flex: 1 1 calc(50% - 16px);
        min-width: 280px;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        background: #fff;
    }
    .navi-story-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #f6f6f6;
        border-bottom: 1px solid #ddd;
    }
    .navi-story-card-title {
        font-weight: 700;
    }
    .navi-story-card-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        background: #e5e5e5;
        color: #666;
    }
    .navi-story-card-badge.is-filled {
        background: #d4edda;
        color: #256029;
    }
    .navi-story-card-preview {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 140px;
        background: #222;
    }
    .navi-story-card-preview img {
        max-width: 100%;
        max-height: 100%;
        display: none;
    }
    .navi-story-card-placeholder {
        color: #999;
        font-size: .8125rem;
    }
    .navi-story-card-body {
        padding: 14px;
    }
    .navi-story-card-body label {
        font-weight: 600;
        font-size: .8125rem;
        margin-bottom: 4px;
    }
    .navi-story-card-body .form-group {
        margin-bottom: 12px;
    }
    .navi-story-card-body details {
        margin-top: 8px;
    }
    .navi-story-card-body summary {
        cursor: pointer;
        font-size: .8125rem;
        color: #666;
        margin-bottom: 8px;
    }
    .navi-story-file-info {
        font-size: .75rem;
        margin-top: 4px;
    }
    .navi-story-file-info.is-warning {
        color: #c0392b;
        font-weight: 700;
    }
</style>

<div class="panel">
    <h3><i class="icon-video-camera"></i> {l s='Stories (Navi)' mod='navi'}</h3>
    <p class="alert alert-info">
        {l s='Jusqu\'à 4 stories par produit. Chaque story affiche une bulle vidéo cliquable sur la fiche produit.' mod='navi'}
        {l s='Collez une URL ou un identifiant YouTube pour un aperçu immédiat, ou importez une vidéo MP4 (max. %d Mo).' sprintf=[$navi_story_max_mb] mod='navi'}
    </p>

    <input type="hidden" name="navi_story_submitted" value="1">

    <div class="navi-story-grid">
        {foreach from=$navi_story_slots item=slot}
            <div class="navi-story-card" data-slot="{$slot.index}">
                <div class="navi-story-card-header">
                    <span class="navi-story-card-title">{l s='Story' mod='navi'} #{$slot.index}</span>
                    <span class="navi-story-card-badge{if $slot.youtube} is-filled{/if}" id="navi-story-badge-{$slot.index}">
                        {if $slot.youtube}{l s='Configurée' mod='navi'}{else}{l s='Vide' mod='navi'}{/if}
                    </span>
                </div>

                <div class="navi-story-card-preview">
                    <img id="navi-story-thumb-{$slot.index}"
                         src="{$slot.thumbnail|escape:'html':'UTF-8'}"
                         alt=""
                         style="{if $slot.thumbnail}display:block;{/if}">
                    <span class="navi-story-card-placeholder" id="navi-story-placeholder-{$slot.index}" style="{if $slot.thumbnail}display:none;{/if}">
                        {l s='Aucune vidéo' mod='navi'}
                    </span>
                </div>

                <div class="navi-story-card-body">
                    <div class="form-group">
                        <label for="navi_story_youtube_{$slot.index}">{l s='URL ou identifiant YouTube' mod='navi'}</label>
                        <input type="text" class="form-control navi-story-youtube-input"
                               id="navi_story_youtube_{$slot.index}"
                               name="navi_story_youtube_{$slot.index}"
                               value="{$slot.youtube|escape:'html':'UTF-8'}"
                               placeholder="https://www.youtube.com/watch?v=..."
                               data-slot="{$slot.index}">
                    </div>

                    <div class="form-group">
                        <label for="navi_story_label_{$slot.index}">{l s='Libellé affiché' mod='navi'}</label>
                        <input type="text" class="form-control"
                               id="navi_story_label_{$slot.index}"
                               name="navi_story_label_{$slot.index}"
                               value="{$slot.label|escape:'html':'UTF-8'}">
                    </div>

                    <details>
                        <summary>{l s='Prévisualisation personnalisée (optionnel)' mod='navi'}</summary>

                        <div class="form-group">
                            <label for="navi_story_preview_{$slot.index}">{l s='URL de la vidéo de prévisualisation (MP4)' mod='navi'}</label>
                            <input type="text" class="form-control"
                                   id="navi_story_preview_{$slot.index}"
                                   name="navi_story_preview_{$slot.index}"
                                   value="{$slot.preview|escape:'html':'UTF-8'}">
                            <p class="help-block">{l s='Laisser vide pour utiliser la vignette YouTube par défaut.' mod='navi'}</p>
                        </div>

                        <div class="form-group">
                            <label for="navi_story_preview_file_{$slot.index}">{l s='...ou importer un fichier MP4' mod='navi'}</label>
                            <input type="file" class="navi-story-file-input"
                                   id="navi_story_preview_file_{$slot.index}"
                                   name="navi_story_preview_file_{$slot.index}"
                                   accept="video/mp4,.mp4"
                                   data-slot="{$slot.index}">
                            <p class="navi-story-file-info" id="navi-story-file-info-{$slot.index}"></p>
                        </div>
                    </details>
                </div>
            </div>
        {/foreach}
    </div>
</div>

{*
  Deux blocs <script> séparés : le premier laisse Smarty évaluer
  {$var}/{l}, le second est protégé par {literal}...{/literal} — sans ça,
  Smarty interprète un quantificateur regex comme {11} comme une
  expression Smarty valide (littéral numérique) et avale silencieusement
  les accolades, corrompant la regex ([A-Za-z0-9_-]{11} devient
  [A-Za-z0-9_-]11, cassant complètement l'extraction d'identifiant
  YouTube) — trouvé en testant ce gabarit.
*}
<script>
    var NAVI_STORY_MAX_BYTES = {$navi_story_max_bytes|intval};
    var NAVI_STORY_LABEL_CONFIGURED = '{l s="Configurée" mod="navi" js=1}';
    var NAVI_STORY_LABEL_EMPTY = '{l s="Vide" mod="navi" js=1}';
    var NAVI_STORY_LABEL_TOO_LARGE = '{l s="dépasse la taille maximale autorisée" mod="navi" js=1}';
</script>
<script>
{literal}
    (function () {
        var MAX_BYTES = NAVI_STORY_MAX_BYTES;

        function extractYoutubeId(input) {
            input = (input || '').trim();
            if (!input) return '';

            var urlMatch = input.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
            if (urlMatch) return urlMatch[1];

            if (/^[A-Za-z0-9_-]{11}$/.test(input)) return input;

            return '';
        }

        function updatePreview(slot) {
            var input = document.getElementById('navi_story_youtube_' + slot);
            var thumb = document.getElementById('navi-story-thumb-' + slot);
            var placeholder = document.getElementById('navi-story-placeholder-' + slot);
            var badge = document.getElementById('navi-story-badge-' + slot);
            if (!input || !thumb || !placeholder || !badge) return;

            var videoId = extractYoutubeId(input.value);

            if (videoId) {
                thumb.src = 'https://img.youtube.com/vi/' + videoId + '/mqdefault.jpg';
                thumb.style.display = 'block';
                placeholder.style.display = 'none';
                badge.textContent = NAVI_STORY_LABEL_CONFIGURED;
                badge.classList.add('is-filled');
            } else {
                thumb.style.display = 'none';
                placeholder.style.display = 'block';
                badge.textContent = NAVI_STORY_LABEL_EMPTY;
                badge.classList.remove('is-filled');
            }
        }

        function updateFileInfo(slot, input) {
            var info = document.getElementById('navi-story-file-info-' + slot);
            if (!info) return;

            if (!input.files || !input.files.length) {
                info.textContent = '';
                info.classList.remove('is-warning');
                return;
            }

            var file = input.files[0];
            var sizeMb = (file.size / 1048576).toFixed(1);

            if (file.size > MAX_BYTES) {
                info.textContent = file.name + ' — ' + sizeMb + ' Mo — ' + NAVI_STORY_LABEL_TOO_LARGE;
                info.classList.add('is-warning');
            } else {
                info.textContent = file.name + ' — ' + sizeMb + ' Mo';
                info.classList.remove('is-warning');
            }
        }

        var youtubeInputs = document.querySelectorAll('.navi-story-youtube-input');
        for (var i = 0; i < youtubeInputs.length; i++) {
            youtubeInputs[i].addEventListener('input', function (event) {
                updatePreview(event.target.getAttribute('data-slot'));
            });
        }

        var fileInputs = document.querySelectorAll('.navi-story-file-input');
        for (var j = 0; j < fileInputs.length; j++) {
            fileInputs[j].addEventListener('change', function (event) {
                updateFileInfo(event.target.getAttribute('data-slot'), event.target);
            });
        }
    })();
{/literal}
</script>
