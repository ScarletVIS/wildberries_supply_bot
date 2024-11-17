<?php
require 'vendor/autoload.php'; // Composer autoload

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

// Адрес Selenium Server
$serverUrl = 'http://localhost:4444/wd/hub';

// Путь к профилю Chrome
$profilePath = 'C:/Users/user/AppData/Local/Google/Chrome/User Data/Profile 2';

// Настройки Chrome
$chromeOptions = new ChromeOptions();
$chromeOptions->addArguments(["user-data-dir=$profilePath"]);

// Создаем экземпляр браузера (Chrome)
$driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $chromeOptions));

// Открываем страницу для авторизации
$driver->get('https://seller-auth.wildberries.ru/');

// Подожди, пока ты вручную выполнишь авторизацию (например, 10 минут)
echo "Авторизуйтесь в браузере и затем нажмите любую клавишу, чтобы продолжить...";
sleep(60);

// Сохраняем куки
$cookies = $driver->manage()->getCookies();
file_put_contents('cookies.json', json_encode($cookies));

// Закрываем браузер
$driver->quit();
?>
