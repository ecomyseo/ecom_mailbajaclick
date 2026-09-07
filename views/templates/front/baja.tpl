{**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
{extends file='page.tpl'}

{block name='page_title'}
  {l s='Unsubscribe' d='Modules.Ecommailbajaclick.Shop'}
{/block}

{block name='page_content'}
  <div class="ecom-mbc">

    {if $mbc.error}
      <div class="alert alert-danger" role="alert">
        {$mbc.error|escape:'html':'UTF-8'}
      </div>
    {/if}

    {if $mbc.hecho}
      <div class="alert alert-success" role="alert">
        {if $mbc.ya_estaba}
          <p>{l s='You were already unsubscribed.' d='Modules.Ecommailbajaclick.Shop'}</p>
        {else}
          <p><strong>{l s='Done. You will not receive any more commercial emails from us.' d='Modules.Ecommailbajaclick.Shop'}</strong></p>
        {/if}
        {if $mbc.email}
          <p class="ecom-mbc-email">{$mbc.email|escape:'html':'UTF-8'}</p>
        {/if}
      </div>
      <p class="ecom-mbc-nota">
        {l s='Order confirmations and other messages you need in order to buy will still reach you.' d='Modules.Ecommailbajaclick.Shop'}
      </p>
    {/if}

    {if $mbc.formulario && !$mbc.hecho}
      <p>{l s='Write the email address you want to remove from our mailing list.' d='Modules.Ecommailbajaclick.Shop'}</p>

      <form method="post" action="{$mbc_accion|escape:'html':'UTF-8'}" class="ecom-mbc-form">
        <div class="form-group">
          <label for="ecom_mbc_correo">{l s='Email address' d='Modules.Ecommailbajaclick.Shop'}</label>
          <input type="email" class="form-control" id="ecom_mbc_correo" name="ecom_mbc_correo" required value="">
        </div>

        <div class="ecom-mbc-trampa" aria-hidden="true">
          <label for="ecom_mbc_web">{l s='Leave this field empty' d='Modules.Ecommailbajaclick.Shop'}</label>
          <input type="text" id="ecom_mbc_web" name="ecom_mbc_web" tabindex="-1" autocomplete="off" value="">
        </div>

        <button type="submit" name="ecom_mbc_manual" value="1" class="btn btn-primary">
          {l s='Unsubscribe' d='Modules.Ecommailbajaclick.Shop'}
        </button>
      </form>
    {/if}

  </div>
{/block}
