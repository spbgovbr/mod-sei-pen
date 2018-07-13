<?php
//@TODOJOIN: VERIFICAR SE N�O EXISTEM TRY CATCH QUE OCULTAM ERROS. CASO EXISTAM CATCH COM EXEPTION DO PHP, RETIRALOS
class ProcessoEletronicoRN extends InfraRN {

    //const PEN_WEBSERVICE_LOCATION = 'https://desenv-api-pen.intra.planejamento/interoperabilidade/soap/v1_1/';

  /* TAREFAS DE EXPEDI��O DE PROCESSOS */
  //Est� definindo o comportamento para a tarefa $TI_PROCESSO_EM_PROCESSAMENTO
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_EXPEDIDO = 'PEN_PROCESSO_EXPEDIDO';
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_RECEBIDO = 'PEN_PROCESSO_RECEBIDO';
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_CANCELADO = 'PEN_PROCESSO_CANCELADO';
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_RECUSADO = 'PEN_PROCESSO_RECUSADO';
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_EXTERNO = 'PEN_OPERACAO_EXTERNA';
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_ABORTADO = 'PEN_EXPEDICAO_PROCESSO_ABORTADA';


  /* N�VEL DE SIGILO DE PROCESSOS E DOCUMENTOS */
  public static $STA_SIGILO_PUBLICO = '1';
  public static $STA_SIGILO_RESTRITO = '2';
  public static $STA_SIGILO_SIGILOSO = '3';

  /* RELA��O DE SITUA��ES POSS�VEIS EM UM TR�MITE */
  public static $STA_SITUACAO_TRAMITE_INICIADO = 1;                           // Iniciado - Metadados recebidos pela solu��o
  public static $STA_SITUACAO_TRAMITE_COMPONENTES_ENVIADOS_REMETENTE = 2;     // Componentes digitais recebidos pela solu��o
  public static $STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO = 3;    // Metadados recebidos pelo destinat�rio
  public static $STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO = 4; // Componentes digitais recebidos pelo destinat�rio
  public static $STA_SITUACAO_TRAMITE_RECIBO_ENVIADO_DESTINATARIO = 5;        // Recibo de conclus�o do tr�mite enviado pelo destinat�rio do processo
  public static $STA_SITUACAO_TRAMITE_RECIBO_RECEBIDO_REMETENTE = 6;          // Recibo de conclus�o do tr�mite recebido pelo remetente do processo
  public static $STA_SITUACAO_TRAMITE_CANCELADO = 7;                          // Tr�mite do processo ou documento cancelado pelo usu�rio (Qualquer situa��o diferente de 5 e 6)
  public static $STA_SITUACAO_TRAMITE_RECUSADO = 8;                           // Tr�mite do processo recusado pelo destinat�rio (Situa��es 2, 3, 4)
  public static $STA_SITUACAO_TRAMITE_CIENCIA_RECUSA = 9;                           // Remetente ciente da recusa do tr�mite

  public static $STA_TIPO_RECIBO_ENVIO = '1'; // Recibo de envio
  public static $STA_TIPO_RECIBO_CONCLUSAO_ENVIADO = '2'; // Recibo de recebimento enviado
  public static $STA_TIPO_RECIBO_CONCLUSAO_RECEBIDO = '3'; // Recibo de recebimento recebido

  /* OPERA��ES DO HIST�RICO DO PROCESSO */
  // 02 a 18 est�o registrados na tabela rel_tarefa_operacao
  public static $OP_OPERACAO_REGISTRO = "01";



  const ALGORITMO_HASH_DOCUMENTO = 'SHA256';

  /**
   * Motivo para recusar de tramite de componente digital pelo formato
   */
  const MTV_RCSR_TRAM_CD_FORMATO = '01';
  /**
   * Motivo para recusar de tramite de componente digital que esta corrompido
   */
  const MTV_RCSR_TRAM_CD_CORROMPIDO = '02';
  /**
   * Motivo para recusar de tramite de componente digital que n�o foi enviado
   */
  const MTV_RCSR_TRAM_CD_FALTA = '03';

  /**
   * Esp�cie documentoal n�o mapeada
   */
  const MTV_RCSR_TRAM_CD_ESPECIE_NAO_MAPEADA = '03';

  /**
   * Motivo para recusar de tramite de componente digital
   */
  const MTV_RCSR_TRAM_CD_OUTROU = '99';

  public static $MOTIVOS_RECUSA = array(
      "01"  => "Formato de componente digital n�o suportado",
      "02" => "Componente digital corrompido",
      "03" => "Falta de componentes digitais",
      "04" => "Esp�cie documental n�o mapeada no destinat�rio",
      "99" => "Outro"
  );


  private $strWSDL = null;
  private $objPenWs = null;
  private $options = null;

  public function __construct() {
    $objPenParametroRN = new PenParametroRN();

    $strEnderecoWebService = $objPenParametroRN->getParametro('PEN_ENDERECO_WEBSERVICE');
    $strLocalizacaoCertificadoDigital =  $objPenParametroRN->getParametro('PEN_LOCALIZACAO_CERTIFICADO_DIGITAL');
    $strSenhaCertificadoDigital =  $objPenParametroRN->getParametro('PEN_SENHA_CERTIFICADO_DIGITAL');

    if (InfraString::isBolVazia($strEnderecoWebService)) {
      throw new InfraException('Endere�o do servi�o de integra��o do Processo Eletr�nico Nacional (PEN) n�o informado.');
    }

    //TODO: Urgente - Remover senha do certificado de autentica��o dos servi�os do PEN da tabela de par�metros
    if (InfraString::isBolVazia($strSenhaCertificadoDigital)) {
      throw new InfraException('Dados de autentica��o do servi�o de integra��o do Processo Eletr�nico Nacional(PEN) n�o informados.');
    }

    $this->strWSDL = $strEnderecoWebService . '?wsdl';
    $this->strComumXSD = $strEnderecoWebService . '?xsd=comum.xsd';
    $this->strLocalCert = $strLocalizacaoCertificadoDigital;
    $this->strLocalCertPassword = $strSenhaCertificadoDigital;

    $this->options = array(
      'soap_version' => SOAP_1_1
      , 'local_cert' => $this->strLocalCert
      , 'passphrase' => $this->strLocalCertPassword
      , 'resolve_wsdl_remote_includes' => true
      , 'cache_wsdl'=> WSDL_CACHE_NONE
      , 'trace' => true
      , 'encoding' => 'UTF-8'
      , 'attachment_type' => BeSimple\SoapCommon\Helper::ATTACHMENTS_TYPE_MTOM
      , 'ssl' => array(
          'allow_self_signed' => true,
        )
      );
  }

  protected function inicializarObjInfraIBanco()
  {
    return BancoSEI::getInstance();
  }

    /**
     * Verifica se o uma url esta ativa
     *
     * @param string $strUrl url a ser testada
     * @param string $strLocalCert local f�sico do certificado .pem
     * @throws InfraException
     * @return null
     */
    private function testaUrl($strUrl = '', $strLocalCert = ''){

        $arrParseUrl = parse_url($this->strWSDL);
        // � melhor a p�gina inicial que todo o arquivo wsdl
        $strUrl = $arrParseUrl['scheme'].'://'.$arrParseUrl['host'];

        $strCommand = sprintf('curl %s --insecure --cert %s 2>&1', $strUrl, $this->options['local_cert']);
        $numRetorno = 0;
        $arrOutput = array();

        @exec($strCommand, $arrOutput, $numRetorno);

        if($numRetorno > 0){

            throw new InfraException('Falha de comunica��o com o Barramento de Servi�os. Por favor, tente novamente mais tarde.', $e);
        }
    }

