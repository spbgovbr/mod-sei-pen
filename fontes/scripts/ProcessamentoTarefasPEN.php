<?php

$dirSeiWeb = !defined("DIR_SEI_WEB") ? getenv("DIR_SEI_WEB") ?: __DIR__."/../../web" : DIR_SEI_WEB;
require_once $dirSeiWeb . '/SEI.php';

// PHP internal, faz com que o tratamento de sinais funcione corretamente
// TODO: Substituir declara��o por pcntl_async_signal no php 7
declare(ticks=1); 

$bolInterromper = false;
function tratarSinalInterrupcaoProc($sinal)
{
    global $bolInterromper;
    $bolInterromper = true;
    printf("\nAten��o: Sinal de interrup��o do processamento de pend�ncias recebido. Finalizando processamento ...%s", PHP_EOL);
}


class ProcessamentoTarefasPEN
{
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new ProcessamentoTarefasPEN();
        }
        return self::$instance;
    }

    function __construct()
    {
        ini_set('max_execution_time','0');
        ini_set('memory_limit','-1');
    
        pcntl_signal(SIGINT, 'tratarSinalInterrupcaoProc');
        pcntl_signal(SIGTERM, 'tratarSinalInterrupcaoProc');
        pcntl_signal(SIGHUP, 'tratarSinalInterrupcaoProc');     
    }
    

    public function processarPendencias()
    {
        InfraDebug::getInstance()->setBolLigado(true);
        InfraDebug::getInstance()->setBolDebugInfra(false);
        InfraDebug::getInstance()->setBolEcho(true);
        InfraDebug::getInstance()->limpar();
    
        try {
            SessaoSEI::getInstance(false);
            $objProcessarPendenciasRN = new ProcessarPendenciasRN("PROCESSAMENTO");
            $resultado = $objProcessarPendenciasRN->processarPendencias();
            exit($resultado);
        } finally {
            InfraDebug::getInstance()->setBolLigado(false);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(false);
        }        
    }    
}


// Garante que c�digo abaixo foi executado unicamente via linha de comando
if ($argv && $argv[0] && realpath($argv[0]) === __FILE__) {
    
    ProcessamentoTarefasPEN::getInstance()->processarPendencias();

    // ini_set('max_execution_time','0');
    // ini_set('memory_limit','-1');

    // pcntl_signal(SIGINT, 'tratarSinalInterrupcaoProc');
    // pcntl_signal(SIGTERM, 'tratarSinalInterrupcaoProc');
    // pcntl_signal(SIGHUP, 'tratarSinalInterrupcaoProc'); 

    // InfraDebug::getInstance()->setBolLigado(true);
    // InfraDebug::getInstance()->setBolDebugInfra(false);
    // InfraDebug::getInstance()->setBolEcho(true);
    // InfraDebug::getInstance()->limpar();

    // try {
    //     SessaoSEI::getInstance(false);
    //     $objProcessarPendenciasRN = new ProcessarPendenciasRN("PROCESSAMENTO");
    //     $resultado = $objProcessarPendenciasRN->processarPendencias();
    //     exit($resultado);
    // } finally {
    //     InfraDebug::getInstance()->setBolLigado(false);
    //     InfraDebug::getInstance()->setBolDebugInfra(false);
    //     InfraDebug::getInstance()->setBolEcho(false);
    // }
}

?>
