{**
 * Selector de plantillas de correo.
 *}
<div class='ecom-mbc-selector'>
  <input type='hidden' id='ECOM_MBC_TPL_INCLUDE_SELECTOR' name='ECOM_MBC_TPL_INCLUDE' value='{$mbc_selector_valor|escape:'html':'UTF-8'}'>
  <input type='hidden' name='ECOM_MBC_TPL_EXCLUDE' value=''>
  <input type='hidden' name='ECOM_MBC_MODE' value='1'>

  <div class='alert alert-info'>
    {l s='All templates are listed below. New templates are enabled automatically, except transactional messages such as orders, payments, invoices, shipping, passwords and support.' d='Modules.Ecommailbajaclick.Admin'}
  </div>

  <div class='form-group ecom-mbc-selector-busqueda'>
    <label for='ecom-mbc-buscar-plantilla'>{l s='Search templates' d='Modules.Ecommailbajaclick.Admin'}</label>
    <input type='search' id='ecom-mbc-buscar-plantilla' class='form-control'
           placeholder='{l s='Write a template or module name' d='Modules.Ecommailbajaclick.Admin'}'>
  </div>

  <div class='ecom-mbc-selector-columnas'>
    <section class='ecom-mbc-selector-columna ecom-mbc-selector-no' data-mbc-lista='no'>
      <h3><span class='label label-danger'>NO</span> {l s='Without unsubscribe headers' d='Modules.Ecommailbajaclick.Admin'}</h3>
      <p class='help-block'>{l s='Transactional emails that Gmail must never treat as advertising.' d='Modules.Ecommailbajaclick.Admin'}</p>
      <div class='ecom-mbc-selector-lista' id='ecom-mbc-lista-no'>
        {foreach from=$mbc_selector_plantillas item=fila}
          {if !$fila.activa}
            <button type='button' class='ecom-mbc-plantilla' data-mbc-plantilla='{$fila.nombre|escape:'html':'UTF-8'}'
                    data-mbc-busqueda='{$fila.nombre|escape:'html':'UTF-8'} {$fila.origen|escape:'html':'UTF-8'}'
                    {if $fila.nombre == 'test'}disabled title='{l s='The PrestaShop test email is mandatory.' d='Modules.Ecommailbajaclick.Admin'}'{/if}>
              <strong>{$fila.nombre|escape:'html':'UTF-8'}</strong>
              <small>{$fila.origen|escape:'html':'UTF-8'}</small>
              <span aria-hidden='true'>→</span>
            </button>
          {/if}
        {/foreach}
      </div>
    </section>

    <section class='ecom-mbc-selector-columna ecom-mbc-selector-si' data-mbc-lista='si'>
      <h3><span class='label label-success'>SÍ</span> {l s='With unsubscribe headers' d='Modules.Ecommailbajaclick.Admin'}</h3>
      <p class='help-block'>{l s='Commercial emails, newsletters, promotions and any template enabled by the merchant.' d='Modules.Ecommailbajaclick.Admin'}</p>
      <div class='ecom-mbc-selector-lista' id='ecom-mbc-lista-si'>
        {foreach from=$mbc_selector_plantillas item=fila}
          {if $fila.activa}
            <button type='button' class='ecom-mbc-plantilla' data-mbc-plantilla='{$fila.nombre|escape:'html':'UTF-8'}'
                    data-mbc-busqueda='{$fila.nombre|escape:'html':'UTF-8'} {$fila.origen|escape:'html':'UTF-8'}'>
              <span aria-hidden='true'>←</span>
              <strong>{$fila.nombre|escape:'html':'UTF-8'}</strong>
              <small>{$fila.origen|escape:'html':'UTF-8'}</small>
            </button>
          {/if}
        {/foreach}
      </div>
    </section>
  </div>
</div>
