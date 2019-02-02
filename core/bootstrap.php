<?php

namespace Parser;

require_once "ParserException.php";

# не определять повторно класс если он уже где-то определен
if(class_exists(ParserCore::class)) return;

require_once "./ParserException.php";

/**
 * Основной код отвечающий за загрузку, исполнение в консоли, создание процессов и подключение к очереди
 *
 * параметры командной строки:
 *   parser.php parse "request string"    парсинг запроса напрямую
 *   parser.php init                      предварительная инициализация и регулярные процедуры
 *   parser.php daemon                    запуск в режиме демона, регистрация в менеджере парсеров
 *   parser.php help                      показать подсказку
 *
 *
 * Функции которые ДОЛЖНЫ быть реализованы в модуле парсера:
 *   do_parse    выполнение парсинга на основе полученных параметров
 *
 *
 * Функции которые МОГУТ быть реализованы в модуле парсера:
 *   do_init     предварительная подготовка парсера (например скачивание файлов с данными)
 */

abstract class ParserCore
{
    private $start_time=0;
    /**
     * команда полученная в результате разбора командной строки
     *
     * @var string
     */
    public $command="help";
    /**
     * массив аргументов командной строки или запроса
     * @var array
     */
    public $arguments=[];

    /**
     * Уровень отладки 0 - отключена, далее чем больше тем подробнее
     * @var int
     */
    public $debug_level=0;

    /**
     * Сообщение об ошибке (если есть)
     * @var bool|string
     */
    public $error=false;

    public $stats=[];

    /**
     * ParserCore constructor.
     */
    public function __construct()
    {
        $this->start_time=microtime(true);
    }

