{*
  Curseurs + aperçu en direct pour l'aspect de la vidéo des stories
  (épaisseur de la bordure autour de l'écran, taille du mockup) — voir
  Navi::getVideoAppearanceBlock(). Formulaire indépendant des fieldsets
  HelperForm plus bas sur la page (submitNaviVideoAppearance).
*}
<style>
    .navi-video-appearance-preview {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 260px;
        padding: 20px;
        background: #f6f6f6;
        border-radius: 4px;
    }
    .navi-preview-phone {
        position: relative;
        aspect-ratio: 9 / 18.5;
        background: #111;
        border-radius: 34px;
        padding: 10px;
        box-sizing: border-box;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
        transition: width .1s ease;
    }
    .navi-preview-screen {
        width: 100%;
        height: 100%;
        border-radius: 24px;
        border-style: solid;
        border-color: {$navi_accent_color|escape:'html':'UTF-8'};
        box-sizing: border-box;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000;
        transition: border-width .1s ease;
    }
    .navi-preview-video {
        color: #fff;
        font-size: .8125rem;
        font-family: sans-serif;
        opacity: .6;
    }
    .navi-video-appearance-row {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 20px;
    }
    .navi-video-appearance-row output {
        font-weight: 700;
    }
</style>

<div class="panel">
    <h3><i class="icon-video-camera"></i> {l s='Stories — Aspect de la vidéo' mod='navi'}</h3>
    <p class="alert alert-info">
        {l s="Épaisseur de la bordure autour de l'écran vidéo et taille du mockup de téléphone. Les autres réglages Stories (titre, bordure de la bulle, couleurs) sont dans la section Stories plus bas." mod='navi'}
    </p>

    <form method="post" action="{$navi_current_index|escape:'html':'UTF-8'}&amp;token={$navi_ajax_token|escape:'html':'UTF-8'}">
        <div class="row">
            <div class="col-lg-6">
                <div class="navi-video-appearance-row">
                    <label for="navi_video_border_range">
                        {l s='Épaisseur de la bordure vidéo' mod='navi'}
                        (<output id="navi_video_border_output">{$navi_video_border_width}</output> px)
                    </label>
                    <input type="range" class="form-control" id="navi_video_border_range"
                           name="NAVI_STORIES_VIDEO_BORDER_WIDTH"
                           min="0" max="{$navi_video_border_max}" step="1"
                           value="{$navi_video_border_width}"
                           list="navi_video_border_ticks">
                    <datalist id="navi_video_border_ticks">
                        {foreach from=$navi_video_border_ticks item=tick}
                            <option value="{$tick}"></option>
                        {/foreach}
                    </datalist>
                </div>

                <div class="navi-video-appearance-row">
                    <label for="navi_phone_width_range">
                        {l s='Taille du mockup de téléphone' mod='navi'}
                        (<output id="navi_phone_width_output">{$navi_phone_width}</output> px)
                    </label>
                    <input type="range" class="form-control" id="navi_phone_width_range"
                           name="NAVI_STORIES_PHONE_WIDTH"
                           min="{$navi_phone_width_min}" max="{$navi_phone_width_max}" step="10"
                           value="{$navi_phone_width}"
                           list="navi_phone_width_ticks">
                    <datalist id="navi_phone_width_ticks">
                        {foreach from=$navi_phone_width_ticks item=tick}
                            <option value="{$tick}"></option>
                        {/foreach}
                    </datalist>
                </div>

                <button type="submit" name="submitNaviVideoAppearance" class="btn btn-primary">
                    {l s='Enregistrer' mod='navi'}
                </button>
            </div>

            <div class="col-lg-6">
                <div class="navi-video-appearance-preview">
                    <div class="navi-preview-phone" id="naviPreviewPhone" style="width: {$navi_phone_width}px;">
                        <div class="navi-preview-screen" id="naviPreviewScreen" style="border-width: {$navi_video_border_width}px;">
                            <span class="navi-preview-video">{l s='Vidéo' mod='navi'}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        var borderRange = document.getElementById('navi_video_border_range');
        var widthRange = document.getElementById('navi_phone_width_range');
        var borderOutput = document.getElementById('navi_video_border_output');
        var widthOutput = document.getElementById('navi_phone_width_output');
        var previewPhone = document.getElementById('naviPreviewPhone');
        var previewScreen = document.getElementById('naviPreviewScreen');

        if (!borderRange || !widthRange || !previewPhone || !previewScreen) return;

        borderRange.addEventListener('input', function () {
            borderOutput.textContent = borderRange.value;
            previewScreen.style.borderWidth = borderRange.value + 'px';
        });

        widthRange.addEventListener('input', function () {
            widthOutput.textContent = widthRange.value;
            previewPhone.style.width = widthRange.value + 'px';
        });
    })();
</script>
