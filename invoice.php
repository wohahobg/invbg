<?php
//configorations
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$http_referer = $_SERVER['HTTP_REFERER'] ?? '/clientarea.php?action=invoices';
$host = $_SERVER['HTTP_HOST'];

if (!str_contains($http_referer, $host)) {
    $http_referer = '/clientarea.php?action=invoices';
}
$http_referer = str_replace(['https://', 'http://', $host], '', $http_referer);
define('REDIRECT_TO', $http_referer);

use WHMCS\Database\Capsule;
use WHMCS\User\Client as WHMCSClient;

require __DIR__ . '/init.php';

$currentUser = new \WHMCS\Authentication\CurrentUser;

$userData = $currentUser->user();
if (!$userData) {
    addInvBGLog("[INV.BG MODULE] Warning: User data not found. Redirecting to " . REDIRECT_TO);
    header('Location: ' . REDIRECT_TO);
    exit(404);
}
// get client's custom fields
$clientData = WHMCSClient::find($userData->id);
$clientFields = $clientData->customFieldValues;
$invoiceId = $_GET['id'] ?? 0;

// 1. Extract WHMCS Data
$invoiceData = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
if (!$invoiceData || $invoiceData->userid != $clientData->id) {
    addInvBGLog("Неуспешно извличане на данни за фактура с WHMCS ID " . $invoiceId . " и клиент с имейл " . $clientData->email . ".");
    header('Location: ' . REDIRECT_TO . '&error=invoiceDataNotFound');
    exit(404);
}

if ($invoiceData->status != 'Paid') {
    // die('Location: ' . REDIRECT_TO . '&error=statusNotPaid');
    addInvBGLog("Потреибтел с имейл " . $clientData->email . " се опите да генерира фактура със статус " . $invoiceData->status);
    header('Location: ' . REDIRECT_TO . '&error=statusNotPaid');
    exit(404);
}
$itemsData = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get()->toArray();
if (!$itemsData) {
    addInvBGLog("Неуспешно извличане на данни за предмети от фактура с WHMCS ID " . $invoiceId . " и клиент с имейл " . $clientData->email . ".");
    header('Location: ' . REDIRECT_TO . '&error=itemsDataNotFound&id=' . $invoiceId);
    exit(404);
}


//get the inv document from the database.
//if there is matches we store the new file and output it for the user.
$invDocument = Capsule::table('invbg_invoices')->where('whmcs_id', $invoiceId)->first();
if ($invDocument) {
    $response = handleDocumentAction($invDocument->invbg_id, $invoiceId, $invoiceData, $clientData);
    if ($response === false) {
        Capsule::table('invbg_invoices')->where('whmcs_id', $invoiceId)->delete();
        [$dds, $mol, $bulstat, $is_person, $is_dds, $to_name] = getClientCustomFileds($clientData);
        $invDocument = createInvBgDocument($invoiceData, $clientData, $itemsData, $is_person, $is_dds, $dds, $to_name, $mol, $bulstat);
        if ($invDocument === false) {
            die('Something went wrong! Please contact the website admin!');
        }
        $response = handleDocumentAction($invDocument['id'], $invoiceId, $invoiceData, $clientData);
        if ($response === false) {
            header('Location: ' . REDIRECT_TO . '&error=errorWhileProgressingDownload&id=' . $invoiceId);
            exit(404);
        }
    }
    header('Location: ' . REDIRECT_TO);
    exit(404);

}

[$dds, $mol, $bulstat, $is_person, $is_dds, $to_name] = getClientCustomFileds($clientData);
$invDocument = createInvBgDocument($invoiceData, $clientData, $itemsData, $is_person, $is_dds, $dds, $to_name, $mol, $bulstat);
if ($invDocument === false || is_string($invDocument)) {
    header('Location: ' . REDIRECT_TO . '&error=' . $invDocument . '&id=' . $invoiceId);
    exit(404);
}
$response = handleDocumentAction($invDocument['id'], $invoiceId, $invoiceData, $clientData);
if ($response === false) {
    header('Location: ' . REDIRECT_TO . '&error=errorWhileProgressingDownload&id=' . $invoiceId);
    exit(404);
}
header('Location: ' . REDIRECT_TO);
exit(200);