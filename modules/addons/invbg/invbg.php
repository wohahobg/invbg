<?php

use WHMCS\Database\Capsule;

function invbg_config()
{
    $results = Capsule::table('tblcustomfields')->where('type', 'client')->get('fieldname')->toArray();
    $customFileds = [];
    $noCustomFileds = '';
    if ($results) {
        foreach ($results as $result) {
            $customFileds[$result->fieldname] = $result->fieldname;
        }
    }
    if (!$customFileds) {
        $noCustomFileds = '<br><br> <h2 class="text-danger" style="margin: 0;
            padding: 0;"><b>Вие нямате създадени никакви персонализирани полета за клиент зоната!<br>Можете да създадете такива от тук <a href="https://control.qgs-hosting.com/admin/configcustomfields.php">System Settings > Custom Fields</a></b></h2>';
    }
    return [
        'name' => 'Фактури INV.BG',
        'description' => 'Модул за генериране/изпращане на фактури чрез INV.BG',
        'version' => '1.0',
        'author' => 'Wohaho',
        'fields' => [
            'importen_message' => [
                'Description' => '
                            <div class="alert alert-info">
                                За да може да се генерират фактури в INV.BG за клиенти с фирми те трябва да имат задъжлително попълнени Име на компания,МОЛ и ЕИК/Булстат<br>
                                 За да създадете персонализирани полета за клиент моля вижте тази статия <a href="https://docs.whmcs.com/Custom_Fields#Client_Custom_Fields">WHMCS Client Custom Fields</a>
                                 ' . $noCustomFileds . '
                            </div>',
            ],
            'api_token' => [
                'FriendlyName' => 'API ключ',
                'Type' => 'text',
                'Size' => '255',
                'Description' => 'Въведете вашия API ключ тук',
                'Default' => '',
            ],
            'bulstat_field' => [
                'FriendlyName' => 'Поле ЕИК/Булстат',
                'Type' => 'dropdown',
                'Options' => $customFileds,  // Заменете с реалните опции
                'Description' => 'Изберете името на персонализираното поле, където стойността за ЕИК/Булстат се съхранява за клиента.',
                'Default' => 'ЕИК/Булстат',
            ],
            'mol_field' => [
                'FriendlyName' => 'Поле МОЛ',
                'Type' => 'dropdown',
                'Options' => $customFileds,  // Заменете с реалните опции
                'Description' => 'Изберете името на персонализираното поле, където стойността за МОЛ (Управител или Оторизиран представител) се съхранява за клиента.',
                'Default' => 'МОЛ',
            ],
            'dds_field' => [
                'FriendlyName' => 'Поле ДДС №',
                'Type' => 'dropdown',
                'Options' => $customFileds,  // Заменете с реалните опции
                'Description' => 'Изберете името на персонализираното поле, където номерът на ДДС се съхранява за клиента.',
                'Default' => 'ДДС №',
            ],
            'include' => [
                'FriendlyName' => 'Включени файлове',
                "Type" => "dropdown",
                "Options" => [
                    'original' => 'Оригинал',
                    'copy' => 'Копие',
                    'both' => 'И двете',
                ],
                'Description' => 'Изберете какъв тип файл да бъде включен в имейла или за изтегляне.',
                'Default' => 'original',
            ],
            'receiving_type' => [
                'FriendlyName' => 'Тип на получаване',
                "Type" => "dropdown",
                "Options" => [
                    'download' => 'Изтегляне',
                    'email' => 'Имейл',
                ],
                'Description' => 'Изберете действие, което да се изпълни, когато потребителят натисне бутона на страницата с фактурите.',
                'Default' => 'email',
            ],
            'email_delivery' => [
                'FriendlyName' => 'Доставка по имейл',
                "Type" => "dropdown",
                "Options" => [
                    'file' => 'Файл',
                    'link' => 'Линк',
                ],
                'Size' => 5,  // This controls how many options are displayed at once in the dropdown; adjust as needed
                'Description' => 'Тази опция се използва само ако Тип на получаване е имейл. Ако изберете "Файл", ще изпратите PDF на имейла на клиента.',
                'Default' => 'file', // default to 'Option 1' selected, for example
            ],
            'generate_on_paid' => [
                'FriendlyName' => 'Генериране при плащане',
                'Description' => 'Дали да се генерира фактура автоматично, когато се регистрира като платена?',
                "Type" => "yesno",
                'Default' => '',
            ],
            'generate_for_companies' => [
                'FriendlyName' => 'Генериране само за фирми (При плащане на фактурата)',
                'Description' => 'Дали да се генерират фактури само за фирми, без фиризически  лица ? (Това не важи при изтегляне на фактура от бутона)',
                "Type" => "yesno",
                'Default' => 'on',
            ],

        ]
    ];
}


function invbg_activate()
{
    if (!Capsule::schema()->hasTable('invbg_invoices')) {
        Capsule::schema()->create('invbg_invoices', function ($table) {
            $table->bigIncrements('id');  // bigint auto increment
            $table->bigInteger('whmcs_id');
            $table->bigInteger('invbg_id');
            $table->date('date');
        });
        logActivity("[INV.BG MODULE] Info: 'invbg_invoices' table did not exist and was created.");
    }
    return array('status' => 'success', 'description' => 'Module activated successfully');
}