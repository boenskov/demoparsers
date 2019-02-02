<?php
##!/usr/bin/php

namespace Parser\Passports;

use Parser\ParserCore;
use Parser\ParserException;

if(file_exists("./core/bootstrap.php"))
    require_once "./core/bootstrap.php";
else
    require_once "../core/bootstrap.php";

/**
 * Проверка по списку недействительных российских паспортов
 * ========================================================
 * http://сервисы.гувм.мвд.рф/info-service.htm?sid=2000
 *
 * Данный сервис является информационным, предоставляемая информация не является юридически значимой.
 *
 * Источником информационного сервиса является ежедневно обновляемый список недействительных паспортов размещенный
 * на сайте МВД России в формате открытых данных. Данный список является обезличенным и не нарушает действующее
 * законодательство в области персональных данных.
 *
 * Файлы сервиса содержат список недействительных (утраченных (похищенных), оформленных на утраченных (похищенных)
 * бланках паспорта гражданина Российской Федерации, выданных в нарушение установленного порядка, а также признанных
 * недействительными) паспортов граждан Российской Федерации, удостоверяющих личность гражданина Российской Федерации
 * на территории Российской Федерации
 *
 * @TODO сделать возможность использовать устаревшие файлы и один большой файл пока не готовы вспомогательные файлы
 */
class Parser extends ParserCore {
    static $passport_list=[];

    private $passport_file=false;
    private $temp_dir=false;

    /**
     * Parser constructor.
     * @param bool $temp_dir
     */
    public function __construct()
    {
        $this->temp_dir=sys_get_temp_dir();
        #$this->temp_dir="/mnt/ramdisk";

        #$this->passport_file="/tmp/passports";
        $this->passport_file=$this->temp_dir."/passports";

        parent::__construct();
    }