   public function testarDisponibilidade(){

       try{
           $this->testaUrl($this->strWSDL, $this->options['local_cert']);
           return true;
       } catch (Exception $ex) {
           return false;
       }

   }

  private function getObjPenWs() {

    if($this->objPenWs == null) {
      $this->testaUrl($this->strWSDL, $this->options['local_cert']);
      try {

        $objConfig = ConfiguracaoSEI::getInstance();

        if($objConfig->isSetValor('SEI', 'LogPenWs')){

            $this->objPenWs = new LogPenWs($objConfig->getValor('SEI', 'LogPenWs'), $this->strWSDL, $this->options);
        }
        else {

            $this->objPenWs = new BeSimple\SoapClient\SoapClient($this->strWSDL, $this->options);
        }
     } catch (Exception $e) {
        throw new InfraException('Erro acessando servi�o.', $e);
      }
    }

    return $this->objPenWs;
  }

    //TODO: Avaliar otimiza��o de tal servi�o para buscar individualmente os dados do reposit�rio de estruturas
  public function consultarRepositoriosDeEstruturas($numIdentificacaoDoRepositorioDeEstruturas) {

    $objRepositorioDTO = null;

    try{
      $parametros = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura->ativos = false;

      $result = $this->getObjPenWs()->consultarRepositoriosDeEstruturas($parametros);

      if(isset($result->repositoriosEncontrados->repositorio)){

        if(!is_array($result->repositoriosEncontrados->repositorio)) {
          $result->repositoriosEncontrados->repositorio = array($result->repositoriosEncontrados->repositorio);
        }

        foreach ($result->repositoriosEncontrados->repositorio as $repositorio) {
          if($repositorio->id == $numIdentificacaoDoRepositorioDeEstruturas){
            $objRepositorioDTO = new RepositorioDTO();
            $objRepositorioDTO->setNumId($repositorio->id);
            $objRepositorioDTO->setStrNome(utf8_decode($repositorio->nome));
            $objRepositorioDTO->setBolAtivo($repositorio->ativo);
          }
        }
      }
    } catch(Exception $e){
      throw new InfraException("Erro durante obten��o dos reposit�rios", $e);
    }

    return $objRepositorioDTO;
  }

  public function listarRepositoriosDeEstruturas() {

    $arrObjRepositorioDTO = array();

    try{
      $parametros = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura->ativos = true;

      $result = $this->getObjPenWs()->consultarRepositoriosDeEstruturas($parametros);

      if(isset($result->repositoriosEncontrados->repositorio)){

        if(!is_array($result->repositoriosEncontrados->repositorio)) {
          $result->repositoriosEncontrados->repositorio = array($result->repositoriosEncontrados->repositorio);
        }

        foreach ($result->repositoriosEncontrados->repositorio as $repositorio) {
          $item = new RepositorioDTO();
          $item->setNumId($repositorio->id);
          $item->setStrNome(utf8_decode($repositorio->nome));
          $item->setBolAtivo($repositorio->ativo);
          $arrObjRepositorioDTO[] = $item;
        }
      }
    } catch(Exception $e){
      throw new InfraException("Erro durante obten��o dos reposit�rios", $e);
    }

    return $arrObjRepositorioDTO;
  }

  public function consultarEstrutura($idRepositorioEstrutura, $numeroDeIdentificacaoDaEstrutura, $bolRetornoRaw = false) {

        try {
            $parametros = new stdClass();
            $parametros->filtroDeEstruturas = new stdClass();
            $parametros->filtroDeEstruturas->identificacaoDoRepositorioDeEstruturas = $idRepositorioEstrutura;
            $parametros->filtroDeEstruturas->numeroDeIdentificacaoDaEstrutura = $numeroDeIdentificacaoDaEstrutura;
            $parametros->filtroDeEstruturas->apenasAtivas = false;

            $result = $this->getObjPenWs()->consultarEstruturas($parametros);

            if ($result->estruturasEncontradas->totalDeRegistros == 1) {

                $arrObjEstrutura = is_array($result->estruturasEncontradas->estrutura) ? $result->estruturasEncontradas->estrutura : array($result->estruturasEncontradas->estrutura);
                $objEstrutura = current($arrObjEstrutura);

                $objEstrutura->nome = utf8_decode($objEstrutura->nome);
                $objEstrutura->sigla = utf8_decode($objEstrutura->sigla);

                if ($bolRetornoRaw !== false) {
                    if (isset($objEstrutura->hierarquia) && isset($objEstrutura->hierarquia->nivel)) {
                        if (!is_array($objEstrutura->hierarquia->nivel)) {
                            $objEstrutura->hierarquia->nivel = array($objEstrutura->hierarquia->nivel);
                        }

			         foreach ($objEstrutura->hierarquia->nivel as &$objNivel) {
                            $objNivel->nome = utf8_decode($objNivel->nome);
                        }
                    }
                    return $objEstrutura;
                }
                else {

                    $objEstruturaDTO = new EstruturaDTO();
                    $objEstruturaDTO->setNumNumeroDeIdentificacaoDaEstrutura($objEstrutura->numeroDeIdentificacaoDaEstrutura);
                    $objEstruturaDTO->setStrNome($objEstrutura->nome);
                    $objEstruturaDTO->setStrSigla($objEstrutura->sigla);
                    $objEstruturaDTO->setBolAtivo($objEstrutura->ativo);
                    $objEstruturaDTO->setBolAptoParaReceberTramites($objEstrutura->aptoParaReceberTramites);
                    $objEstruturaDTO->setStrCodigoNoOrgaoEntidade($objEstrutura->codigoNoOrgaoEntidade);
                    return $objEstruturaDTO;
                }
            }
        }
        catch (Exception $e) {
            throw new InfraException("Erro durante obten��o das unidades", $e);
        }
    }

    public function listarEstruturas($idRepositorioEstrutura, $nome='')
  {
    $arrObjEstruturaDTO = array();

    try{
      $idRepositorioEstrutura = filter_var($idRepositorioEstrutura, FILTER_SANITIZE_NUMBER_INT);
      if(!$idRepositorioEstrutura) {
        throw new InfraException("Reposit�rio de Estruturas inv�lido");
      }

      $parametros = new stdClass();
      $parametros->filtroDeEstruturas = new stdClass();
      $parametros->filtroDeEstruturas->identificacaoDoRepositorioDeEstruturas = $idRepositorioEstrutura;
      $parametros->filtroDeEstruturas->apenasAtivas = true;

      $nome = trim($nome);
      if(is_numeric($nome)) {
        $parametros->filtroDeEstruturas->numeroDeIdentificacaoDaEstrutura = intval($nome);
      } else {
        $parametros->filtroDeEstruturas->nome = utf8_encode($nome);
      }

      $result = $this->getObjPenWs()->consultarEstruturas($parametros);

      if($result->estruturasEncontradas->totalDeRegistros > 0) {

        if(!is_array($result->estruturasEncontradas->estrutura)) {
          $result->estruturasEncontradas->estrutura = array($result->estruturasEncontradas->estrutura);
        }

        foreach ($result->estruturasEncontradas->estrutura as $estrutura) {
          $item = new EstruturaDTO();
          $item->setNumNumeroDeIdentificacaoDaEstrutura($estrutura->numeroDeIdentificacaoDaEstrutura);
          $item->setStrNome(utf8_decode($estrutura->nome));
          $item->setStrSigla(utf8_decode($estrutura->sigla));
          $item->setBolAtivo($estrutura->ativo);
          $item->setBolAptoParaReceberTramites($estrutura->aptoParaReceberTramites);
          $item->setStrCodigoNoOrgaoEntidade($estrutura->codigoNoOrgaoEntidade);

            if(!empty($estrutura->hierarquia->nivel)) {
                $array = array();
                foreach($estrutura->hierarquia->nivel as $nivel) {
                    $array[] = utf8_decode($nivel->sigla);
                }
                $item->setArrHierarquia($array);
            }

          $arrObjEstruturaDTO[] = $item;
        }
      }

    } catch (Exception $e) {
      throw new InfraException("Erro durante obten��o das unidades", $e);
    }

    return $arrObjEstruturaDTO;
  }

