# Бот для бронирования поставок WB #

1. Установите Google Chrome
Убедитесь, что у вас установлен браузер Google Chrome.
Чтобы использовать профиль для авторизации

2. Скачайте Chrome WebDriver
Зайдите на страницу загрузки ChromeDriver.
Скачайте версию WebDriver, соответствующую версии вашего Chrome.
Разархивируйте файл и сохраните chromedriver.exe в удобное место

3. Установите Selenium Server
Загрузите Selenium Server с официального сайта.
Сохраните .jar файл, например, в C:\selenium.

4. Запустите Selenium Server
Для работы Selenium WebDriver требуется сервер. Запустите его командой в терминале:
`java -jar C:\selenium\selenium-server-standalone-<версия>.jar`
Убедитесь, что у вас установлена Java (JDK или JRE).

5. Установите Composer
Если Composer ещё не установлен, скачайте его с официального сайта и установите.

6. Установите библиотеку для работы с Selenium в PHP
Через Composer установите PHP-библиотеку для Selenium:
`composer require php-webdriver/webdriver`

8. Запустите скрипт
Запустите start.bat в CRON каждую минуту