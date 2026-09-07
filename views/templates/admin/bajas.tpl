{**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
<div class="panel ecom-mbc-panel">
  <div class="panel-heading">
    <i class="icon-user-times"></i> {l s='Unsubscribes' d='Modules.Ecommailbajaclick.Admin'}
    <span class="badge">{$mbc_total|escape:'html':'UTF-8'}</span>
  </div>

  <form method="get" action="{$mbc_form_url|escape:'html':'UTF-8'}" class="form-inline ecom-mbc-buscador">
    <input type="text" name="mbc_buscar" class="form-control" value="{$mbc_buscar|escape:'html':'UTF-8'}"
           placeholder="{l s='Search by email address' d='Modules.Ecommailbajaclick.Admin'}">
    <button type="submit" class="btn btn-default"><i class="icon-search"></i> {l s='Search' d='Modules.Ecommailbajaclick.Admin'}</button>
  </form>

  {if $mbc_bajas}
    <table class="table ecom-mbc-tabla">
      <thead>
        <tr>
          <th>{l s='Date' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th>{l s='Email address' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th>{l s='Way' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th>{l s='Template' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th>{l s='IP address' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th>{l s='Shop' d='Modules.Ecommailbajaclick.Admin'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$mbc_bajas item=fila}
          <tr>
            <td>{$fila.date_add|escape:'html':'UTF-8'}</td>
            <td>{$fila.email|escape:'html':'UTF-8'}</td>
            <td>{$fila.metodo|escape:'html':'UTF-8'}</td>
            <td>{$fila.origen|escape:'html':'UTF-8'}</td>
            <td>{$fila.ip|escape:'html':'UTF-8'}</td>
            <td>{$fila.id_shop|escape:'html':'UTF-8'}</td>
            <td class="text-right">
              <form method="post" action="{$mbc_form_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="ecom_mbc_email" value="{$fila.email|escape:'html':'UTF-8'}">
                <button type="submit" name="submitEcomMbcReactivar" value="1"
                        class="btn btn-default btn-xs ecom-mbc-confirmar"
                        data-mbc-aviso="{l s='Subscribe this address again?' d='Modules.Ecommailbajaclick.Admin'}">
                  <i class="icon-undo"></i> {l s='Subscribe again' d='Modules.Ecommailbajaclick.Admin'}
                </button>
              </form>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    {if $mbc_paginas > 1}
      <ul class="pagination ecom-mbc-paginacion">
        {section name=p start=1 loop=$mbc_paginas+1 step=1}
          <li{if $smarty.section.p.index == $mbc_pagina} class="active"{/if}>
            <a href="{$mbc_form_url|escape:'html':'UTF-8'}&amp;mbc_pagina={$smarty.section.p.index|escape:'html':'UTF-8'}{if $mbc_buscar}&amp;mbc_buscar={$mbc_buscar|escape:'url'}{/if}">
              {$smarty.section.p.index|escape:'html':'UTF-8'}
            </a>
          </li>
        {/section}
      </ul>
    {/if}
  {else}
    <p class="text-muted ecom-mbc-vacio">
      {l s='Nobody has unsubscribed yet.' d='Modules.Ecommailbajaclick.Admin'}
    </p>
  {/if}
</div>
