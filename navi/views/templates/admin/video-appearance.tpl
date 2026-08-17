{*
  Curseurs + aperçu en direct pour l'aspect du mockup de téléphone des
  stories (épaisseur du cadre autour de l'écran vidéo = padding de
  .navi-story-phone, taille du mockup) — voir
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
        box-sizing: border-box;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
        transition: width .1s ease, padding .1s ease;
    }
    .navi-preview-screen {
        width: 100%;
        height: 100%;
        border-radius: 24px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000;
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
    <h3><i class="icon-video-camera"></i> {l s='Stories — Aspect du mockup' mod='navi'}</h3>
    <p class="alert alert-info">
        {l s="Épaisseur du cadre autour de l'écran vidéo et taille du mockup de téléphone. Les autres réglages Stories (titre, bordure de la bulle, couleurs) sont dans la section Stories plus bas." mod='navi'}
    </p>

    <form method="post" action="{$navi_current_index|escape:'html':'UTF-8'}&amp;token={$navi_ajax_token|escape:'html':'UTF-8'}">
        <div class="row">
            <div class="col-lg-6">
                <div class="navi-video-appearance-row">
                    <label for="navi_phone_padding_range">
                        {l s="Épaisseur du cadre autour de l'écran" mod='navi'}
                        (<output id="navi_phone_padding_output">{$navi_phone_padding}</output> px)
                    </label>
                    <input type="range" class="form-control" id="navi_phone_padding_range"
                           name="NAVI_STORIES_PHONE_PADDING"
                           min="0" max="{$navi_phone_padding_max}" step="2"
                           value="{$navi_phone_padding}"
                           list="navi_phone_padding_ticks">
                    <datalist id="navi_phone_padding_ticks">
                        {foreach from=$navi_phone_padding_ticks item=tick}
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
                    <div class="navi-preview-phone" id="naviPreviewPhone" style="width: {$navi_phone_width}px; padding: {$navi_phone_padding}px;">
                        <div class="navi-preview-screen" id="naviPreviewScreen">
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
        var paddingRange = document.getElementById('navi_phone_padding_range');
        var widthRange = document.getElementById('navi_phone_width_range');
        var paddingOutput = document.getElementById('navi_phone_padding_output');
        var widthOutput = document.getElementById('navi_phone_width_output');
        var previewPhone = document.getElementById('naviPreviewPhone');

        if (!paddingRange || !widthRange || !previewPhone) return;

        paddingRange.addEventListener('input', function () {
            paddingOutput.textContent = paddingRange.value;
            previewPhone.style.padding = paddingRange.value + 'px';
        });

        widthRange.addEventListener('input', function () {
            widthOutput.textContent = widthRange.value;
            previewPhone.style.width = widthRange.value + 'px';
        });
    })();
</script>
