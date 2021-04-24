{if isset($newOrderUrl)}
  <h3>{$i18n.orders} <a class="button-new_order button" href="{$newOrderUrl}">{$i18n.addOrder}</a></h3>
{/if}
<table class="selector row-highlight">
  <thead class="sticky">
    <tr>
      <th scope="col">{$i18n.orderNumber}</th>
      <th scope="col">{$i18n.date}</th>
      <th scope="col">{$i18n.billingName}</th>
      <th scope="col">{$i18n.shippingName}</th>
      <th scope="col">{$i18n.itemCount}</th>
      <th scope="col">{$i18n.amount}</th>
      <th scope="col">{$i18n.actions}</th>
    </tr>
  </thead>
  <tbody>
  {if $orders}
    {foreach from=$orders item=row}
      {assign var=id value=$row.order_number}
      <tr class="{$row.order_status}">
        <td>{$row.order_number}</td>
        <td>{$row.order_date}</td>
        <td>{$row.order_billing_name}</td>
        <td>{$row.order_shipping_name}</td>
        <td>{$row.item_count}</td>
        <td>{$row.order_total|crmMoney}</td>
        <td><a href="{$row.order_link}">{$i18n.edit}</a></td>
      </tr>
    {/foreach}
  {/if}
  {literal}
    <script type="text/javascript">console.log('Loaded')</script>
  {/literal}
  </tbody>
</table>
