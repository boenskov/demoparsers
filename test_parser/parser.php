#!/usr/bin/php
<?php

namespace Parser\Test;

use Parser\ParserCore;

require_once "../core/bootstrap.php";


/**
 * Тестовый парсер
 *
 * (для экспериментов)
 */
class Parser extends ParserCore {

    protected function do_init(){
        $this->print_info("do init");
    }

    protected function do_parse(){
        $this->print_info("do_parse");
    }

}

# запуск скрипта
$parser=new Parser();
$parser->run();
