<?php

/**
 * Скрипт упаковки парсера в PHAR-архив
 *
 * Запуск:
 *
 * php pack.php <parser_dir_name>
 *
 * Например:
 * pack.php passports
 */

# проверки
if($argc<2)
    die("Не указано название директории парсера для упаковки".PHP_EOL);

$parser_name=$argv[1];
$dir=__DIR__."/".$parser_name;

if(!is_dir($dir))
    die("Некорректный путь для парсера: $dir".PHP_EOL);

ini_set("phar.readonly",0);
if (!Phar::canWrite()) die("canWrite=false".PHP_EOL);

echo "Создаем {$dir}.phar".PHP_EOL;
if(file_exists($dir.".phar"))
    unlink($dir.".phar");

$phar=new Phar($dir.".phar");

echo "Добавляем ядро\n";
#$phar->addFile(__DIR__."/core/bootstrap.php","core/bootstrap.php");
// добавить все файлы в каталоге /путь/к/проекту/project, сохранение в phar-архив с префиксом "project"
$phar->buildFromIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/core",FilesystemIterator::SKIP_DOTS)), __DIR__);


#var_dump($dir);
$files=glob($dir."/*");
#var_dump($files);
foreach ($files as $file){
#    var_dump($file);
    $dst=str_replace($dir,"",$file);
    echo "Добавляем файлы парсера $file => $dst \n";
    $phar->addFile($file,$dst);
}

# добавим index файл
$phar["index.php"]='<?php
require "parser.php";
';

echo "Сжатие".PHP_EOL;
$phar->compressFiles(Phar::GZ);

echo "Готово".PHP_EOL;