  public function consultarMotivosUrgencia()
  {
    $curl = curl_init($this->strComumXSD);
    curl_setopt($curl, CURLOPT_URL, $this->strComumXSD);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSLCERT, $this->strLocalCert);
    curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $this->strLocalCertPassword);
    $output = curl_exec($curl);
    curl_close($curl);

    $dom = new DOMDocument;
    $dom->loadXML($output);

    $xpath = new DOMXPath($dom);

    $rootNamespace = $dom->lookupNamespaceUri($dom->namespaceURI);
    $xpath->registerNamespace('x', $rootNamespace);
    $entries = $xpath->query('/x:schema/x:simpleType[@name="motivoDaUrgencia"]/x:restriction/x:enumeration');

    $resultado = array();
    foreach ($entries as $entry) {
      $valor = $entry->getAttribute('value');

      $documentationNode = $xpath->query('x:annotation/x:documentation', $entry);
      $descricao = $documentationNode->item(0)->nodeValue;

      $resultado[$valor] = utf8_decode($descricao);
    }

    return $resultado;
  }

  public function enviarProcesso($parametros)
  {
    try {
      return $this->getObjPenWs()->enviarProcesso($parametros);
    } catch (\SoapFault $fault) {


        if (!empty($fault->detail->interoperabilidadeException->codigoErro) && $fault->detail->interoperabilidadeException->codigoErro == '0005') {
            $mensagem = 'O c�digo mapeado para a unidade ' . utf8_decode($parametros->novoTramiteDeProcesso->processo->documento[0]->produtor->unidade->nome) . ' est� incorreto.';
        } else {
            $mensagem = $this->tratarFalhaWebService($fault);
        }
            //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
            //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  public function listarPendencias($bolTodasPendencias)
  {

    $arrObjPendenciaDTO = array();

    try {
      $parametros = new stdClass();
      $parametros->filtroDePendencias = new stdClass();
      $parametros->filtroDePendencias->todasAsPendencias = $bolTodasPendencias;
      $result = $this->getObjPenWs()->listarPendencias($parametros);

      if(isset($result->listaDePendencias->IDT)){

        if(!is_array($result->listaDePendencias->IDT)) {
          $result->listaDePendencias->IDT = array($result->listaDePendencias->IDT);
        }

        foreach ($result->listaDePendencias->IDT as $idt) {
          $item = new PendenciaDTO();
          $item->setNumIdentificacaoTramite($idt->_);
          $item->setStrStatus($idt->status);
          $arrObjPendenciaDTO[] = $item;
        }
      }
    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }

    return $arrObjPendenciaDTO;
  }

    //TODO: Tratar cada um dos poss�veis erros gerados pelos servi�os de integra��o do PEN
  private function tratarFalhaWebService(SoapFault $fault)
  {
    error_log('$e->faultcode:' . $fault->faultcode);
    error_log('$e->detail:' . print_r($fault->detail, true));

    $mensagem = $fault->getMessage();
    if(isset($fault->detail->interoperabilidadeException)){
      $strWsException = $fault->detail->interoperabilidadeException;

      switch ($strWsException->codigoErro) {
        case '0044':
        $mensagem = 'Processo j� possui um tr�mite em andamento';
        break;

        default:
        $mensagem = utf8_decode($fault->detail->interoperabilidadeException->mensagem);
        break;
      }
    }

    return $mensagem;
  }

  public function construirCabecalho($strNumeroRegistro = null, $idRepositorioOrigem = 0, $idUnidadeOrigem = 0, $idRepositorioDestino = 0,
    $idUnidadeDestino = 0, $urgente = false, $motivoUrgencia = 0, $enviarTodosDocumentos = false)
  {
    $cabecalho = new stdClass();

    if(isset($strNumeroRegistro)) {
      $cabecalho->NRE = $strNumeroRegistro;
    }

    $cabecalho->remetente = new stdClass();
    $cabecalho->remetente->identificacaoDoRepositorioDeEstruturas = $idRepositorioOrigem;
    $cabecalho->remetente->numeroDeIdentificacaoDaEstrutura = $idUnidadeOrigem;

    $cabecalho->destinatario = new stdClass();
    $cabecalho->destinatario->identificacaoDoRepositorioDeEstruturas = $idRepositorioDestino;
    $cabecalho->destinatario->numeroDeIdentificacaoDaEstrutura = $idUnidadeDestino;

    $cabecalho->urgente = $urgente;
    $cabecalho->motivoDaUrgencia = $motivoUrgencia;
    $cabecalho->obrigarEnvioDeTodosOsComponentesDigitais = $enviarTodosDocumentos;

    return $cabecalho;
  }

  public function enviarComponenteDigital($parametros)
  {
    try {
      return $this->getObjPenWs()->enviarComponenteDigital($parametros);
    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);

    //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
    //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }


  public function solicitarMetadados($parNumIdentificacaoTramite) {

    try
    {
      $parametros = new stdClass();
      $parametros->IDT = $parNumIdentificacaoTramite;
      return $this->getObjPenWs()->solicitarMetadados($parametros);
    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
      //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  public static function converterDataWebService($dataHoraSEI)
  {
    $resultado = '';
    if(isset($dataHoraSEI)){
      $resultado = InfraData::getTimestamp($dataHoraSEI);
      $resultado = date(DateTime::W3C, $resultado);
    }

    return $resultado;
  }

  public static function converterDataSEI($dataHoraWebService)
  {
    $resultado = null;
    if(isset($dataHoraWebService)){
      $resultado = strtotime($dataHoraWebService);
      $resultado = date('d/m/Y H:i:s', $resultado);
    }

    return $resultado;
  }

  public static function obterIdTarefaModulo($strIdTarefaModulo)
  {
      $objTarefaDTO = new TarefaDTO();
      $objTarefaDTO->retNumIdTarefa();
      $objTarefaDTO->setStrIdTarefaModulo($strIdTarefaModulo);

      $objTarefaRN = new TarefaRN();
      $objTarefaDTO = $objTarefaRN->consultar($objTarefaDTO);

      if($objTarefaDTO){
          return $objTarefaDTO->getNumIdTarefa();
      }else{
          return false;
      }

  }

  public function cadastrarTramiteDeProcesso($parDblIdProcedimento, $parStrNumeroRegistro, $parNumIdentificacaoTramite, $parDthRegistroTramite, $parObjProcesso, $parNumTicketComponentesDigitais = null, $parObjComponentesDigitaisSolicitados = null)
  {
    if(!isset($parDblIdProcedimento) || $parDblIdProcedimento == 0) {
      throw new InfraException('Par�metro $parDblIdProcedimento n�o informado.');
    }

    if(!isset($parStrNumeroRegistro)) {
      throw new InfraException('Par�metro $parStrNumeroRegistro n�o informado.');
    }

    if(!isset($parNumIdentificacaoTramite) || $parNumIdentificacaoTramite == 0) {
      throw new InfraException('Par�metro $parStrNumeroRegistro n�o informado.');
    }

    if(!isset($parObjProcesso)) {
      throw new InfraException('Par�metro $objProcesso n�o informado.');
    }

    //Monta dados do processo eletr�nico
    $objProcessoEletronicoDTO = new ProcessoEletronicoDTO();
    $objProcessoEletronicoDTO->setStrNumeroRegistro($parStrNumeroRegistro);
    $objProcessoEletronicoDTO->setDblIdProcedimento($parDblIdProcedimento);

    //Montar dados dos procedimentos apensados
    if(isset($parObjProcesso->processoApensado)){
      if(!is_array($parObjProcesso->processoApensado)){
        $parObjProcesso->processoApensado = array($parObjProcesso->processoApensado);
      }

      $arrObjRelProcessoEletronicoApensadoDTO = array();
      $objRelProcessoEletronicoApensadoDTO = null;
      foreach ($parObjProcesso->processoApensado as $objProcessoApensado) {
        $objRelProcessoEletronicoApensadoDTO = new RelProcessoEletronicoApensadoDTO();
        $objRelProcessoEletronicoApensadoDTO->setStrNumeroRegistro($parStrNumeroRegistro);
        $objRelProcessoEletronicoApensadoDTO->setDblIdProcedimentoApensado($objProcessoApensado->idProcedimentoSEI);
        $objRelProcessoEletronicoApensadoDTO->setStrProtocolo($objProcessoApensado->protocolo);
        $arrObjRelProcessoEletronicoApensadoDTO[] = $objRelProcessoEletronicoApensadoDTO;
      }

      $objProcessoEletronicoDTO->setArrObjRelProcessoEletronicoApensado($arrObjRelProcessoEletronicoApensadoDTO);
    }

    //Monta dados do tr�mite do processo
    $objTramiteDTO = new TramiteDTO();
    $objTramiteDTO->setStrNumeroRegistro($parStrNumeroRegistro);
    $objTramiteDTO->setNumIdTramite($parNumIdentificacaoTramite);
    $objTramiteDTO->setNumTicketEnvioComponentes($parNumTicketComponentesDigitais);
    $objTramiteDTO->setDthRegistro($this->converterDataSEI($parDthRegistroTramite));
    $objTramiteDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
    $objTramiteDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
    $objProcessoEletronicoDTO->setArrObjTramiteDTO(array($objTramiteDTO));

    //Monta dados dos componentes digitais
    $arrObjComponenteDigitalDTO = $this->montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $parObjProcesso, $parObjComponentesDigitaisSolicitados);

    $objTramiteDTO->setArrObjComponenteDigitalDTO($arrObjComponenteDigitalDTO);
    $objProcessoEletronicoDTO = $this->cadastrarTramiteDeProcessoInterno($objProcessoEletronicoDTO);

    return $objProcessoEletronicoDTO;
  }


  //TODO: Tratar a exce��o de recebimento de um tr�mite que j� havia sido tratado no sistema
  protected function cadastrarTramiteDeProcessoInternoControlado(ProcessoEletronicoDTO $parObjProcessoEletronicoDTO) {

    if(!isset($parObjProcessoEletronicoDTO)) {
      throw new InfraException('Par�metro $parObjProcessoEletronicoDTO n�o informado.');
    }

    $idProcedimento = $parObjProcessoEletronicoDTO->getDblIdProcedimento();

    //Registra os dados do processo eletr�nico
    //TODO: Revisar a forma como o barramento tratar o NRE para os processos apensados
    $objProcessoEletronicoDTOFiltro = new ProcessoEletronicoDTO();
    $objProcessoEletronicoDTOFiltro->setStrNumeroRegistro($parObjProcessoEletronicoDTO->getStrNumeroRegistro());
    $objProcessoEletronicoDTOFiltro->setDblIdProcedimento($parObjProcessoEletronicoDTO->getDblIdProcedimento());
    $objProcessoEletronicoDTOFiltro->retStrNumeroRegistro();
    $objProcessoEletronicoDTOFiltro->retDblIdProcedimento();

    $objProcessoEletronicoBD = new ProcessoEletronicoBD($this->getObjInfraIBanco());
    $objProcessoEletronicoDTO = $objProcessoEletronicoBD->consultar($objProcessoEletronicoDTOFiltro);

    if(empty($objProcessoEletronicoDTO)) {

        $objProcessoEletronicoDTO = $objProcessoEletronicoBD->cadastrar($objProcessoEletronicoDTOFiltro);
    }

    //Registrar processos apensados
    if($parObjProcessoEletronicoDTO->isSetArrObjRelProcessoEletronicoApensado()) {

        $objRelProcessoEletronicoApensadoBD = new RelProcessoEletronicoApensadoBD($this->getObjInfraIBanco());

        foreach ($parObjProcessoEletronicoDTO->getArrObjRelProcessoEletronicoApensado() as $objRelProcessoEletronicoApensadoDTOFiltro) {

            if($objRelProcessoEletronicoApensadoBD->contar($objRelProcessoEletronicoApensadoDTOFiltro) < 1){

                $objRelProcessoEletronicoApensadoBD->cadastrar($objRelProcessoEletronicoApensadoDTOFiltro);
            }
        }
    }

        //Registrar informa��es sobre o tr�mite do processo
    $arrObjTramiteDTO = $parObjProcessoEletronicoDTO->getArrObjTramiteDTO();
    $parObjTramiteDTO = $arrObjTramiteDTO[0];

    $objTramiteDTO = new TramiteDTO();
    $objTramiteDTO->retNumIdTramite();
    $objTramiteDTO->setStrNumeroRegistro($parObjTramiteDTO->getStrNumeroRegistro());
    $objTramiteDTO->setNumIdTramite($parObjTramiteDTO->getNumIdTramite());

    $objTramiteBD = new TramiteBD($this->getObjInfraIBanco());
    $objTramiteDTO = $objTramiteBD->consultar($objTramiteDTO);

    if($objTramiteDTO == null) {
      $objTramiteDTO = $objTramiteBD->cadastrar($parObjTramiteDTO);
    }

    $objProcessoEletronicoDTO->setArrObjTramiteDTO(array($objTramiteDTO));

    //Registra informa��es sobre o componente digital do documento
    $arrObjComponenteDigitalDTO = array();
    $objComponenteDigitalBD = new ComponenteDigitalBD($this->getObjInfraIBanco());

    $numOrdem = 1;

    foreach ($parObjTramiteDTO->getArrObjComponenteDigitalDTO() as $objComponenteDigitalDTO) {

        $objComponenteDigitalDTOFiltro = new ComponenteDigitalDTO();

        $objComponenteDigitalDTOFiltro->setStrNumeroRegistro($objComponenteDigitalDTO->getStrNumeroRegistro());
        $objComponenteDigitalDTOFiltro->setDblIdProcedimento($objComponenteDigitalDTO->getDblIdProcedimento());
        $objComponenteDigitalDTOFiltro->setDblIdDocumento($objComponenteDigitalDTO->getDblIdDocumento());

         if($objComponenteDigitalBD->contar($objComponenteDigitalDTOFiltro) > 0){
             $numOrdem++;
         }

    }

    foreach ($parObjTramiteDTO->getArrObjComponenteDigitalDTO() as $objComponenteDigitalDTO) {

      //Verifica se o documento foi inserido pelo tr�mite atual
      if($objComponenteDigitalDTO->getDblIdDocumento() != null){

        $objComponenteDigitalDTO->setDblIdProcedimento($idProcedimento);

        $objComponenteDigitalDTOFiltro = new ComponenteDigitalDTO();

        $objComponenteDigitalDTOFiltro->setStrNumeroRegistro($objComponenteDigitalDTO->getStrNumeroRegistro());
        $objComponenteDigitalDTOFiltro->setDblIdProcedimento($objComponenteDigitalDTO->getDblIdProcedimento());
        $objComponenteDigitalDTOFiltro->setDblIdDocumento($objComponenteDigitalDTO->getDblIdDocumento());

        if($objComponenteDigitalBD->contar($objComponenteDigitalDTOFiltro) < 1){

            $objComponenteDigitalDTO->setNumOrdem($numOrdem);
            $objComponenteDigitalDTO->unSetStrDadosComplementares();
            $objComponenteDigitalDTO = $objComponenteDigitalBD->cadastrar($objComponenteDigitalDTO);
            $numOrdem++;
        }
        else {

            //Verifica se foi setado o envio
            if(!$objComponenteDigitalDTO->isSetStrSinEnviar()){
                $objComponenteDigitalDTO->setStrSinEnviar('N');
            }

            // Muda a ID do tramite e o arquivo pode ser enviado
            $objComponenteDigitalBD->alterar($objComponenteDigitalDTO);
        }
        $arrObjComponenteDigitalDTO[] = $objComponenteDigitalDTO;
      }
    }

    $objTramiteDTO->setArrObjComponenteDigitalDTO($arrObjComponenteDigitalDTO);


    //TODO: Adicionar controle de excess�o
    //...

    return $objProcessoEletronicoDTO;
  }

  /**
   * Retorna o hash do objecto do solicitarMetadadosResponse
   *
   * @param object $objMeta tem que ser o componenteDigital->hash
   * @return string
   */
    public static function getHashFromMetaDados($objMeta){

        $strHashConteudo = '';

        if (isset($objMeta)) {
            $matches = array();
            $strHashConteudo = (isset($objMeta->enc_value)) ? $objMeta->enc_value : $objMeta->_;

            if (preg_match('/^<hash.*>(.*)<\/hash>$/', $strHashConteudo, $matches, PREG_OFFSET_CAPTURE)) {
                $strHashConteudo = $matches[1][0];
            }
        }

        return $strHashConteudo;
    }

  private function montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $parObjProcesso, $parObjComponentesDigitaisSolicitados)
  {
    //Monta dados dos componentes digitais
    $arrObjComponenteDigitalDTO = array();
    if(!is_array($parObjProcesso->documento)) {
      $parObjProcesso->documento = array($parObjProcesso->documento);
    }

    foreach ($parObjProcesso->documento as $objDocumento) {
      $objComponenteDigitalDTO = new ComponenteDigitalDTO();
      $objComponenteDigitalDTO->setStrNumeroRegistro($parStrNumeroRegistro);
      $objComponenteDigitalDTO->setDblIdProcedimento($parObjProcesso->idProcedimentoSEI); //TODO: Error utilizar idProcedimentoSEI devido processos apensados
      $objComponenteDigitalDTO->setDblIdDocumento($objDocumento->idDocumentoSEI);
      $objComponenteDigitalDTO->setNumOrdem($objDocumento->ordem);
      $objComponenteDigitalDTO->setNumIdTramite($parNumIdentificacaoTramite);
      $objComponenteDigitalDTO->setStrProtocolo($parObjProcesso->protocolo);

      //Por enquanto, considera que o documento possui apenas um componente digital
      if(is_array($objDocumento->componenteDigital) && count($objDocumento->componenteDigital) != 1) {
        throw new InfraException("Erro processando componentes digitais do processo " . $parObjProcesso->protocolo . "\n Somente � permitido o recebimento de documentos com apenas um Componente Digital.");
      }

      $objComponenteDigital = $objDocumento->componenteDigital;
      $objComponenteDigitalDTO->setStrNome($objComponenteDigital->nome);

      $strHashConteudo = static::getHashFromMetaDados($objComponenteDigital->hash);

      $objComponenteDigitalDTO->setStrHashConteudo($strHashConteudo);
      $objComponenteDigitalDTO->setStrAlgoritmoHash(self::ALGORITMO_HASH_DOCUMENTO);
      $objComponenteDigitalDTO->setStrTipoConteudo($objComponenteDigital->tipoDeConteudo);
      $objComponenteDigitalDTO->setStrMimeType($objComponenteDigital->mimeType);
      $objComponenteDigitalDTO->setStrDadosComplementares($objComponenteDigital->dadosComplementaresDoTipoDeArquivo);

      //Registrar componente digital necessita ser enviado pelo tr�mite espef�fico      //TODO: Teste $parObjComponentesDigitaisSolicitados aqui
      if(isset($parObjComponentesDigitaisSolicitados)){
        $arrObjItensSolicitados = is_array($parObjComponentesDigitaisSolicitados->processo) ? $parObjComponentesDigitaisSolicitados->processo : array($parObjComponentesDigitaisSolicitados->processo);

        foreach ($arrObjItensSolicitados as $objItemSolicitado) {
            if(!is_null($objItemSolicitado)){
                $objItemSolicitado->hash = is_array($objItemSolicitado->hash) ? $objItemSolicitado->hash : array($objItemSolicitado->hash);

                if($objItemSolicitado->protocolo == $objComponenteDigitalDTO->getStrProtocolo() && in_array($strHashConteudo, $objItemSolicitado->hash) && !$objDocumento->retirado) {
                  $objComponenteDigitalDTO->setStrSinEnviar("S");
                }
            }
        }
      }

      //TODO: Avaliar dados do tamanho do documento em bytes salvo na base de dados
      $objComponenteDigitalDTO->setNumTamanho($objComponenteDigital->tamanhoEmBytes);
      $objComponenteDigitalDTO->setNumIdAnexo($objComponenteDigital->idAnexo);

      $arrObjComponenteDigitalDTO[] = $objComponenteDigitalDTO;
    }

    //Chamada recursiva sobre os documentos dos processos apensados
    if(isset($parObjProcesso->processoApensado) && count($parObjProcesso->processoApensado)) {
      foreach ($parObjProcesso->processoApensado as $objProcessoApensado) {
        $arrObj = $this->montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $objProcessoApensado, $parObjComponentesDigitaisSolicitados);
        $arrObjComponenteDigitalDTO = array_merge($arrObjComponenteDigitalDTO, $arrObj);
      }
    }

    return $arrObjComponenteDigitalDTO;
  }


  public function receberComponenteDigital($parNumIdentificacaoTramite, $parStrHashComponenteDigital, $parStrProtocolo)
  {
    try
    {
      $parametros = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->IDT = $parNumIdentificacaoTramite;
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->protocolo = $parStrProtocolo;
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->hashDoComponenteDigital = $parStrHashComponenteDigital;

      return $this->getObjPenWs()->receberComponenteDigital($parametros);

    } catch (\SoapFault $fault) {
        $mensagem = $this->tratarFalhaWebService($fault);
        //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
        //rlacionado ao a renderiza��o de mensagens de erro na barra de progresso
        error_log($mensagem);
        throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  public function consultarTramites($parNumIdTramite = null, $parNumeroRegistro = null, $parNumeroUnidadeRemetente = null, $parNumeroUnidadeDestino = null, $parProtocolo = null, $parNumeroRepositorioEstruturas = null)
  {
    try
    {
      $arrObjTramite = array();
      $parametro = new stdClass();
      $parametro->filtroDeConsultaDeTramites = new stdClass();
      $parametro->filtroDeConsultaDeTramites->IDT = $parNumIdTramite;

      if(!is_null($parNumeroRegistro)){
        $parametro->filtroDeConsultaDeTramites->NRE = $parNumeroRegistro;
      }

      if(!is_null($parNumeroUnidadeRemetente) && !is_null($parNumeroRepositorioEstruturas)){
          $parametro->filtroDeConsultaDeTramites->remetente = new stdClass();
          $parametro->filtroDeConsultaDeTramites->remetente->identificacaoDoRepositorioDeEstruturas = $parNumeroRepositorioEstruturas;
          $parametro->filtroDeConsultaDeTramites->remetente->numeroDeIdentificacaoDaEstrutura = $parNumeroUnidadeRemetente;
      }

      if(!is_null($parNumeroUnidadeDestino) && !is_null($parNumeroRepositorioEstruturas)){
          $parametro->filtroDeConsultaDeTramites->destinatario = new stdClass();
          $parametro->filtroDeConsultaDeTramites->destinatario->identificacaoDoRepositorioDeEstruturas = $parNumeroRepositorioEstruturas;
          $parametro->filtroDeConsultaDeTramites->destinatario->numeroDeIdentificacaoDaEstrutura = $parNumeroUnidadeDestino;
      }

      if(!is_null($parProtocolo)){
          $parametro->filtroDeConsultaDeTramites->protocolo = $parProtocolo;
      }

      $objTramitesEncontrados = $this->getObjPenWs()->consultarTramites($parametro);

      if(isset($objTramitesEncontrados->tramitesEncontrados)) {

        $arrObjTramite = $objTramitesEncontrados->tramitesEncontrados->tramite;
        if(!is_array($arrObjTramite)) {
          $arrObjTramite = array($objTramitesEncontrados->tramitesEncontrados->tramite);
        }
      }

      return $arrObjTramite;

    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  public function consultarTramitesProtocolo($parProtocoloFormatado)
  {
    try
    {
      $arrObjTramite = array();
      $parametro = new stdClass();
      $parametro->filtroDeConsultaDeTramites = new stdClass();
      $parametro->filtroDeConsultaDeTramites->protocolo = $parProtocoloFormatado;

      $objTramitesEncontrados = $this->getObjPenWs()->consultarTramites($parametro);

      if(isset($objTramitesEncontrados->tramitesEncontrados)) {

        $arrObjTramite = $objTramitesEncontrados->tramitesEncontrados->tramite;
        if(!is_array($arrObjTramite)) {
          $arrObjTramite = array($objTramitesEncontrados->tramitesEncontrados->tramite);
        }
      }

      return $arrObjTramite;

    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  public function cienciaRecusa($parNumIdTramite)
  {
    try
    {
      $parametro = new stdClass();
      $parametro->IDT = $parNumIdTramite;

      return $this->getObjPenWs()->cienciaRecusa($parametro);

    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

  /**
   * Retorna o estado atual do procedimento no api-pen
   *
   * @param integer $dblIdProcedimento
   * @param integer $numIdRepositorio
   * @param integer $numIdEstrutura
   * @return integer
   */
  public function consultarEstadoProcedimento($strProtocoloFormatado = '', $numIdRepositorio = null, $numIdEstrutura = null) {

        $objBD = new GenericoBD($this->inicializarObjInfraIBanco());

        $objProtocoloDTO = new ProtocoloDTO();
        $objProtocoloDTO->setStrProtocoloFormatado($strProtocoloFormatado);
        $objProtocoloDTO->setNumMaxRegistrosRetorno(1);
        $objProtocoloDTO->retDblIdProtocolo();
        $objProtocoloDTO->retStrProtocoloFormatado();
        $objProtocoloDTO->retStrStaEstado();

        $objProtocoloDTO = $objBD->consultar($objProtocoloDTO);

        if (empty($objProtocoloDTO)) {
            throw new InfraException(utf8_encode(sprintf('Nenhum procedimento foi encontrado com o id %s', $strProtocoloFormatado)));
        }

        if (!in_array($objProtocoloDTO->getStrStaEstado(), array(ProtocoloRN::$TE_EM_PROCESSAMENTO, ProtocoloRn::$TE_BLOQUEADO))) {
            throw new InfraException(utf8_encode('O processo n�o esta com o estado com "Em Processamento" ou "Bloqueado"'));
        }

        $objTramiteDTO = new TramiteDTO();
        $objTramiteDTO->setNumIdProcedimento($objProtocoloDTO->retDblIdProtocolo());
        $objTramiteDTO->setOrd('Registro', InfraDTO::$TIPO_ORDENACAO_DESC);
        $objTramiteDTO->setNumMaxRegistrosRetorno(1);
        $objTramiteDTO->retNumIdTramite();

        $objTramiteBD = new TramiteBD($this->getObjInfraIBanco());
        $arrObjTramiteDTO = $objTramiteBD->listar($objTramiteDTO);

        if(!$arrObjTramiteDTO){
            throw new InfraException('Tr�mite n�o encontrado');
        }

        $objTramiteDTO = $arrObjTramiteDTO[0];

        $objFiltro = new stdClass();
        $objFiltro->filtroDeConsultaDeTramites = new stdClass();
        $objFiltro->filtroDeConsultaDeTramites->IDT = $objTramiteDTO->getNumIdTramite();

        $objResultado = $this->getObjPenWs()->consultarTramites($objFiltro);

        $objTramitesEncontrados = $objResultado->tramitesEncontrados;

        if (empty($objTramitesEncontrados) || !isset($objTramitesEncontrados->tramite)) {
            throw new InfraException(utf8_encode(sprintf('Nenhum tramite foi encontrado para o procedimento %s', $strProtocoloFormatado)));
        }

        if(!is_array($objTramitesEncontrados->tramite)){
            $objTramitesEncontrados->tramite = array($objTramitesEncontrados->tramite);
        }

        $arrObjTramite = (array) $objTramitesEncontrados->tramite;

        $objTramite = array_pop($arrObjTramite);

        if (empty($numIdRepositorio)) {
            $objPenParametroRN = new PenParametroRN();
            $numIdRepositorio = $objPenParametroRN->getParametro('PEN_ID_REPOSITORIO_ORIGEM');

        }

        if (empty($numIdEstrutura)) {

            $objPenUnidadeDTO = new PenUnidadeDTO();
            $objPenUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
            $objPenUnidadeDTO->retNumIdUnidadeRH();

            $objPenUnidadeDTO = $objBD->consultar($objPenUnidadeDTO);

            if (empty($objPenUnidadeDTO)) {
                throw new InfraException(utf8_encode('N�mero da Unidade RH n�o foi encontrado'));
            }

            $numIdEstrutura = $objPenUnidadeDTO->getNumIdUnidadeRH();
        }

        if ($objTramite->remetente->numeroDeIdentificacaoDaEstrutura != $numIdEstrutura ||
            $objTramite->remetente->identificacaoDoRepositorioDeEstruturas != $numIdRepositorio) {

            throw new InfraException(utf8_encode('O �ltimo tr�mite desse processo n�o pertence a esse �rg�o'));
        }

        switch ($objTramite->situacaoAtual) {

            case static::$STA_SITUACAO_TRAMITE_RECIBO_ENVIADO_DESTINATARIO:
                // @todo: caso command-line informar o procedimento que ser� executado
                $objPenTramiteProcessadoRN = new PenTramiteProcessadoRN(PenTramiteProcessadoRN::STR_TIPO_RECIBO);

                if(!$objPenTramiteProcessadoRN->isProcedimentoRecebido($objTramite->IDT)){

                    $objReceberReciboTramiteRN = new ReceberReciboTramiteRN();
                    $objReceberReciboTramiteRN->receberReciboDeTramite($objTramite->IDT);
                }
                break;

            case static::$STA_SITUACAO_TRAMITE_RECIBO_RECEBIDO_REMETENTE:
                throw new InfraException(utf8_encode('O tr�mite externo deste processo j� foi conclu�do'));
                break;

            default:
                $objAtividadeDTO = new AtividadeDTO();
                $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProtocolo());
                $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
                $objAtividadeDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
                $objAtividadeDTO->setNumIdTarefa(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_ABORTADO);
                $objAtividadeDTO->setArrObjAtributoAndamentoDTO(array());

                $objAtividadeRN = new AtividadeRN();
                $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

                $objProtocoloDTO->setStrStaEstado(ProtocoloRN::$TE_NORMAL);
                $objBD->alterar($objProtocoloDTO);

                if($objTramite->situacaoAtual == static::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO && $objTramite->situacaoAtual == static::$STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO){
                    $this->cancelarTramite($objTramite->IDT);
                }

                return PenConsoleRN::format(sprintf('Processo %s foi atualizado com sucesso', $objProtocoloDTO->getStrProtocoloFormatado()), 'blue');
        }
    }

  public function enviarReciboDeTramite($parNumIdTramite, $parDthRecebimento, $parStrReciboTramite)
  {
    try
    {
      $strHashAssinatura = null;
      $objPrivatekey = openssl_pkey_get_private("file://".$this->strLocalCert, $this->strLocalCertPassword);

      if ($objPrivatekey === FALSE) {
        throw new InfraException("Erro ao obter chave privada do certificado digital.");
      }


      openssl_sign($parStrReciboTramite, $strHashAssinatura, $objPrivatekey, 'sha256');
      $strHashDaAssinaturaBase64 = base64_encode($strHashAssinatura);

      $parametro = new stdClass();
      $parametro->dadosDoReciboDeTramite = new stdClass();
      $parametro->dadosDoReciboDeTramite->IDT = $parNumIdTramite;
      $parametro->dadosDoReciboDeTramite->dataDeRecebimento = $parDthRecebimento;
      $parametro->dadosDoReciboDeTramite->hashDaAssinatura = $strHashDaAssinaturaBase64;


      $this->getObjPenWs()->enviarReciboDeTramite($parametro);

      return $strHashDaAssinaturaBase64;

    } catch (\SoapFault $fault) {

            $strMensagem  = '[ SOAP Request ]'.PHP_EOL;
            $strMensagem .= 'Method: enviarReciboDeTramite (FAIL)'.PHP_EOL;
            $strMensagem .= 'Request: '.$this->getObjPenWs()->__getLastRequest().PHP_EOL;
            $strMensagem .= 'Response: '.$this->getObjPenWs()->__getLastResponse().PHP_EOL;

            file_put_contents('/tmp/pen.log', $strMensagem.PHP_EOL, FILE_APPEND);

      if(isset($objPrivatekey)){
        openssl_free_key($objPrivatekey);
      }

      $mensagem = $this->tratarFalhaWebService($fault);

        //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
        //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      if(isset($objPrivatekey)){
        openssl_free_key($objPrivatekey);
      }

      throw new InfraException("Error Processing Request", $e);
    }
  }

  public function receberReciboDeTramite($parNumIdTramite)
  {
    try
    {
      $parametro = new stdClass();
      $parametro->IDT = $parNumIdTramite;

      $resultado = $this->getObjPenWs()->receberReciboDeTramite($parametro);

      return $resultado;
    }
    catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }
  }

    /**
     * Retorna um objeto DTO do recibo de envio do processo ao barramento
     *
     * @param int $parNumIdTramite
     * @return ReciboTramiteEnviadoDTO
     */
    public function receberReciboDeEnvio($parNumIdTramite) {

        try {
            $parametro = new stdClass();
            $parametro->IDT = $parNumIdTramite;

            $resultado = $this->getObjPenWs()->receberReciboDeEnvio($parametro);

            return $resultado->conteudoDoReciboDeEnvio;
        }
        catch (\SoapFault $fault) {
            $mensagem = $this->tratarFalhaWebService($fault);
            throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
        }
        catch (\Exception $e) {
            throw new InfraException("Error Processing Request", $e);
        }
        throw new InfraException("Error Processing Request", $e);
    }

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function converterOperacaoDTO($objOperacaoPEN)
  {
    if(!isset($objOperacaoPEN)) {
      throw new InfraException('Par�metro $objOperacaoPEN n�o informado.');
    }

    $objOperacaoDTO = new OperacaoDTO();
    $objOperacaoDTO->setStrCodigo(utf8_decode($objOperacaoPEN->codigo));
    $objOperacaoDTO->setStrComplemento(utf8_decode($objOperacaoPEN->complemento));
    $objOperacaoDTO->setDthOperacao($this->converterDataSEI($objOperacaoPEN->dataHora));

    $strIdPessoa =  ($objOperacaoPEN->pessoa->numeroDeIdentificacao) ?: null;
    $objOperacaoDTO->setStrIdentificacaoPessoaOrigem(utf8_decode($strIdPessoa));

    $strNomePessoa =  ($objOperacaoPEN->pessoa->nome) ?: null;
    $objOperacaoDTO->setStrNomePessoaOrigem(utf8_decode($strNomePessoa));

    switch ($objOperacaoPEN->codigo) {
      case "01": $objOperacaoDTO->setStrNome("Registro"); break;
      case "02": $objOperacaoDTO->setStrNome("Envio de documento avulso/processo"); break;
      case "03": $objOperacaoDTO->setStrNome("Cancelamento/exclus�o ou envio de documento"); break;
      case "04": $objOperacaoDTO->setStrNome("Recebimento de documento"); break;
      case "05": $objOperacaoDTO->setStrNome("Autua��o"); break;
      case "06": $objOperacaoDTO->setStrNome("Juntada por anexa��o"); break;
      case "07": $objOperacaoDTO->setStrNome("Juntada por apensa��o"); break;
      case "08": $objOperacaoDTO->setStrNome("Desapensa��o"); break;
      case "09": $objOperacaoDTO->setStrNome("Arquivamento"); break;
      case "10": $objOperacaoDTO->setStrNome("Arquivamento no Arquivo Nacional"); break;
      case "11": $objOperacaoDTO->setStrNome("Elimina��o"); break;
      case "12": $objOperacaoDTO->setStrNome("Sinistro"); break;
      case "13": $objOperacaoDTO->setStrNome("Reconstitui��o de processo"); break;
      case "14": $objOperacaoDTO->setStrNome("Desarquivamento"); break;
      case "15": $objOperacaoDTO->setStrNome("Desmembramento"); break;
      case "16": $objOperacaoDTO->setStrNome("Desentranhamento"); break;
      case "17": $objOperacaoDTO->setStrNome("Encerramento/abertura de volume no processo"); break;
      case "18": $objOperacaoDTO->setStrNome("Registro de extravio"); break;
      default:   $objOperacaoDTO->setStrNome("Registro"); break;
    }

    return $objOperacaoDTO;
  }

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function obterCodigoOperacaoPENMapeado($numIdTarefa)
  {
    $strCodigoOperacao = self::$OP_OPERACAO_REGISTRO;

    if(isset($numIdTarefa) && $numIdTarefa != 0) {
      $objRelTarefaOperacaoDTO = new RelTarefaOperacaoDTO();
      $objRelTarefaOperacaoDTO->retStrCodigoOperacao();
      $objRelTarefaOperacaoDTO->setNumIdTarefa($numIdTarefa);


      $objRelTarefaOperacaoBD = new RelTarefaOperacaoBD(BancoSEI::getInstance());
      $objRelTarefaOperacaoDTO = $objRelTarefaOperacaoBD->consultar($objRelTarefaOperacaoDTO);

      if($objRelTarefaOperacaoDTO != null) {
        $strCodigoOperacao = $objRelTarefaOperacaoDTO->getStrCodigoOperacao();
      }
    }

    return $strCodigoOperacao;
  }

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function obterIdTarefaSEIMapeado($strCodigoOperacao)
  {
    return self::$TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_EXTERNO;
  }


  /**
   * Cancela um tramite externo de um procedimento para outra unidade, gera
   * falha caso a unidade de destino j� tenha come�ado a receber o procedimento.
   *
   * @param type $idTramite
   * @param type $idProtocolo
   * @throws Exception|InfraException
   * @return null
   */
  public function cancelarTramite($idTramite) {

      //@TODOJOIN: Adicionar a seguinte linha abaixo dessa : $parametros->filtroDeConsultaDeTramites = new stdClass()
      //Faz a consulta do tramite
      $paramConsultaTramite = new stdClass();
      $paramConsultaTramite->filtroDeConsultaDeTramites = new stdClass();
      $paramConsultaTramite->filtroDeConsultaDeTramites->IDT = $idTramite;
      $dadosTramite = $this->getObjPenWs()->consultarTramites($paramConsultaTramite);

      //Requisita o cancelamento
      $parametros = new stdClass();
      $parametros->IDT = $idTramite;

      try{
          $this->getObjPenWs()->cancelarEnvioDeTramite($parametros);
      }
      catch(\SoapFault $e) {
          throw new InfraException($e->getMessage(), null, $e);
      }
  }

  /**
   * M�todo que faz a recusa de um tr�mite
   *
   * @param integer $idTramite
   * @param string $justificativa
   * @param integer $motivo
   * @return mixed
   * @throws InfraException
   */
  public function recusarTramite($idTramite, $justificativa, $motivo) {
        try {

            //@TODOJOIN: Adicionar a seguinte linha abaixo dessa : $parametros->recusaDeTramite = new stdClass()
            $parametros = new stdClass();
            $parametros->recusaDeTramite = new stdClass();
            $parametros->recusaDeTramite->IDT = $idTramite;
            $parametros->recusaDeTramite->justificativa = utf8_encode($justificativa);
            $parametros->recusaDeTramite->motivo = $motivo;

            $resultado = $this->getObjPenWs()->recusarTramite($parametros);

        } catch (SoapFault $fault) {

            $mensagem = $this->tratarFalhaWebService($fault);
            throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function cadastrarTramitePendente($numIdentificacaoTramite, $idAtividadeExpedicao) {
        try {

            $tramitePendenteDTO = new TramitePendenteDTO();
            $tramitePendenteDTO->setNumIdTramite($numIdentificacaoTramite);
            $tramitePendenteDTO->setNumIdAtividade($idAtividadeExpedicao);

            $tramitePendenteBD = new TramitePendenteBD($this->getObjInfraIBanco());
            $tramitePendenteBD->cadastrar($tramitePendenteDTO);

        } catch (\InfraException $ex) {
            throw new InfraException($ex->getStrDescricao());
        } catch (\Exception $ex) {
            throw new InfraException($ex->getMessage());
        }
    }

    public function isDisponivelCancelarTramite($strProtocolo = ''){

        //Obtem o id_rh que representa a unidade no barramento
        $objPenParametroRN = new PenParametroRN();
        $numIdRespositorio = $objPenParametroRN->getParametro('PEN_ID_REPOSITORIO_ORIGEM');

        //Obtem os dados da unidade
        $objPenUnidadeDTO = new PenUnidadeDTO();
        $objPenUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objPenUnidadeDTO->retNumIdUnidadeRH();

        $objGenericoBD = new GenericoBD($this->inicializarObjInfraIBanco());
        $objPenUnidadeDTO = $objGenericoBD->consultar($objPenUnidadeDTO);

        //Obtem os dados do �ltimo tr�mite desse processo no barramento
        $objProtocoloDTO = new ProtocoloDTO();
        $objProtocoloDTO->setStrProtocoloFormatado($strProtocolo);
        $objProtocoloDTO->retDblIdProtocolo();

        $objProtocoloRN = new ProtocoloRN();
        $objProtocoloDTO = $objProtocoloRN->consultarRN0186($objProtocoloDTO);

        $objTramiteDTO = new TramiteDTO();
        $objTramiteDTO->setNumIdProcedimento($objProtocoloDTO->retDblIdProtocolo());
        $objTramiteDTO->setOrd('Registro', InfraDTO::$TIPO_ORDENACAO_DESC);
        $objTramiteDTO->setNumMaxRegistrosRetorno(1);
        $objTramiteDTO->retNumIdTramite();

        $objTramiteBD = new TramiteBD($this->getObjInfraIBanco());
        $arrObjTramiteDTO = $objTramiteBD->listar($objTramiteDTO);

        if(!$arrObjTramiteDTO){
            return false;
        }

        $objTramiteDTO = $arrObjTramiteDTO[0];

        try {

            $parametro = (object)array(
                'filtroDeConsultaDeTramites' => (object)array(
                    'IDT' => $objTramiteDTO->getNumIdTramite(),
                    'remetente' => (object)array(
                        'identificacaoDoRepositorioDeEstruturas' => $numIdRespositorio,
                        'numeroDeIdentificacaoDaEstrutura' => $objPenUnidadeDTO->getNumIdUnidadeRH()
                    ),
                )
            );


            $objMeta = $this->getObjPenWs()->consultarTramites($parametro);


            if($objMeta->tramitesEncontrados) {

                $arrObjMetaTramite = !is_array($objMeta->tramitesEncontrados->tramite) ? array($objMeta->tramitesEncontrados->tramite) : $objMeta->tramitesEncontrados->tramite;

                $objMetaTramite = $arrObjMetaTramite[0];

                switch($objMetaTramite->situacaoAtual){

                    case static::$STA_SITUACAO_TRAMITE_INICIADO:
                    case static::$STA_SITUACAO_TRAMITE_COMPONENTES_ENVIADOS_REMETENTE:
                    case static::$STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO:
                    case static::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO:
                        return true;
                        break;

                }
            }

            return false;
        }
        catch(SoapFault $e) {
            return false;
        }
        catch(Exception $e) {
            return false;
        }
    }

    public function consultarHipotesesLegais() {
        try{
            $hipoteses = $this->getObjPenWs()->consultarHipotesesLegais();
            if (empty($hipoteses)) {
                return [];
            }
            return $hipoteses;

        } catch(Exception $e){
            throw new InfraException("Erro durante obten��o da resposta das hip�teses legais", $e);
        }
    }

    protected function contarConectado(ProcessoEletronicoDTO $objProcessoEletronicoDTO){
      try {
        $objProcessoEletronicoBD = new ProcessoEletronicoBD($this->getObjInfraIBanco());
        return $objProcessoEletronicoBD->contar($objProcessoEletronicoDTO);
      }catch(Exception $e){
        throw new InfraException('Erro contando Processos Externos.',$e);
      }
    }
}