    # init на SDD - 567sec
    # ramdisk - 418sec (sudo mkdir /mnt/ramdisk && sudo mount -t tmpfs -o size=3.5G tmpfs /mnt/ramdisk)
    protected function do_init(){
        $temp_dir=$this->temp_dir;

        #$this->print_info("do init");
        $this->debug("Проверка наличия файла с данными");

        $need_update=false;
        if(!file_exists($this->passport_file)) {
            $this->debug("Файл не обнаружен");
            $need_update=true;
        } elseif(date("dmy")!=date("dmy",filemtime($this->passport_file))){
            $this->debug("Файл устарел");
            $need_update=true;
        }
        if($need_update){

            # зачистка
            foreach([
                # скаченный оригинал
                $temp_dir."/list_of_expired_passports.csv.bz2",
                # распакованный оригинал
                $temp_dir."/list_of_expired_passports.csv",
                # временный файл в который предварительно распаковываем оригинал
                $this->passport_file.".tmp",
                # файл-признак успешности подготовки вспомогательных файлов
                $temp_dir."/pasp_good_marker"
                ] as $file) {
                if (file_exists($file)) unlink($file);
            }
            foreach(range(0,99) as $id) {
                $id=str_pad($id,2,"0",STR_PAD_LEFT);
                $file=$temp_dir."/pasp_{$id}.tmp";
                if (file_exists($file)) unlink($file);
            }

            $this->debug("Скачиваем...");
            #echo $temp_dir;
            #exit();
            #system("cd $temp_dir");
            $download="https://guvm.mvd.ru/upload/expired-passports/list_of_expired_passports.csv.bz2";
            #$type="wget";
            $type="curl";
            if($type=="wget") {
                if (!$this->debug_level)
                    $wget_options = "--quiet";
                elseif (!$this->debug_level > 1)
                    $wget_options = "--debug";
                else
                    $wget_options = "--verbose";

                system("cd $temp_dir && wget {$wget_options} $download", $result);
                if ($result) {
                    $this->print_error("Ошибка получения файла $download");
                } else {
                    $this->debug("Распаковываем...");
                    system("cd $temp_dir && bunzip2 -f --stdout ./list_of_expired_passports.csv.bz2 >'{$this->passport_file}.tmp'", $result);
                    if ($result) {
                        $this->print_error("Ошибка распаковки файла list_of_expired_passports.csv.bz2");
                    } else {
                        # удалим запакованный вариант
                        unlink($temp_dir . "/list_of_expired_passports.csv.bz2");
                        $this->debug("Заменяем файл с данными...");
                        if (file_exists($this->passport_file)) unlink($this->passport_file);
                        rename($this->passport_file . ".tmp", $this->passport_file);
                    }
                }
            } elseif($type=="curl") {
                $cmd = "curl $download | bunzip2 --stdout >'{$this->passport_file}.tmp'";
                system($cmd, $result);
                if ($result) {
                    $this->print_error("Ошибка получения файла $download");
                } else {
                    //# удалим запакованный вариант
                    //unlink($temp_dir . "/list_of_expired_passports.csv.bz2");
                    $this->debug("Заменяем файл с данными...");
                    if (file_exists($this->passport_file)) unlink($this->passport_file);
                    rename($this->passport_file . ".tmp", $this->passport_file);
                }
            }
            # проверка успеха
            if(file_exists($this->passport_file) && filesize($this->passport_file)>1000000)
                $this->debug("Успешно");
            else
                $this->print_error("Файл не существует или его размер некорректен");
        }

        # чтение в память данных
        #
        # пустое чтение
        # массив значений
        # как ключи
        # как int-ключи
        # как hash-ключи


        # подготовка временных вспомогательных файлов (если отсутствуют или не готовы)
        if(!$this->error){

            #$mem_before = memory_get_usage();
            $time = microtime(true);
            $last_time = $time;

            if(!file_exists($temp_dir."/pasp_good_marker")) {
//                foreach (range(0, 99) as $id) {
//                    $id = str_pad($id, 2, "0", STR_PAD_LEFT);
//                    $file = $temp_dir . "/pasp_{$id}.tmp";
//                    if (file_exists($file)) unlink($file);
//                }
//
//
//                #$passport_list=[];
//                $row = 0;
//                $b = 0;
//
//                $this->debug("Готовим вспомогательные файлы");
//                $files = [];
//                foreach (range(0, 99) as $id) {
////                    $id = str_pad($id, 2, "0", STR_PAD_LEFT);
////                    $file = $temp_dir . "/pasp_{$id}.tmp";
////                    $files[$id] = fopen($file, "w");
//
//                    $uid=str_pad("$id",2,"0",STR_PAD_LEFT);
//                    $this->debug("Готовим '$uid'");
//
//                    $file = $temp_dir . "/pasp_{$uid}.tmp";
//                    $cmd="grep '^{$uid}' {$this->passport_file} > $file";
//                    system ($cmd);
//                    #system("cd $temp_dir && wget {$wget_options} $download",$result);
//                }

                $this->debug("Оптимизация файлов");
                $cmd=__DIR__."/../passport_opt";
                if(!file_exists($cmd))
                    $cmd=__DIR__."/passport_opt";
                if(!file_exists($cmd))
                    $this->print_error("Не найден файл оптимизатора");
                system($cmd);
                # по этому файлу определяем что файлы полностью сгенерировались
                file_put_contents($temp_dir . "/pasp_good_marker", "1");

                $this->debug("Вспомогательные файлы готовы");

                #echo json_encode(unpack("q",pack("q","9999999999")));
                #echo convBase("9999999999","0123456789",'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYabcdefghijklmnopqrstuvwxy');
#            echo $this->conv("1234567890");
#            echo $this->conv("0000000000");
#            echo $this->conv("9999999999");
                #echo $this->conv("9999999999");

#            exit();

//                $f = fopen($this->passport_file, "r");
//                $filesize=filesize($this->passport_file);
//                #$f_out=fopen($this->passport_file.".bin","");
//                if (!$f) {
//                    $this->print_error("Невозможно открыть файл {$this->passport_file} на чтение");
//                } else {
//                    #$cnt=1000000;
//                    $old_buffer = "";
//                    while (
//#                    ($buffer = fgets($f, 100)) !== false
//                    !empty(($buffer = fread($f, 200000)))
//                    ) {
//                        $b += strlen($buffer);
//                        $buffer = str_replace(",", "", $buffer);
//                        $buffer = $old_buffer . $buffer;
//                        $buffer = explode("\n", $buffer);
//                        #$row+=count($buffer);
//
//                        foreach ($buffer as $buf) {
//                            if (strlen($buf) == 10) {
//                                $row++;
//                                $k = $buf;
//                                #$kk=base_convert($k,10,36);
//                                #$kk=$this->pack_string($k);
//                                $kk = $k;
//                                $gr = substr($kk, 0, 2);
//                                #file_put_contents($temp_dir."/pasp{$gr}",$kk."\n",FILE_APPEND);
//                                if (isset($files[$gr]))
//                                    fwrite($files[$gr], "{$kk}\n");
//
//                                #self::$passport_list[]=$k;
//                                #self::$passport_list[$k]=$k;
//                                #self::$passport_list[$kk]=$kk;
//                                #$kk=base_convert("9999999999",10,36);
//                                #$kk=base64_encode(pack("q","9999999999"));
//                            }
//                        }
//
//
//                        if ((microtime(true) - $last_time) > 10) {
//                            $last_time = microtime(true);
//                            $this->debug("row=$row b=$b last=$kk [".(int)($b/$filesize*100)."%]");
//                            #$this->debug(json_encode($buffer));
//                        }
//                    }
////                if (!feof($f)) {
////                    $this->print_error("Ошибка: fgets() неожиданно потерпел неудачу");
////                }
//                    fclose($f);
//
//                    # закроем все вспомогательные файлы
//                    foreach ($files as $f) {
//                        fclose($f);
//                    }
//                    # по этому файлу определяем что файлы полностью сгенерировались
//                    file_put_contents($temp_dir . "/pasp_good_marker", "1");
//
//                    $this->debug("Вспомогательные файлы готовы");
//                }
            }
//            $passports=["5400,"/*"5400,053305","4503,174100","0300,975102"*/];
//            if(!is_array($passports)) $rules=$passports; else $rules=join('\|',$passports);
//            $file=$this->passport_file;
//            #$cmd = "grep -c -m 1 \"$rules\" $file";
//            $cmd = "grep \"$rules\" $file";
//            if(defined("PARSER_DEBUG"))   var_dump($cmd);
//            $count = trim(shell_exec($cmd));
//            $this->debug($count);

            $this->debug("time=".(microtime(true)-$time));
            #$this->debug("mem=".(memory_get_usage()-$mem_before));

        }

//        foreach (["5400053305","4503174100","0300975102"] as $q) {
//            $this->arguments["query"] = $q;
//            $this->do_parse();
//        }
    }

