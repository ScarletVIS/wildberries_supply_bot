<?php

function startBrouser($driver, $url) {
    // Загружаем куки
    // $cookies = json_decode(file_get_contents('cookies.json'), true);
    // foreach ($cookies as $cookie) {
    //     $driver->manage()->addCookie($cookie);
    // }
    // Открываем страницу
    $driver->get($url);
}


function closeBrouser($driver) {
    // Сохраняем куки
    $cookies = $driver->manage()->getCookies();
    file_put_contents('cookies.json', json_encode($cookies));
    // Закрываем браузер
    $driver->quit();
}


// Преобразуем строку даты в формат 'd F'
function parseDate($dateString) {
    // Массив для перевода месяцев на английский
    $months = [
        'января' => 'January',
        'февраля' => 'February',
        'марта' => 'March',
        'апреля' => 'April',
        'мая' => 'May',
        'июня' => 'June',
        'июля' => 'July',
        'августа' => 'August',
        'сентября' => 'September',
        'октября' => 'October',
        'ноября' => 'November',
        'декабря' => 'December'
    ];

    // Удаляем ненужные символы и переводим месяц
    $dateString = trim($dateString);
    $parts = explode(' ', $dateString);

    if (count($parts) < 2) {
        throw new Exception('Invalid date format');
    }

    $day = $parts[0];
    $month = str_replace(array_keys($months), array_values($months), $parts[1]);

    // Проверяем корректность перевода месяца
    if (!$month) {
        throw new Exception('Month translation failed');
    }

    // Преобразуем строку даты в объект DateTime
    $dateStringEn = "$day $month";
    $dateStringEn = str_replace(',', '', $dateStringEn);
    $date = DateTime::createFromFormat('j F', $dateStringEn);

    // Проверка на успешное создание даты
    if (!$date) {
        // Выводим ошибку и форматируем строку для отладки
        $errors = DateTime::getLastErrors();
        throw new Exception('Date conversion failed: ' . print_r($errors, true));
    }

    // Устанавливаем текущий год
    $currentYear = (int)date('Y');
    $now = new DateTime();
    $currentMonth = (int)$now->format('n');

    // Устанавливаем год
    if ($date->format('n') < $currentMonth) {
        // Если месяц даты меньше текущего месяца, устанавливаем следующий год
        $year = $currentYear + 1;
    } else {
        // Иначе устанавливаем текущий год
        $year = $currentYear;
    }

    // Устанавливаем год в объекте DateTime
    $date->setDate($year, $date->format('n'), $date->format('j'));

    // Форматируем дату в 'Y-m-d'
    $formattedDate = $date->format('Y-m-d');

    return $formattedDate;
}