{*
  Bulles "stories" rendues directement par Navi (voir
  Navi::hookDisplayAfterProductThumbs()) — plus de dépendance à un module
  tiers pour la gestion des stories. Sélecteur consommé par
  views/js/stories.js : .navi-story-bubble[data-video-id].
*}
<div class="navi-story-row">
    {foreach from=$navi_stories item=story}
        <button type="button" class="navi-story-bubble"
                data-video-id="{$story.youtube|escape:'html':'UTF-8'}"
                data-product-id="{$navi_story_product_id|intval}"
                data-label="{$story.label|escape:'html':'UTF-8'}">
            <span class="navi-story-bubble-circle">
                {if $story.preview_is_video}
                    <video class="navi-story-bubble-preview" muted loop autoplay playsinline preload="metadata">
                        <source src="{$story.preview|escape:'html':'UTF-8'}" type="video/mp4">
                    </video>
                {else}
                    <img class="navi-story-bubble-preview" src="{$story.preview|escape:'html':'UTF-8'}" alt="" loading="lazy">
                {/if}
                <span class="navi-story-bubble-play" aria-hidden="true"></span>
            </span>
            {if $story.label}<span class="navi-story-bubble-label">{$story.label|escape:'html':'UTF-8'}</span>{/if}
        </button>
    {/foreach}
</div>
