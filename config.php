<?php

    function db_base() {
        static $db_base;
        return !isset($db_base) ? $db_base = mysqli_connect("localhost", "wb_postavki_user", "123", "wb_postavki") : $db_base; 
    }

   
    define("KEY_2", "Укажите свои API ключи");
    
    define("KEY_1", "Укажите свои API ключи");

?>