{**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
<div class="panel ecom-mbc-panel">
  <div class="panel-heading">
    <i class="icon-check-circle"></i> {l s='Status' d='Modules.Ecommailbajaclick.Admin'}
  </div>

  {assign var=mbc_fallos value=0}
  {foreach from=$mbc_diagnostico item=fila}
    {if !$fila.ok}{assign var=mbc_fallos value=$mbc_fallos+1}{/if}
  {/foreach}

  {if $mbc_fallos > 0}
    <div class="alert alert-warning">
      {l s='Some checks did not pass. Fix them or the one-click unsubscribe will not work.' d='Modules.Ecommailbajaclick.Admin'}
    </div>
  {else}
    <div class="alert alert-success">
      {l s='Everything is ready: your emails carry the unsubscribe headers.' d='Modules.Ecommailbajaclick.Admin'}
    </div>
  {/if}

  {if $mbc_multitienda}
    <div class="alert alert-info">
      {l s='These settings are global: they apply to every shop of the installation.' d='Modules.Ecommailbajaclick.Admin'}
    </div>
  {/if}

  <table class="table ecom-mbc-diag">
    <tbody>
      {foreach from=$mbc_diagnostico item=fila}
        <tr>
          <td class="ecom-mbc-diag-icono">
            {if $fila.ok}
              <span class="badge badge-success"><i class="icon-check"></i></span>
            {else}
              <span class="badge badge-danger"><i class="icon-remove"></i></span>
            {/if}
          </td>
          <td class="ecom-mbc-diag-texto"><strong>{$fila.texto|escape:'html':'UTF-8'}</strong></td>
          <td class="ecom-mbc-diag-detalle">{$fila.detalle|escape:'html':'UTF-8'}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <div class="ecom-mbc-url">
    <label>{l s='Unsubscribe URL' d='Modules.Ecommailbajaclick.Admin'}</label>
    <input type="text" class="form-control" readonly value="{$mbc_url_baja|escape:'html':'UTF-8'}">
    <p class="help-block">
      {l s='This is the address that travels in the header of every email; the code at the end changes for each recipient.' d='Modules.Ecommailbajaclick.Admin'}
    </p>
  </div>

  <form method="post" action="{$mbc_form_url|escape:'html':'UTF-8'}" class="ecom-mbc-botones">
    <a href="{$mbc_url_prueba|escape:'html':'UTF-8'}" target="_blank" rel="noopener" class="btn btn-default">
      <i class="icon-external-link"></i> {l s='Open the unsubscribe URL' d='Modules.Ecommailbajaclick.Admin'}
    </a>
    <button type="submit" name="submitEcomMbcExport" value="1" class="btn btn-default">
      <i class="icon-download"></i> {l s='Download the log as CSV' d='Modules.Ecommailbajaclick.Admin'}
    </button>
    <button type="submit" name="submitEcomMbcRegen" value="1" class="btn btn-default ecom-mbc-confirmar"
            data-mbc-aviso="{l s='The links already sent will stop working. Continue?' d='Modules.Ecommailbajaclick.Admin'}">
      <i class="icon-key"></i> {l s='Generate a new signing key' d='Modules.Ecommailbajaclick.Admin'}
    </button>
    {if $mbc_debug}
      <button type="submit" name="submitEcomMbcClearLog" value="1" class="btn btn-default">
        <i class="icon-trash"></i> {l s='Empty the debug log' d='Modules.Ecommailbajaclick.Admin'}
      </button>
    {/if}
    <p class="help-block">
      {l s='Open the URL to check that your server answers it; download the CSV to keep proof of every unsubscribe.' d='Modules.Ecommailbajaclick.Admin'}
    </p>
  </form>

  <div class="ecom-mbc-ayuda">
    <button type="button" class="btn btn-link ecom-mbc-acordeon" data-mbc-destino="ecom-mbc-ayuda-cuerpo">
      <i class="icon-question-circle"></i> {l s='Help / how it works' d='Modules.Ecommailbajaclick.Admin'}
    </button>
    <div id="ecom-mbc-ayuda-cuerpo" class="ecom-mbc-plegado" style="display:none;">
      <p>
        {l s='Gmail, Yahoo and Outlook demand that commercial email carries a List-Unsubscribe header and, since 2024, a List-Unsubscribe-Post header so their "Unsubscribe" button works without the reader leaving the mailbox (RFC 8058). Without it your messages lose reputation and end up in the spam folder.' d='Modules.Ecommailbajaclick.Admin'}
      </p>
      <ul>
        <li>{l s='The module adds both headers to the emails the shop sends, with an address that is unique per recipient and signed, so nobody can unsubscribe somebody else.' d='Modules.Ecommailbajaclick.Admin'}</li>
        <li>{l s='When the mailbox calls that address, the module unchecks the newsletter box on the customer record and deactivates the subscription of guests.' d='Modules.Ecommailbajaclick.Admin'}</li>
        <li>{l s='Transactional email (order confirmations, invoices, passwords) must not carry the headers: choose which templates do in the Templates tab.' d='Modules.Ecommailbajaclick.Admin'}</li>
        <li>{l s='HTTPS is mandatory. Over plain HTTP the module still adds List-Unsubscribe, but not the one-click part, because the mailboxes reject it.' d='Modules.Ecommailbajaclick.Admin'}</li>
        <li>{l s='Every unsubscribe is written to a table of the module with the date, the IP address and the template it came from, and it can be downloaded as CSV.' d='Modules.Ecommailbajaclick.Admin'}</li>
        <li>{l s='Other modules can react to an unsubscribe through the actionEcomMailbajaclickUnsubscribe hook, for example to propagate it to Mailchimp or Brevo.' d='Modules.Ecommailbajaclick.Admin'}</li>
      </ul>
      <p>
        {l s='To check it end to end: send yourself a test email from a template that carries the headers, open it in Gmail and look for the "Unsubscribe" link next to the sender name.' d='Modules.Ecommailbajaclick.Admin'}
      </p>
    </div>
  </div>

  {if $mbc_debug && $mbc_log}
    <div class="ecom-mbc-ayuda">
      <button type="button" class="btn btn-link ecom-mbc-acordeon" data-mbc-destino="ecom-mbc-log-cuerpo">
        <i class="icon-file-text"></i> {l s='Debug log' d='Modules.Ecommailbajaclick.Admin'}
      </button>
      <div id="ecom-mbc-log-cuerpo" class="ecom-mbc-plegado" style="display:none;">
        <pre class="ecom-mbc-log">{$mbc_log|escape:'html':'UTF-8'}</pre>
      </div>
    </div>
  {/if}
</div>
