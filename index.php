<?php

include_once 'functions.php';
include_once 'config.php';
require 'vendor/autoload.php'; // Composer autoload

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Interactions\WebDriverActions;


$telegram_id = '123123123123';

// Адрес Selenium Server
$serverUrl = 'http://localhost:4444/wd/hub';

// Путь к профилю Chrome
$profilePath = 'C:/Users/scarl/AppData/Local/Google/Chrome/User Data/Profile 2';

// Настройки Chrome
$chromeOptions = new ChromeOptions();
$chromeOptions->addArguments(["user-data-dir=$profilePath"]);

// Или, если хотите указать конкретный размер окна
$chromeOptions->addArguments(["--window-size=1920,1080"]);

// Создаем экземпляр браузера (Chrome)
$driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $chromeOptions));
// $_SERVER['argv'][1] = 1;
$array_reg_postavki = [];
$q = mysqli_query(db_base(), "SELECT * FROM WB_postavki_reg WHERE id = ".$_SERVER['argv'][1].";");
if (mysqli_num_rows($q) > 0) {
    while ($r = mysqli_fetch_assoc($q)) {
        $array_reg_postavki = $r;
    }
}

$q_sklad = mysqli_query(db_base(), "SELECT * FROM WB_postavki_limits_warehouses WHERE warehouseID = ".$array_reg_postavki['warehouseID']." limit 1;");
$r_sklad = mysqli_fetch_assoc($q_sklad);


startBrouser($driver, $array_reg_postavki['url']);


$count_td = 0;