    /**
     * Точка входа для всех запусков из консоли
     */
    public function run()
    {
        try {
            #cli_set_process_title("hello");
            # Проверка основных параметров системы
            $this->check_requirements();

            # проверим что нам передали в параметрах
            global $argv;
            $this->parse_arguments($argv);

            #var_dump($this->arguments);
            $this->process_command();
        } catch (ParserException $e){
            $this->print_error("[{$e->getCode()}] {$e->getMessage()}");
            echo json_encode(["error"=>[
                "message"=>$e->getMessage(),
                "code"=>$e->getCode()
            ]],JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Проверка основных параметров системы
     */
    protected function check_requirements(){
        # предварительные проверки
        if (PHP_SAPI !== 'cli') {
            $this->print_error("Ошибка: Скрипт должен быть запущен из консоли");
            exit(1);
        }
        if (!stristr(PHP_OS, 'LINUX')) {
            $this->print_error("Ошибка: Поддерживается только Linux в качестве хостовой ОС");
            exit(1);
        }
        $required_version = "5.6.0";
        if (!version_compare(PHP_VERSION, $required_version, ">=")) {
            $this->print_error("Ошибка: Версия PHP должна быть не ниже версии $required_version. Текущая версия: " . PHP_VERSION);
            exit(1);
        }
    }

    protected function parse_arguments($argv){
        array_shift($argv);

        #var_dump($argv);
        if(!empty($argv)){
            $this->command=array_shift($argv);
        }
        if(empty($argv)) return;

        foreach($argv as $arg){
            list($key,$val)=array_pad(explode("=",$arg,2),2,true);
            $key=preg_replace("#^-*#","",$key);
            $this->arguments[$key]=$val;
        }

        # применим служебные аргументы
        if(isset($this->arguments["debug"])){
            $this->debug_level=($this->arguments["debug"]===TRUE)?1:$this->arguments["debug"];
        }
        if(isset($this->arguments["verbose"])){
            $this->debug_level=1;
        }
    }

    protected function process_command(){
        $cmd="do_".$this->command;
        $result=null;
        if(method_exists($this,$cmd)){
            if(empty($this->arguments["help"]))
                $result=$this->{$cmd}();
            else
                $this->do_help();
        } else {
            $this->print_error("Неправильная комманда {$this->command}");
            $this->do_help();
        }

        if($result!==null){
            if(is_array($result) || is_object($result)){
                $result=json_encode($result,JSON_UNESCAPED_UNICODE);
            }
            echo $result.PHP_EOL;
        }
    }

    /**
     * очистка текста комментария от обрамляющих символов
     * @param $comment
     */
    private function _clean_comment(&$comment){
        #var_dump($comment);
        # удалим все строки имеющие комментарий начинающийся с "@"
        $comment=preg_replace('#^\s*\*?\s*@.*$#m',"",$comment);
        # уберем начало блока комментариев
        $comment=preg_replace("#^/\*\*#m","",$comment);
        # уберем конец блока комментариев
        $comment=preg_replace("#\*/$#m","",$comment);
        # уберем звездочки в начале каждой строки
        $comment=preg_replace("#^[ \t\*]*#m","",$comment);
        $comment=trim($comment);
        #var_dump($comment);
    }

    #############################
    # логирование и вывод сообщений
    #############################

    /**
     * Вывод сообщения об ошибке в STDERR
     *
     * @param $msg
     */
    protected function print_error($msg){
        fwrite(STDERR, "[ОШИБКА] {$msg}\n");
        $this->error=$msg;
    }

    /**
     * Вывод информационного сообщения в STDOUT
     * @param $msg
     */
    protected function print_info($msg){
        fwrite(STDOUT, $msg."\n");
    }

    /**
     * Отладочное сообщение
     *
     * Уровень отладки устанавливается значением $debug_level
     *
     * @param     $msg
     * @param int $min_level    минимальный уровень при котором необходимо выводить сообщение
     */
    protected function debug($msg, $min_level=1){
#        echo json_encode([$this->debug_level,$min_level]);
        if($min_level<=$this->debug_level) {
            $time=number_format((microtime(true)-$this->start_time),4,".","");
            $this->print_info("[DEBUG][{$time}] $msg");
        }
    }


    #############################
    # работа в режиме АПИ-демона
    #############################

    /**
     * Регистрация парсера в менеджере парсеров
     *
     * Информация о парсере:
     * name: машинное имя парсера
     * type: active (прослушивает порт и может сразу принимать данные) | passive (сам запрашивает данные из учереди у менеджера)
     * fast: парсер является быстрым (скорость ответа менее 1сек) (только для type=active)
     * port:[80] на каком порту висит парсер (только для type=active)
     *
     */
    protected function registerInManager(){

    }


    #############################
    # обработчики команд
    #############################

    /** показать общую справку */
    protected function do_help(){
        $class=get_class($this);

        # показывать общую документацию
        $root_help=true;
        if($this->command!="help"){
            if(method_exists($this,"do_{$this->command}")) {
                $root_help = false;
            }
        }

        if($root_help) {
            $r=new \ReflectionClass($class);
            $comment=$r->getDocComment();
            if(!empty($comment)) {
                $this->print_info("Описание:");
                $this->print_info("");
                $this->_clean_comment($comment);
                $this->print_info($comment);
                $this->print_info("");
            }
            $this->print_info("Использование парсера:");
            $this->print_info("  php parser.php команда [аргументы]        - выполнить команду");
            $this->print_info("  php parser.php команда --help             - описание команды");
            $this->print_info("");
            $this->print_info("Опции:");
            $this->print_info("  --debug[=0..2]          уровень детализации отладочных сообщений (0=без сообщений, если не указано, то, по-умолчанию - 1)");
            $this->print_info("  --verbose               аналог опции --debug=1");
            $this->print_info("");
            $this->print_info("Доступные команды:");
        } else {
            $this->print_info("Описание команды:");
        }

        #var_dump(get_class_methods($class));
        foreach (get_class_methods($class) as $method){
            if(!$root_help && $method!="do_{$this->command}")
                continue;
            if(strpos($method,"do_")===FALSE)
                continue;

            $doc_class=$class;
            # сделаем перебор предков в поисках документации по методу
            # (на случай если в потомке решили не описывать метод)
            do {
                $r = new \ReflectionMethod($doc_class, $method);
                #var_dump($method);
                #var_dump($r->getDocComment());
                if ($r)
                    $comment = $r->getDocComment();
                else
                    $comment = false;
                if (!empty($comment)) {
                    $this->_clean_comment($comment);
                    if ($root_help) {
                        # для общей справки покажем только первую строку
                        list($comment) = explode("\n", $comment);
                    }
                }
            }while(empty($comment) && $doc_class=get_parent_class($doc_class));

            #var_dump($comment);
            $msg="  ".str_pad(preg_replace("#^do_#","",$method),20);
            if(!empty($comment)) $msg.=" - {$comment}";
            $this->print_info($msg);
        };
        $this->print_info("");
    }

    /**
     * Выполнить подготовительные работы по инициализации парсера (например, загрузка необходимых файлов)
     */
    protected function do_init(){}

    /**
     * Выполнить парсинг ресурса в поисках запрошенной информации
     */
    protected abstract function do_parse();
}
