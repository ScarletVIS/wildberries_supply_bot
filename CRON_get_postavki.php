<?php
    ini_set("max_execution_time", 70);

    include_once 'config.php';

    // Функция рекурсивно ждет наступления следующей минуты
    // function waitForNextMinute() {
    //     // Получаем текущую минуту
    //     $current_minute_func = date('i');

    //     // Ожидаем 500 миллисекунд (.5 секунды), чтобы не делать паузу слишком длинной
    //     sleep(2); 

    //     // Проверяем снова текущую минуту
    //     $new_minute = date('i');

    //     // Если минута не изменилась, продолжаем рекурсию
    //     if ($current_minute_func === $new_minute) {
    //         waitForNextMinute(); // Рекурсивный вызов функции
    //     } else {
    //         // Когда минута сменилась, функция завершится
    //         echo "Наступила новая минута: $new_minute<br>";
    //     }
    // }


    $otladka_t_time_start = microtime(true);
    $telegram_id = '-123123123';
    $array_with_key = [KEY_1, KEY_2];

    // //ТАК КАК Я ЗАПУСКАЮ НА ЛОКАЛЬНОЙ МАШИНЕ, А НА WINDOWS НЕТ CRON ВИНДА ДЫРЯВАЯ ДАЁТ ТОЛЬКО РАЗ В 5 МИНУТ ЗАПУСКАТЬ, НУЖЕН КОСТЫЛЬ ДЛЯ 5 МИНУТ
    // $count_in_windows = 0;
    // while ($count_in_windows < 5) {
    //     echo 'Обновление скрипта №'.$count_in_windows.'<br>';

        /*************************************************************************************************************************/
        /*************************************** ЦИКЛ ДЛЯ КАЖДОГО КЛЮЧА **********************************************************/
        /*************************************************************************************************************************/
        foreach ($array_with_key as $key) {
            echo 'Обновление ключа<br>';

            $start_minute = date('i');  // Минуты начала выполнения скрипта

            $count_script = 0; // на один ключ, не больше 6
            
            // Получаем текущее время в секундах с миллисекундами (используем для оценки времени выполнения)
            $start_time = microtime(true);
            $current_minute = $start_minute;  // Инициализируем текущую минуту
        
            // Рассчитываем предполагаемое время следующей итерации (это должно быть до цикла)
            $estimated_next_iteration = microtime(true) + 4;  // Текущее время + 4 секунд

        /*************************************************************************************************************************/
        /*************************************** ЦИКЛ ДЛЯ КАЖДОГО КЛЮЧА НЕ БОЛЬШЕ 6 НА КЛЮЧ **************************************/
        /*************************************************************************************************************************/
            while ($count_script < 6 && $current_minute == $start_minute && date('i', (int)$estimated_next_iteration) == $start_minute) {
                echo ($count_script.'<br>');
                // Фиксируем начало итерации (точное время в секундах с миллисекундами)
                $iteration_start_time = microtime(true);

                /*************************************************************************************************************************/
                /*************************************** ПРОВЕРИМ КАКИЕ СКЛАДЫ НАМ НУЖНЫ *************************************************/
                /*************************************************************************************************************************/
                $q  = mysqli_query(db_base(), "SELECT * FROM WB_postavki_limits_warehouses WHERE flag_get = 1;");
                $array_with_warehouses = [];
                $array_with_warehouses_and_types = [];
                if (mysqli_num_rows($q)>0) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $array_with_warehouses[$r['warehouseID']] = $r['warehouseID'];
                        $array_with_warehouses_and_types[$r['warehouseID']][] = $r['boxTypeID'];
                    }
                } else {
                    exit;
                }


                /*************************************************************************************************************************/
                /*************************************** API WB **************************************************************************/
                /*************************************************************************************************************************/
                $wa_IDs = implode(',', $array_with_warehouses);
                $link = 'https://supplies-api.wildberries.ru/api/v1/acceptance/coefficients?warehouseIDs='.$wa_IDs;	

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $link);
                curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36");
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Тайм-аут в секундах
                //ОТКЛЮЧАЕМ SSL ДЛЯ ЛОКАЛЬНОЙ МАШИНЫ
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$key));
                curl_setopt($ch, CURLOPT_POST, false);	
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result_REQUEST = curl_exec($ch);


                if ($result_REQUEST == false) {
                    //echo "Ошибка cURL: " . curl_error($ch);
                    mysqli_query(db_base(), "INSERT INTO WB_postavki_log_api (date_add, check_suc, response) VALUES (NOW(), 0, 'Ошибка cURL: ".curl_error($ch)."');");
                    curl_close($ch);
                    exit;
                } else {
                    $result_REQUEST = json_decode($result_REQUEST, TRUE);
                    curl_close($ch);
                }
                if (isset($result_REQUEST['title']) && $result_REQUEST['title'] != '') {
                    mysqli_query(db_base(), "INSERT INTO WB_postavki_log_api (date_add, check_suc, response) VALUES (NOW(), 0, 'Ошибка: ".mysqli_real_escape_string(db_base(), json_encode($result_REQUEST))."');");
                    curl_close($ch);
                    exit;
                }
                		
                /*************************************************************************************************************************/
                /******************* НУЖНО ПРОВЕРИТЬ ЕСЛИ МЫ НАШЛИ НУЖНЫЙ ЛИМИТ ТО НЕМЕДЛЕННО ВЫЗЫВАЕМ БОТА НА ПОМОЩЬЬЬЬ *****************/
                /*************************************************************************************************************************/
                $array_reg_postavki = [];
                $q = mysqli_query(db_base(), "SELECT * FROM WB_postavki_reg WHERE check_complete = 0 AND in_progress = 0;");
                if (mysqli_num_rows($q) > 0) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $array_reg_postavki[$r['warehouseID']][$r['boxTypeID']] = $r;
                    }
                }

                /*************************************************************************************************************************/
                /*************************************** ЗАПИСЫВАЕМ В БД НУЖНЫЕ НАМ СТРОКИ ***********************************************/
                /*************************************************************************************************************************/
                //исключаем сегодняшнюю дату и следующие 5 дней
                //$dont_now_data = date('Y-m-d', strtotime('+5 day'));
      
                $flag_in_progress = false;
                $array_postavki = [];
                $count = 0;
                echo '|кол-во поставок'.count($result_REQUEST).'|';
                // print_r('<pre>');
                // print_r($result_REQUEST);
                // print_r('</pre>');
                // date('Y-m-d',strtotime($postavka['date'])) < $dont_now_data ||
                foreach ($result_REQUEST as $postavka) {
                    if (!isset($postavka['boxTypeID']) || $postavka['boxTypeID'] == '') {
                        $postavka['boxTypeID'] = 0;
                    }
                    if ($postavka['coefficient'] == -1 || $postavka['coefficient'] > 5 ||  !in_array($postavka['boxTypeID'], $array_with_warehouses_and_types[$postavka['warehouseID']])) continue;
                    array_push($array_postavki, "(NOW(),'{$postavka['date']}','{$postavka['coefficient']}','{$postavka['warehouseID']}','{$postavka['warehouseName']}','{$postavka['boxTypeName']}','{$postavka['boxTypeID']}')");
                    
                
                    
                    /*************************************************************************************************************************/
                    /******************* НУЖНО ПРОВЕРИТЬ ЕСЛИ МЫ НАШЛИ НУЖНЫЙ ЛИМИТ ТО НЕМЕДЛЕННО ВЫЗЫВАЕМ БОТА НА ПОМОЩЬЬЬЬ *****************/
                    /*************************************************************************************************************************/
                    if (!empty($array_reg_postavki[$postavka['warehouseID']][$postavka['boxTypeID']]) && !$flag_in_progress && date('Y-m-d',strtotime($postavka['date'])) > date('Y-m-d',strtotime($array_reg_postavki[$postavka['warehouseID']][$postavka['boxTypeID']]['min_date_postavka']))) {
                        mysqli_query(db_base(),"UPDATE WB_postavki_reg SET in_progress = 1, date_last_try = NOW() WHERE id = ".$array_reg_postavki[$postavka['warehouseID']][$postavka['boxTypeID']]['id'].";");
                        // Выполняем другой скрипт в фоне

                        $param1 = $array_reg_postavki[$postavka['warehouseID']][$postavka['boxTypeID']]['id'];

                        exec("C:\\wamp64\\bin\\php\\php7.4.33\\php.exe C:\\wamp64\\www\\bot_wb\\index.php $param1 > NUL 2>&1");
                        $flag_in_progress = true;

                        print_r('<pre>');
                        print_r('aaaaaaaaaaaaaaaaaaaa мы нашли лимит <br>');
                        print_r('</pre>');
                        

                    }
                   
                    if ($count == 500) {
                        mysqli_query(db_base(), "INSERT INTO WB_postavki_limits (date_add, date_postavka, coefficient, warehouseID, warehouseName, boxTypeName, boxTypeID) VALUES ".implode(',', $array_postavki).";");
                        $array_postavki = [];
                        $count = 0;
                    }
                    $count++;

                }
                

                if ($count != 0 && !empty($array_postavki)) {
                    mysqli_query(db_base(), "INSERT INTO WB_postavki_limits (date_add, date_postavka, coefficient, warehouseID, warehouseName, boxTypeName, boxTypeID) VALUES ".implode(',', $array_postavki).";");
                }

                /*************************************************************************************************************************/
                /*************************************** В УСЛОВИЕ $where ЗАПИСЫВАЕМ СТРОКИ КОТОРЫЕ УЖЕ ПОКАЗЫВАЛ БОТ В ТЕЧЕНИИ 30 минут */
                /*************************************************************************************************************************/
                $q = mysqli_query(db_base(), "SELECT date_postavka, boxTypeID, warehouseID FROM WB_postavki_limits WHERE check_send = 1 AND date_add >= NOW()-INTERVAL 25 MINUTE GROUP BY date_postavka, boxTypeID, warehouseID;");
                if (mysqli_num_rows($q) > 0) {
                    $conditions = [];
                    
                    while ($r = mysqli_fetch_assoc($q)) {
                        $conditions[] = "({$r['warehouseID']}, '{$r['date_postavka']}', {$r['boxTypeID']})";
                    }
                
                    // Преобразуем условия в строку для использования в запросе
                    $condition_str = implode(',', $conditions);
                    
                    // Используем конструкцию IN для проверки всех комбинаций
                    $where = "AND (l.warehouseID, l.date_postavka, l.boxTypeID) NOT IN ($condition_str)";
                } else {
                    $where = "";
                }

                /*************************************************************************************************************************/
                /*************************************** ОТПРАВКА УВЕДОМЛЕНИЙ В ЧАТ КУРАТОРОМ ********************************************/
                /*************************************************************************************************************************/
                //AND l.date_postavka > '{$dont_now_data}'
                //print_r('</pre>');
                $q = mysqli_query(db_base(),"SELECT l.* FROM WB_postavki_limits l 
                LEFT JOIN WB_postavki_limits_warehouses lw ON l.boxTypeID = lw.boxTypeID AND l.warehouseID = lw.warehouseID 
                WHERE lw.flag_get = 1 
                AND l.coefficient < 5 
                AND l.coefficient >= 0 
                AND l.date_add >= NOW()-INTERVAL 5 SECOND
                
                AND l.check_send = 0 ".$where.";");
                if (mysqli_num_rows($q) > 0) {
                    $text_comment = 'Доступны поставки: %0A';
                    $count = 0;
                    $array_with_IDs = [];
                    while ($r = mysqli_fetch_assoc($q)) {
                        if ($r['coefficient'] == 0) $r['coefficient'] = 'Бесплатно';
                        $text_comment .= date('Y-m-d',strtotime($r['date_postavka']))." - ".$r['warehouseName']." - ".$r['boxTypeName']." x".$r['coefficient'].";%0A";
                        $count++;
                        if ($count == 10) {
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
                            $count = 0;
                            $text_comment = 'Доступны поставки: %0A';
                        }
                        array_push($array_with_IDs, $r['id']);
                    }
                    if ($count != 0) {

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
                    }
                    
                    //записываем что мы отправили уведомление
                    if (!empty($array_with_IDs)) {
                        mysqli_query(db_base(),"UPDATE WB_postavki_limits SET check_send = 1 WHERE id IN (".implode(',', $array_with_IDs).");");
                    }

                }
                
                
                // Увеличиваем счетчик итераций
                $count_script++;
                            
                
                // Ждем 4 секунд
                sleep(4);
                // Фиксируем конец итерации
                $iteration_end_time = microtime(true);
                
                // Обновляем текущее время (для следующей проверки минуты)
                $current_minute = date('i');
                
                // Рассчитываем время выполнения текущей итерации
                $execution_time = $iteration_end_time - $iteration_start_time;

               
                // Рассчитываем предполагаемое время следующей итерации
                $estimated_next_iteration = microtime(true) + $execution_time;  // Текущее время + 4 секунд
                
                
            }
            sleep(1);
            $otladka_t_time_end = microtime(true);
            echo ($otladka_t_time_end - $otladka_t_time_start).'<br>';

            
            
            
            
           
        }
    //     if ($start_minute == date('i')) {
    //         waitForNextMinute();
    //     }
        

    //     $count_in_windows++;
    // }

    

?>