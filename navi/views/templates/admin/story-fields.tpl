{*
  Onglet "Navi" (bulles vidéo stories) sur la fiche produit — voir
  Navi::hookDisplayAdminProductsExtra(). Les champs sont lus directement
  depuis $_POST/$_FILES par Navi::handleProductSave() lors de
  l'enregistrement du produit : ce bloc fait partie du même formulaire que
  le reste de la fiche produit, pas un formulaire séparé.
*}
<div class="panel">
    <h3><i class="icon-video-camera"></i> {l s='Stories (Navi)' mod='navi'}</h3>
    <p class="alert alert-info">
        {l s='Jusqu\'à 4 stories par produit. Chaque story affiche une bulle vidéo cliquable sur la fiche produit.' mod='navi'}
        {l s='Vidéo au format MP4 (taille max. %d Mo) ou vidéo YouTube (URL ou identifiant).' sprintf=[$navi_story_max_mb] mod='navi'}
    </p>

    <input type="hidden" name="navi_story_submitted" value="1">

    {foreach from=$navi_story_slots item=slot}
        <div class="form-group navi-story-slot" style="border-top: 1px solid #ddd; padding-top: 12px; margin-top: 12px;">
            <h4>{l s='Story' mod='navi'} #{$slot.index}</h4>

            <div class="form-group">
                <label class="control-label col-lg-3">{l s='URL YouTube ou identifiant' mod='navi'}</label>
                <div class="col-lg-6">
                    <input type="text" class="form-control" name="navi_story_youtube_{$slot.index}" value="{$slot.youtube|escape:'html':'UTF-8'}" placeholder="https://www.youtube.com/watch?v=...">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Libellé affiché' mod='navi'}</label>
                <div class="col-lg-6">
                    <input type="text" class="form-control" name="navi_story_label_{$slot.index}" value="{$slot.label|escape:'html':'UTF-8'}">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">{l s='URL de la vidéo de prévisualisation (MP4)' mod='navi'}</label>
                <div class="col-lg-6">
                    <input type="text" class="form-control" name="navi_story_preview_{$slot.index}" value="{$slot.preview|escape:'html':'UTF-8'}">
                    <p class="help-block">{l s='Laisser vide pour utiliser la vignette YouTube par défaut.' mod='navi'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">{l s='...ou importer un fichier MP4' mod='navi'}</label>
                <div class="col-lg-6">
                    <input type="file" name="navi_story_preview_file_{$slot.index}" accept="video/mp4,.mp4">
                </div>
            </div>
        </div>
    {/foreach}
</div>