    /**
     * Запрос на поиск паспорта должен содержать обязательный параметр query содержащий номер проверяемого паспорта
     *
     * Пример:
     *      query=5400053305
     *
     * Возвращаемое значение:
     *      массив ["номер паспорта"]=true если паспорт найден (т.е. паспорт недействителен) и пустой массив, если не найден
     *
     * @return array
     * @throws \Exception
     *
     * @TODO рассмотреть необходимость добавления поиска по пачке паспортов
     */
    protected function do_parse(){
        $this->debug(__FUNCTION__);
        if(isset($this->arguments['query']))
            $this->debug(json_encode($this->arguments['query']));
        else
            throw new ParserException("Не указан обязательный элемент query");

        $time = microtime(true);

        $passport=$this->arguments['query'];
        # проверим на соответствие формату
        if(!is_string($passport) || !ctype_digit($passport) || strlen($passport)!=10)
            throw new ParserException("'$passport' не является корректным паспортом РФ. Необходимо указывать 10 цифр (серия+номер)");
        #if(!is_array($passports)) $rules=$passports; else $rules=join('\|',$passports);

        # проверим на наличие вспомогательного файла
        $gr=substr($passport,0,2);
        $temp_dir=$this->temp_dir;
        $result=[];
        $file=$temp_dir."/pasp_good_marker";
        $file_gr = $temp_dir . "/passports{$gr}.tmp";
        if(file_exists($file) && file_exists($file_gr)) {
            $rules=$passport;
            $this->debug("Ищем в файле $file_gr");
            $cmd = "grep \"$rules\" $file_gr";
            $founded = trim(shell_exec($cmd));
            $this->debug("Результат: ".$founded);

            if(!empty($founded) && strpos($founded,$passport)!==false){
                $result[$passport]=true;
            }
        } else{
            # поищем в основном файле
            $file=$temp_dir."/list_of_expired_passports.csv";
            if(file_exists($file)) {
                preg_match("#^([0-9]{4})([0-9]{6})$#",$passport,$m);
                $rules="{$m[1]},{$m[2]}";
                $cmd = "grep \"$rules\" $file_gr";
                $founded = trim(shell_exec($cmd));
                $this->debug($founded);

                if(!empty($founded) && strpos($founded,$passport)!==false){
                    $result[$passport]=true;
                }
            } else {
                #$this->print_error("Нет необходимых файлов с данными");
                throw new ParserException("Нет необходимых файлов с данными");
            }
        }


//            $file=$this->passport_file;
//            #$cmd = "grep -c -m 1 \"$rules\" $file";
//            $cmd = "grep \"$rules\" $file";
//            if(defined("PARSER_DEBUG"))   var_dump($cmd);
//            $count = trim(shell_exec($cmd));
//            $this->debug($count);
        $this->debug("time=".(microtime(true)-$time));

        return $result;
    }

//    private function pack_string($in){
//        $digs='0123456789'.'ABCDEFGHIJKLMNOPQRSTUVWXYabcdefghijklmnopqrstuvwxy';
//        $div=strlen($digs);
//        $result="";
//
//        while($in){
//            $ost=$in%$div;
//            $in=(int)($in/$div);
//            $result.=$digs[$ost];
//        }
//        return strrev($result);
//    }
}

# запуск скрипта
$parser=new Parser();
$parser->run();


