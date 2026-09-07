{**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
<div class="ecom-mbc-detectadas">
  <button type="button" class="btn btn-link ecom-mbc-acordeon" data-mbc-destino="ecom-mbc-detectadas-cuerpo">
    <i class="icon-list"></i> {l s='Templates found in this shop' d='Modules.Ecommailbajaclick.Admin'}
  </button>

  <div id="ecom-mbc-detectadas-cuerpo" class="ecom-mbc-plegado" style="display:none;">
    <p class="help-block">
      {l s='Click a name to add it to the list above.' d='Modules.Ecommailbajaclick.Admin'}
    </p>

    {if $mbc_plantillas.core}
      <p><strong>{l s='PrestaShop templates' d='Modules.Ecommailbajaclick.Admin'}</strong></p>
      <p class="ecom-mbc-fichas">
        {foreach from=$mbc_plantillas.core item=nombre}
          <button type="button" class="btn btn-default btn-xs ecom-mbc-ficha"
                  data-mbc-plantilla="{$nombre|escape:'html':'UTF-8'}">{$nombre|escape:'html':'UTF-8'}</button>
        {/foreach}
      </p>
    {/if}

    {if $mbc_plantillas.modulos}
      <p><strong>{l s='Module templates' d='Modules.Ecommailbajaclick.Admin'}</strong></p>
      <p class="ecom-mbc-fichas">
        {foreach from=$mbc_plantillas.modulos item=fila}
          <button type="button" class="btn btn-default btn-xs ecom-mbc-ficha"
                  data-mbc-plantilla="{$fila.plantilla|escape:'html':'UTF-8'}"
                  title="{$fila.modulo|escape:'html':'UTF-8'}">{$fila.plantilla|escape:'html':'UTF-8'}
            <span class="text-muted">({$fila.modulo|escape:'html':'UTF-8'})</span></button>
        {/foreach}
      </p>
    {/if}

    {if !$mbc_plantillas.core && !$mbc_plantillas.modulos}
      <p class="text-muted">{l s='No email template was found.' d='Modules.Ecommailbajaclick.Admin'}</p>
    {/if}
  </div>
</div>
