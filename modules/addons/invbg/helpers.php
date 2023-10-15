<?php

use GuzzleHttp\Client as GuzzleHttp;
use WHMCS\Database\Capsule as DBHelper;

class InvbgSettings
{

    public static $API_TOKEN;
    public static $INCLUDE;
    public static $RECEIVING_TYPE;
    public static $EMAIL_DELIVERY;
    public static $GENERATE_ON_PAID;
    public static $GENERATE_FOR_COMPANIES;
    public static $BULSTAT_FIELD = '';
    public static $MOL_FIELD = '';
    public static $DDS_FIELD = '';

    public static function loadSettings()
    {
        $settings = DBHelper::table('tbladdonmodules')->where('module', 'invbg')->get();
        foreach ($settings as $setting) {
            $property = strtoupper($setting->setting);
            if (property_exists(self::class, $property)) {
                self::${$property} = $setting->value;
            }
        }
    }
}

InvbgSettings::loadSettings();

function addInvBGLog($message)
{
    logActivity("[INV.BG MODULE] " . $message);
}

function getClient()
{
    $token = "Bearer " . InvbgSettings::$API_TOKEN;
    return new GuzzleHttp([
        'base_uri' => 'https://api.inv.bg/v3/',
        'timeout' => 30,
        'headers' => [
            'Authorization' => $token,
            "Content-Type" => "application/json"
        ]
    ]);
}

function handleDocumentAction($documentId, $invoiceId, $invoiceData, $clientData)
{
    $type = InvbgSettings::$RECEIVING_TYPE;
    if ($type == 'email') {
        return sendInvBgDocument($documentId, $invoiceId, $clientData);
    }
    if ($type == 'download') {
        return downloadInvBgDocument($documentId, $invoiceId, $clientData);
    }
}

function paymentMethods()
{
    return [
        "paypal" => "moneytransfer",
        "paypalcheckout" => "moneytransfer",
        "stripe" => "card",
        "banktransfer" => "bank",
        "epaybg" => "bank",
        "easypay" => "bank",
        "bp" => "card"
    ];
}

function getPaymentMethod($method)
{
    $paymentMethods = paymentMethods();
    return $paymentMethods[$method] ?? $method;
}

function getInvBGDefaultBank()
{
    $url = "bank/accounts?order_by=default";
    $request = getClient()->get($url)->getBody()->getContents();
    $banks = json_decode($request, true);
    $accounts = $banks['accounts'] ?? [];
    if (!$accounts) return false;
    foreach ($accounts as $account) {
        if ($account['default'] == 1) {
            return $account['id'];
        }
    }
}

function createInvBgDocument($invoiceData, $clientData, $itemsData, $is_person, $is_dds, $dds, $to_name, $mol, $bulstat)
{
    $getDefaultBank = getInvBGDefaultBank();
    if (!$getDefaultBank) {
        addInvBGLog('Не беше намерена банка по-подразбиране в INV.BG. Фактурата не беше генерирана. Номер на в WHMCS фактура ' . $invoiceData->id);
        return false;
    }
    $rate = 0;
    if ($clientData->currencyCode == 'EUR') {
        $getRate = DBHelper::table('tblcurrencies')->where('code', 'BGN')->first('rate');;
        $rate = $getRate->rate;
    }
    $data = array(
        'type' => 'dan',
        'is_oss' => false,
        'to_name' => $to_name,
        'to_country' => $clientData->country,
        'to_town' => $clientData->city,
        'to_address' => $clientData->address1 . ', ' . $clientData->address2,

        'to_bulstat' => $bulstat,
        'to_is_reg_vat' => $is_dds,
        'to_vat_number' => $dds,
        'to_mol' => $mol,
        'is_to_person' => $is_person,
        'to_egn' => 9999999999,

        'recipient' => $clientData->firstname . ' ' . $clientData->lastname,
        'payment_currency' => $clientData->currencyCode,
        'currency_rate' => $rate, // you may need to adjust if WHMCS provides this
        //'date_rate' => $invoiceData->date,
        'payment_method' => getPaymentMethod($invoiceData->paymentmethod),
//        'notes' => 'WHMCS Invoice Id ' . $invoiceData->id,
//    'reduction' => [
//        'type' => 'currency',
//        'value' => 0
//    ],
//    'date_create' => $invoiceData->date,
//    'date_event' => $invoiceData->duedate,
//    'date_mature' => $invoiceData->duedate,
        'status' => 'paid',
//    'is_draft' => 0,
//    'is_annulled' => 0,
        'vat' => [
            'percent' => 20,
            'reason_without' => null // adjust as needed
        ],
//    'related_invoice' => [
//        'id' => null,
//        'number' => 0,
//        'date' => $invoiceData->date
//    ],
        'template' => 3,
        'bank_accounts' => [$getDefaultBank],
        'items_with_vat' => 1,
//    'payment_amount' => $invoiceData->total,
//    'payment_amount_base' => $invoiceData->tax,
//    'payment_amount_vat' => $invoiceData->tax,
//    'payment_amount_reduction' => '',
//    'payment_amount_total' => '',
        'items' => array_map(function ($item) {
            return [
                'name' => $item->description,
                'price' => $item->amount,
                //'price_total' => $item->price_total,
                'quantity_unit' => 'бр.',
                'quantity' => 1,  // adjust if WHMCS provides this
            ];
        }, $itemsData),
        //'number_set' => $invoiceId,
    );
    try {
        $url = "invoices";
        $request = getClient()->post($url, [
            'body' => json_encode($data)
        ])->getBody()->getContents();
        $data = json_decode($request, true);
        DBHelper::table('invbg_invoices')->insert([
            'whmcs_id' => $invoiceData->id,
            'invbg_id' => $data['id'],
            'date' => date('Y-m-d')
        ]);
        getClient()->post('comments',[
            'body' => json_encode( [
                'type' => 'invoice',
                'entity_id' => $data['id'],
                'comment' => 'WHMCS ID: ' . $invoiceData->id
            ])
        ]);
        addInvBGLog("Генерирана фактора в INV.BG ID " . $data['id'] . " за WHMCS ID: " . $invoiceData->id . ".");
        return ['id' => $data['id']];
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $status = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        if (isset($body['error']['description'])) {
            return $body['error']['description'] . '- Status:' . $status;
        }
        $error = $body['error'] ?? 'Error!';
        addInvBGLog("Генерирането на фактурата за INV.BG не беше успешно, WHMCS ID: " . $invoiceData->id . " Грешка:" . $error . ".");
        return false;
    }
}

