<?php

use WHMCS\Database\Capsule;
use WHMCS\User\Client as WHMCSClient;
use WHMCS\View\Menu\Item as MenuItem;

require_once __DIR__ . '/helpers.php';


/**
 * @file Hooks
 * @created 03.08.2021 г.
 *
 * @author Wohaho
 * @email support@w-store.org
 * @discord Wohaho#5542
 *
 */

add_hook('InvoicePaid', 1, function ($vars) {

    if (InvbgSettings::$GENERATE_ON_PAID == 'on') {

        $invoiceId = $vars['invoiceid'];
        $invoiceData = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        $itemsData = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get()->toArray();
        $clientData = WHMCSClient::find($invoiceData->userid);
        [$dds, $mol, $bulstat, $is_person, $is_dds, $to_name] = getClientCustomFileds($clientData);

        if (InvbgSettings::$GENERATE_FOR_COMPANIES == 'on' && !$is_person) {
            $invDocument = createInvBgDocument($invoiceData, $clientData, $itemsData, $is_person, $is_dds, $dds, $to_name, $mol, $bulstat);
            sendInvBgDocument($invDocument['id'], $invoiceId, $clientData);
        }
        if (InvbgSettings::$GENERATE_FOR_COMPANIES != 'on') {
            $invDocument = createInvBgDocument($invoiceData, $clientData, $itemsData, $is_person, $is_dds, $dds, $to_name, $mol, $bulstat);
            sendInvBgDocument($invDocument['id'], $invoiceId, $clientData);
        }

    }
});

add_hook('ClientAreaPageInvoices', 1, function ($vars) {
    $invoices = $vars['invoices'];
    $modifiedInvoices = [];

    $icon = 'far fa-paper-plane';
    if (InvbgSettings::$RECEIVING_TYPE == 'download') {
        $icon = 'fa fa-file-pdf';
    }
    foreach ($invoices as $invoice) {
        if ($invoice['rawstatus'] == 'paid') {
            $invoice['buttonInvBg'] = '<a href="/invoice.php?id=' . $invoice['id'] . '"><i class="' . $icon . ' fa-2x"></i></a>';
        } else {
            $invoice['buttonInvBg'] = '';
        }
        $modifiedInvoices[] = $invoice;
    }
    return ['invoices' => $modifiedInvoices];
});