// ДАТА ДОЛЖНА БЫТЬ БОЛЬШЕ 9 ДНЕЙ
// КОЭФФИЦЕНТ ОТ 0 ДО 5
$MIN_KOEF = 0;
$MAX_KOEF = $array_reg_postavki['max_coefficient'];
$DATE_MIN = date('Y-m-d', strtotime($array_reg_postavki['min_date_postavka']));
try {
    // Явное ожидание до 5 секунд
    $wait = new WebDriverWait($driver, 5); // Ждем до 5 секунд
    $actions = new WebDriverActions($driver); // Создаем объект Actions
    // Ожидание появления элемента с частичным совпадением класса
    $wait->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//button[span[contains(text(), 'Перенести поставку') or contains(text(), 'Запланировать поставку')]]"))
    );

    // Находим все элементы с классом, содержащим "Table__body__"
    $btn_perenos_postavki = $driver->findElement(WebDriverBy::xpath("//button[span[contains(text(), 'Перенести поставку') or contains(text(), 'Запланировать поставку')]]"));
    $btn_perenos_postavki->click();
    
    // Ожидание появления элемента с частичным совпадением класса
    $wait->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//table[starts-with(@class, 'Calendar-plan-view__calendar-table')]//td[not(contains(@class, 'disabled'))]"))
    );

    $tdElements = $driver->findElements(WebDriverBy::xpath("//table[starts-with(@class, 'Calendar-plan-view__calendar-table')]//td[not(contains(@class, 'disabled'))]"));

    

    // Проход по каждому элементу td
    foreach ($tdElements as $td) {
        if ($td->getText() == '') continue;
        // Найти все span внутри текущего td с атрибутом data-name="Text"
        $spanElements = $td->findElements(WebDriverBy::xpath(".//span[@data-name='Text']"));

        // Собираем текст из span в массив
        $temp_array[$count_td] = [];
        foreach ($spanElements as $span) {
            $temp_array[$count_td][] = $span->getText();
        }

        // Проверка наличия элементов перед доступом
        if (isset($temp_array[$count_td][0])) {
            $date_postavka = parseDate($temp_array[$count_td][0]); // Дата поставки
        } else {
            $date_postavka = 'Invalid date';
        }




        if (isset($temp_array[$count_td][2]) && strpos($temp_array[$count_td][2], 'Бесплатно') !== false) {
            $temp_array[$count_td][3] = 0;
        }
        

        if (isset($temp_array[$count_td][3])) {
           
            $coefficient = $temp_array[$count_td][3]; // Коэффициент
        } else {
            $coefficient = 'N/A';
        }

       

        // Проверка, соответствует ли коэффициент числовому диапазону
        if (is_numeric($coefficient)) {
            $coefficient = (float)$coefficient; // Преобразуем коэффициент в число
        } else {
            $coefficient = null; // Если коэффициент не числовой, устанавливаем значение null
        }

        //НАЧАЛО
        //ЕСЛИ НАШЛИ ЛИМИТ ЖМЕМ ЕКАРНЫЙ БАБАЙ 
            // Проверка условий
            if ($date_postavka !== 'Invalid date' && $coefficient !== null) {
                if ($date_postavka > $DATE_MIN && $coefficient >= $MIN_KOEF && $coefficient <= $MAX_KOEF) {
                    // Если дата больше $DATE_MIN и коэффициент в пределах допустимого диапазона
                    // Наведение курсора на элемент
                    $actions->moveToElement($td)->perform();
                    //echo "навел на элемент ".$td->getText().'<br>';

                    // Поиск кнопки внутри текущего элемента td
                    $button = $td->findElement(WebDriverBy::xpath(".//button[.//span[text()='Выбрать']]"));

                    if ($button) {
                        // Нажимаем на кнопку
                        $button->click();
                        usleep(5000);
                        $button_final = $driver->findElement(WebDriverBy::xpath("//button[span[normalize-space(text()) = 'Перенести' or normalize-space(text()) = 'Запланировать']]"));
                        // Делаем скриншот и сохраняем в файл
                        $screenshot = $driver->takeScreenshot(__DIR__ . '/' . date('Y_m_d_H_i_s').'_screenshot.png');
                        //НУ ВОТ И ВСЁ ГОТОВО 
                        $wait->until(function($driver) {
                            $button_final = $driver->findElement(WebDriverBy::xpath("//button[span[normalize-space(text()) = 'Перенести' or normalize-space(text()) = 'Запланировать']]"));
                            return !$button_final->getAttribute('disabled'); // Проверка, что атрибут disabled отсутствует
                        });


                        $button_final->click();
                        
                        sleep(7);
                        
                        // Делаем скриншот и сохраняем в файл
                        $screenshot = $driver->takeScreenshot(__DIR__ . '/' . date('Y_m_d_H_i_s').'_screenshot.png');

                        mysqli_query(db_base(),"UPDATE WB_postavki_reg SET check_complete = 1 WHERE id = ".$_SERVER['argv'][1].";");
                        $text_comment = 'Попытка перенести лимиты на складе: '.$r_sklad['warehouseName'].'%0AНа дату:'.$date_postavka.';%0A С коэффицентом: x'.$coefficient;
                            

                        break;

                    } else {
                        $text_comment = 'Не успел перенести лимит на складе: '.$r_sklad['warehouseName'].' Не найдена кнопка ВЫБРАТЬ';
                        echo "Button with text 'Выбрать' not found.\n";
                    }
                } else {
                    // mysqli_query(db_base(),"UPDATE WB_postavki_reg SET in_progress = 0 WHERE id = ".$_SERVER['argv'][1].";");
                    $text_comment = 'Не успел перенести лимит на складе: '.$r_sklad['warehouseName'].' Коэф изменился';

                }
            } else {
                // mysqli_query(db_base(),"UPDATE WB_postavki_reg SET in_progress = 0 WHERE id = ".$_SERVER['argv'][1].";");
                $text_comment = 'Не успел перенести лимит на складе: '.$r_sklad['warehouseName'];


            }
        //ЕСЛИ НАШЛИ ЛИМИТ ЖМЕМ ЕКАРНЫЙ БАБАЙ
        //КОНЕЦ

        if ($count_td == 13) { 
          
        }
        $count_td++;
    }
   
    // Делаем скриншот и сохраняем в файл
    $driver->takeScreenshot(__DIR__ . '/' . date('Y_m_d_H_i_s').'_screenshot.png');
    mysqli_query(db_base(),"UPDATE WB_postavki_reg SET in_progress = 0 WHERE id = ".$_SERVER['argv'][1].";");

} catch (TimeoutException $e) {
    // Обработка ситуации, когда элемент не был найден за 5 секунды
    $text_comment = 'Не смог перенести лимит на складе: '.$r_sklad['warehouseName'].' Элемент с нужным классом не был найден в течение 5 секунд.';
    echo "Элемент с классом не был найден в течение 5 секунд.<br>";
}
 // Делаем скриншот и сохраняем в файл
 $driver->takeScreenshot(__DIR__ . '/' . date('Y_m_d_H_i_s').'_screenshot.png');
 sleep(2);
$text_comment=str_replace(' ', '_', $text_comment);	

$url="https://www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment&user_id=".$telegram_id."&type=&text=".$text_comment."";
$ch = curl_init();
    curl_setopt_array($ch, array( 
    CURLOPT_HEADER => true,
    CURLOPT_URL => $url,
    CURLOPT_POST => false,
    CURLOPT_RETURNTRANSFER => 1
));
//ОТКЛЮЧАЕМ SSL ДЛЯ ЛОКАЛЬНОЙ МАШИНЫ
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result=curl_exec($ch);
curl_close($ch);

closeBrouser($driver);


?>

