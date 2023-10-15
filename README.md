# invbg# Адон за INV.BG в WHMCS

Този адон позволява автоматичното създаване на фактури в системата на INV.BG, директно от WHMCS.

## Създател:
**Александар a.k.a Wohaho**  
📧 Email: wohahobg@gmail.com

Ако имате въпроси или нужда от помощ моля свържете се с мен.

## Как функционира аддонът?

1. Автоматично създава фактури в системата на INV.BG веднага след като съответната фактура в WHMCS е маркирана като "Платена".
2. Позволява ръчното създаване на фактура при натискане на бутон за изтегляне в клиентската зона на WHMCS.
3. Ако клиентът е въвел данни за Юридическо лице (име на компанията, МОЛ и ЕИК/Булстат), фактурата ще бъде издадена за юридическо лице.
4. Този модул работи единствено с BGN, EUR валути.
## Инструкции за инсталация:

1. Изтеглете съдържанието на адона.
2. Качете го в директорията на вашия WHMCS.
3. Отворете контролния панел на WHMCS и навигирайте до `System Settings > Addon Modules`.
4. Активирайте модула като натиснете бутон `Active`.
5. Натиснете `Configure` и въведете вашите настройки:
    - API ключ от INV.BG
    - Конфигурирайте модула според вашите нужди.

### Конфигурация на фирмените полета:

За оптималната работа на аддона, е важно да конфигурирате следните полета във вашата WHMCS система:

1. **Поле ЕИК/Булстат**: Конфигурирайте това поле така, че клиентите ви да могат да въведат своя ЕИК или Булстат номер.
2. **Поле МОЛ**: Конфигурирайте това поле за въвеждане на името на МОЛ - лицето, упълномощено да представлява фирмата.
3. **Поле ДДС №**: Тук клиентите трябва да въведат своя ДДС номер. Ако клиентът няма въведен ДДС номер, фактурата ще отбелязва, че фирмата не е регистрирана по ДДС.


### Персонализация на клиентския интерфейс:

За добавяне на бутон за изтегляне на фактура към страницата с фактури, редактирайте файла `път/към/вашият/дизайн/clientareainvoices.tpl`.
#### Примерен файл
```html
{include file="$template/includes/tablelist.tpl" tableName="InvoicesList" filterColumn="4"}
<script>
    jQuery(document).ready(function () {
        var table = jQuery('#tableInvoicesList').show().DataTable();

        {if $orderby == 'default'}
        table.order([4, 'desc'], [2, 'asc']);
        {elseif $orderby == 'invoicenum'}
        table.order(0, '{$sort}');
        {elseif $orderby == 'date'}
        table.order(1, '{$sort}');
        {elseif $orderby == 'duedate'}
        table.order(2, '{$sort}');
        {elseif $orderby == 'total'}
        table.order(3, '{$sort}');
        {elseif $orderby == 'status'}
        table.order(4, '{$sort}');
        {/if}
        table.draw();
        jQuery('#tableLoading').hide();
    });
</script>
<div class="table-container clearfix">
    <table id="tableInvoicesList" class="table table-list w-hidden">
        <thead>
        <tr>
            <th>{lang key='invoicestitle'}</th>
            <th>{lang key='invoicesdatecreated'}</th>
            <th>{lang key='invoicesdatedue'}</th>
            <th>{lang key='invoicestotal'}</th>
            <th>{lang key='invoicesstatus'}</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        {foreach $invoices as $invoice}
            <tr onclick="clickableSafeRedirect(event, 'viewinvoice.php?id={$invoice.id}', false)">
                <td>{$invoice.invoicenum}</td>
                <td><span class="w-hidden">{$invoice.normalisedDateCreated}</span>{$invoice.datecreated}</td>
                <td><span class="w-hidden">{$invoice.normalisedDateDue}</span>{$invoice.datedue}</td>
                <td data-order="{$invoice.totalnum}">{$invoice.total}</td>
                <td>
                    <span class="label status status-{$invoice.statusClass}">{$invoice.status}</span>
                </td>
                <td>{$invoice.buttonInvBg}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    <div class="text-center" id="tableLoading">
        <p><i class="fas fa-spinner fa-spin"></i> {lang key='loading'}</p>
    </div>
</div>
```



## Допълнителна информация

Моля уверете се че имате добавени банкови детайли в INV.BG