function sendInvBgDocument($id, $invoiceId, $clientData)
{
    $lang = 'en';
    if ($clientData->language == 'bulgarian') {
        $lang = 'bg';
    }
    try {
        $url = "invoices/" . $id . "/emails";
        getClient()->post($url, [
            'body' => json_encode([
                'email' => $clientData->email,
                'include' => InvbgSettings::$INCLUDE,
                'delivery' => InvbgSettings::$EMAIL_DELIVERY,
                'inv_language' => $lang
            ])
        ]);
        addInvBGLog("Изпратен имейл за INV.BG ID " . $id . ", WHMCS ID: " . $invoiceId . " до клиент: " . $clientData->email . ".");
        return true;
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $status = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        echo '<pre>';
        print_r($body);
        echo '</pre>';
        $error = $body['error'] ?? 'Error!';
        if (isset($body['error']['description'])) {
            $error = $body['error']['description'] . '- Status:' . $status;
        }
        addInvBGLog("Неуспешно изпращане на имейл за INV.BG ID " . $id . ", WHMCS ID: " . $invoiceId . " до клиент: " . $clientData->email . ". Грешка: " . $error . ".");
        return false;
    }
}

/**
 *
 * @param $documentId
 * @param $invoiceId
 * @param $clientData
 * @return bool|void
 * @throws \GuzzleHttp\Exception\GuzzleException
 */
function downloadInvBGDocument($documentId, $invoiceId, $clientData)
{
    $dir = __DIR__ . '/../invbg';
    if (!is_dir($dir)) {
        if (!mkdir($dir)) {
            addInvBGLog("Грешка: Не може да бъде създадена директория 'invbg");
            die('Could not create directory invbg outside public_html!');
        }
    }
    $lang = 'en';
    if ($clientData->language == 'bulgarian') {
        $lang = 'bg';
    }
    try {
        $request = getClient()->get('invoices/' . $documentId . '/pdf?type=' . InvbgSettings::$INCLUDE, [
            'headers' => [
                'Accept-Language' => $lang
            ]
        ])->getBody()->getContents();
        $filepath = $dir . '/' . $documentId . '-' . $invoiceId . '.pdf';
        file_put_contents($filepath, $request);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        flush(); // Flush system output buffer
        readfile($filepath);
        unlink($filepath);
        return true;
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $status = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        $error = $body['error'] ?? 'Error!';
        if (isset($body['error']['description'])) {
            $error = $body['error']['description'] . '- Status:' . $status;
        }
        $message = sprintf(
            "Неуспешно изтегляне на документ с INV.BG id %s и WHMCS id %s за клиент с имейл: %s. Грешка: %s",
            $documentId,
            $invoiceId,
            $clientData->email,
            $error
        );
        addInvBGLog($message);
        return false;
    }
}

function getClientCustomFileds($clientData)
{
    $dds = '';
    $mol = '';
    $bulstat = '';
    $progress = true;
    if (InvbgSettings::$DDS_FIELD == '' || InvbgSettings::$MOL_FIELD == '' || InvbgSettings::$BULSTAT_FIELD == '') {
        addInvBGLog('Не бяха намерени валидни персонализирани полета. Моля проверете настройките на Модула INV.BG');
        $progress = false;
    }
    $CUSTOM_FILEDS = [
        InvbgSettings::$DDS_FIELD => 'dds_field',
        InvbgSettings::$MOL_FIELD => 'mol_field',
        InvbgSettings::$BULSTAT_FIELD => 'bulstat_field',
    ];
    if ($progress && $clientData->customFieldValues) {
        foreach ($clientData->customFieldValues as $field) {
            if (isset($CUSTOM_FILEDS[$field->customField->fieldname])
                && $CUSTOM_FILEDS[$field->customField->fieldname] = 'dds_field') {
                $dds = $field->value;
            }
            if (isset($CUSTOM_FILEDS[$field->customField->fieldname])
                && $CUSTOM_FILEDS[$field->customField->fieldname] = 'mol_field') {
                $mol = $field->value;
            }
            if (isset($CUSTOM_FILEDS[$field->customField->fieldname])
                && $CUSTOM_FILEDS[$field->customField->fieldname] = 'bulstat_field') {
                $bulstat = $field->value;
            }
        }
    }

    $is_person = true;
    $is_dds = false;
    if ($dds != '') {
        $is_dds = true;
    }
    $to_name = $clientData->firstname . ' ' . $clientData->lastname;
    if ($mol != '' && $bulstat != '') {
        if ($clientData->companyname == '') {
            addInvBGLog("Липсва име на компанията за клиент с имейл " . $clientData->email . ". Фактурата е генерирана за фиризическо лице.");
        } else {
            $to_name = $clientData->companyname;
            $is_person = false;
        }
    }
    return [$dds, $mol, $bulstat, $is_person, $is_dds, $to_name];
}
