<?php
require_once dirname(__FILE__) . '/../../../SEI.php';

//TODO: Implementar valida��o sobre tamanho do documento a ser recebido (Par�metros SEI)

class ReceberProcedimentoRN extends InfraRN
{
  const STR_APENSACAO_PROCEDIMENTOS = 'Relacionamento representando a apensa��o de processos recebidos externamente';

  private $objProcessoEletronicoRN;
  private $objInfraParametro;
  private $objProcedimentoAndamentoRN;
  private $documentosRetirados = array();

  public function __construct()
  {
    parent::__construct();

    $this->objInfraParametro = new InfraParametro(BancoSEI::getInstance());
    $this->objProcessoEletronicoRN = new ProcessoEletronicoRN();
    $this->objProcedimentoAndamentoRN = new ProcedimentoAndamentoRN();
  }

  protected function inicializarObjInfraIBanco()
  {
    return BancoSEI::getInstance();
  }

  protected function listarPendenciasConectado()
  {
    $arrObjPendencias = $this->objProcessoEletronicoRN->listarPendencias(true);
    return $arrObjPendencias;
  }

    public function fecharProcedimentoEmOutraUnidades(ProcedimentoDTO $objProcedimentoDTO, $parObjMetadadosProcedimento){
        
        $objPenUnidadeDTO = new PenUnidadeDTO();
        $objPenUnidadeDTO->setNumIdUnidadeRH($parObjMetadadosProcedimento->metadados->destinatario->numeroDeIdentificacaoDaEstrutura);
        $objPenUnidadeDTO->retNumIdUnidade();
      
        $objGenericoBD = new GenericoBD($this->inicializarObjInfraIBanco());
        $objPenUnidadeDTO = $objGenericoBD->consultar($objPenUnidadeDTO);

        if(empty($objPenUnidadeDTO)) {
            return false;
        }

        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->setDistinct(true);
        $objAtividadeDTO->setNumIdUnidade($objPenUnidadeDTO->getNumIdUnidade(), InfraDTO::$OPER_DIFERENTE);
        $objAtividadeDTO->setDblIdProtocolo($objProcedimentoDTO->getDblIdProcedimento());
        $objAtividadeDTO->setDthConclusao(null);
        $objAtividadeDTO->setOrdStrSiglaUnidade(InfraDTO::$TIPO_ORDENACAO_ASC);
        $objAtividadeDTO->setOrdStrSiglaUsuarioAtribuicao(InfraDTO::$TIPO_ORDENACAO_DESC);
        $objAtividadeDTO->retStrSiglaUnidade();
        $objAtividadeDTO->retStrDescricaoUnidade();
        $objAtividadeDTO->retNumIdUsuarioAtribuicao();
        $objAtividadeDTO->retStrSiglaUsuarioAtribuicao();
        $objAtividadeDTO->retStrNomeUsuarioAtribuicao();
        $objAtividadeDTO->retNumIdUnidade();

        $objAtividadeRN = new AtividadeRN();
        $arrObjAtividadeDTO = (array)$objAtividadeRN->listarRN0036($objAtividadeDTO);
       
        $objInfraSessao = SessaoSEI::getInstance();
        $numIdUnidade = $objInfraSessao->getNumIdUnidadeAtual();
        
        foreach($arrObjAtividadeDTO as $objAtividadeDTO) {

            $objInfraSessao->setNumIdUnidadeAtual($objAtividadeDTO->getNumIdUnidade());
            $objInfraSessao->trocarUnidadeAtual();
            
            $objProcedimentoRN = new ProcedimentoRN();
            $objProcedimentoRN->concluir(array($objProcedimentoDTO));
        }
        $objInfraSessao->setNumIdUnidadeAtual($numIdUnidade);
        $objInfraSessao->trocarUnidadeAtual();
    }
  
    // TODO: Adicionar comandos de debug. Vide SeiWs.php gerarProcedimento
  protected function receberProcedimentoControlado($parNumIdentificacaoTramite)
  {     
      
      error_log(__METHOD__.'('.$parNumIdentificacaoTramite.')');
      
    if (!isset($parNumIdentificacaoTramite)) {
      throw new InfraException('Par�metro $parNumIdentificacaoTramite n�o informado.');
    }

    //TODO: Urgente: Verificar o status do tr�mite e verificar se ele j� foi salvo na base de dados
    $objMetadadosProcedimento = $this->objProcessoEletronicoRN->solicitarMetadados($parNumIdentificacaoTramite);

    if (isset($objMetadadosProcedimento)) {

      $strNumeroRegistro = $objMetadadosProcedimento->metadados->NRE;
      $objProcesso = $objMetadadosProcedimento->metadados->processo;

      //Verifica se processo j� foi registrado para esse tr�mite
      //TODO: Avaliar tamb�m processos apensados
      if($this->tramiteRegistrado($strNumeroRegistro, $parNumIdentificacaoTramite)) {
        return ;
      }
      
      // Valida��o dos dados do processo recebido
      $objInfraException = new InfraException();
      $this->validarDadosDestinatario($objInfraException, $objMetadadosProcedimento);
      $objInfraException->lancarValidacoes();
       
      #############################INICIA O RECEBIMENTO DOS COMPONENTES DIGITAIS US010################################################
      $arrObjTramite = $this->objProcessoEletronicoRN->consultarTramites($parNumIdentificacaoTramite);
      $objTramite = $arrObjTramite[0];
      
      //Obt�m lista de componentes digitais que precisam ser obtidos
      if(!is_array($objTramite->componenteDigitalPendenteDeRecebimento)){
        $objTramite->componenteDigitalPendenteDeRecebimento = array($objTramite->componenteDigitalPendenteDeRecebimento);
      }

      //Faz a valida��o do tamanho e esp�cie dos componentes digitais 
      $this->validarComponentesDigitais($objProcesso, $parNumIdentificacaoTramite);
      
      //Faz a valida��o da extens�o dos componentes digitais a serem recebidos
      $this->validarExtensaoComponentesDigitais($parNumIdentificacaoTramite, $objProcesso);
      
      //Faz a valida��o das permiss�es de leitura e escrita 
      $this->verificarPermissoesDiretorios($parNumIdentificacaoTramite);
      
      $arrStrNomeDocumento = $this->listarMetaDadosComponentesDigitais($objProcesso);
      
      //Instancia a RN que faz o recebimento dos componentes digitais
      $receberComponenteDigitalRN = new ReceberComponenteDigitalRN();

      //Cria o array que receber� os anexos ap�s os arquivos f�sicos serem salvos
      $arrAnexosComponentes = array();
      
      //Cria o array com a lista de hash
      $arrayHash = array();
                
      //Percorre os componentes que precisam ser recebidos
      foreach($objTramite->componenteDigitalPendenteDeRecebimento as $componentePendente){
          
          if(!is_null($componentePendente)){
              
                //Adiciona o hash do componente digital ao array
                $arrayHash[] = $componentePendente;
                
                //Obter os dados do componente digital
                $objComponenteDigital = $this->objProcessoEletronicoRN->receberComponenteDigital($parNumIdentificacaoTramite, $componentePendente, $objTramite->protocolo);
                //Copia o componente para a pasta tempor�ria
                $arrAnexosComponentes[$componentePendente] = $receberComponenteDigitalRN->copiarComponenteDigitalPastaTemporaria($objComponenteDigital);
                
                //Valida a integridade do hash
                $receberComponenteDigitalRN->validarIntegridadeDoComponenteDigital($arrAnexosComponentes[$componentePendente], $componentePendente, $parNumIdentificacaoTramite);
          }
      }
      
      if(count($arrAnexosComponentes) > 0){
          
            $receberComponenteDigitalRN->setArrAnexos($arrAnexosComponentes);
      }
      #############################TERMINA O RECEBIMENTO DOS COMPONENTES DIGITAIS US010################################################
      
      $arrObjTramite = $this->objProcessoEletronicoRN->consultarTramites($parNumIdentificacaoTramite);
      $objTramite = $arrObjTramite[0];
      
      //Verifica se o tr�mite est� recusado
      if($objTramite->situacaoAtual == ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_RECUSADO) {
            return;
      }

    $objProcedimentoDTO = $this->registrarProcesso($strNumeroRegistro, $parNumIdentificacaoTramite, $objProcesso, $objMetadadosProcedimento);

    
    foreach($this->documentosRetirados as $documentoCancelado){
        //Instancia o DTO do protocolo
        $objProtocoloCanceladoDTO = new ProtocoloDTO();
        $objProtocoloCanceladoDTO->setDblIdProtocolo($documentoCancelado);
        $objProtocoloCanceladoDTO->setStrMotivoCancelamento('Cancelado pelo remetente');


        $objProtocoloRN = new PenProtocoloRN();
        $objProtocoloRN->cancelar($objProtocoloCanceladoDTO);
    }
    
    
    // @join_tec US008.08 (#23092)
      $this->objProcedimentoAndamentoRN->setOpts($objProcedimentoDTO->getDblIdProcedimento(), $parNumIdentificacaoTramite, ProcessoEletronicoRN::obterIdTarefaModulo(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_RECEBIDO));
      $this->objProcedimentoAndamentoRN->cadastrar('Obtendo metadados do processo', 'S');  

      //Verificar se procedimento j� existia na base de dados do sistema
      //$dblIdProcedimento = $this->consultarProcedimentoExistente($strNumeroRegistro, $strProtocolo);

      //if(isset($dblIdProcedimento)){
      //TODO: Tratar situa��o em que o processo (NUP) j� existia na base do sistema mas n�o havia nenhum NRE registrado para ele
      //  $objProcedimentoDTO = $this->atualizarProcedimento($dblIdProcedimento, $objMetadadosProcedimento, $objProcesso);                
      //}
      //else {            
                //TODO: Gerar Procedimento com status BLOQUEADO, aguardando o recebimento dos componentes digitais
      //  $objProcedimentoDTO = $this->gerarProcedimento($objMetadadosProcedimento, $objProcesso);
      //}

      //TODO: Fazer o envio de cada um dos procedimentos apensados (Processo principal e seus apensados, caso exista)
      //...        
      //TODO: Parei aqui!!! Recebimento de processos apensados
    
      $objProcessoEletronicoDTO = $this->objProcessoEletronicoRN->cadastrarTramiteDeProcesso($objProcedimentoDTO->getDblIdProcedimento(), 
        $strNumeroRegistro, $parNumIdentificacaoTramite, null, $objProcesso);
      

                  
      //TODO: Passar implementa��o para outra classe de neg�cio
      //Verifica se o tramite se encontra na situa��o correta 
      $arrObjTramite = $this->objProcessoEletronicoRN->consultarTramites($parNumIdentificacaoTramite);
      if(!isset($arrObjTramite) || count($arrObjTramite) != 1) {
        throw new InfraException("Tr�mite n�o pode ser localizado pelo identificado $parNumIdentificacaoTramite.");
      }


      $objTramite = $arrObjTramite[0];
      

      if($objTramite->situacaoAtual != ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO) {
        return;
      }
      

            
    //  throw new InfraException("COMPONENTES DIGITAIS A SEREM ANEXADOS: ".var_export($arrayHash, true));
      if(count($arrayHash) > 0){
          
            //Obter dados dos componetes digitais            
            $objComponenteDigitalDTO = new ComponenteDigitalDTO();
            $objComponenteDigitalDTO->setStrNumeroRegistro($strNumeroRegistro);
            $objComponenteDigitalDTO->setNumIdTramite($parNumIdentificacaoTramite);
            $objComponenteDigitalDTO->setStrHashConteudo($arrayHash, InfraDTO::$OPER_IN);
            $objComponenteDigitalDTO->setOrdNumOrdem(InfraDTO::$TIPO_ORDENACAO_ASC);
            $objComponenteDigitalDTO->retDblIdDocumento();
            $objComponenteDigitalDTO->retNumTicketEnvioComponentes();
       //     $objComponenteDigitalDTO->retStrConteudoAssinaturaDocumento();
            $objComponenteDigitalDTO->retStrProtocoloDocumentoFormatado();
            $objComponenteDigitalDTO->retStrHashConteudo();
            $objComponenteDigitalDTO->retStrProtocolo();
            $objComponenteDigitalDTO->retStrNumeroRegistro();
            $objComponenteDigitalDTO->retNumIdTramite();
            $objComponenteDigitalDTO->retStrNome();

            $objComponenteDigitalBD = new ComponenteDigitalBD($this->getObjInfraIBanco());
            $arrObjComponentesDigitaisDTO = $objComponenteDigitalBD->listar($objComponenteDigitalDTO);
                  
          //  throw new InfraException('Componentes encontrados: '.var_export($arrObjComponentesDigitaisDTO, true));
            
          if ($objComponenteDigitalBD->contar($objComponenteDigitalDTO) > 0) {
              
                  $objReceberComponenteDigitalRN = $receberComponenteDigitalRN;
                  
                  foreach($arrObjComponentesDigitaisDTO as $objComponenteDigitalDTOEnviado) {
                      
                        $strHash = $objComponenteDigitalDTOEnviado->getStrHashConteudo();                        
                        $strNomeDocumento = (array_key_exists($strHash, $arrStrNomeDocumento)) ? $arrStrNomeDocumento[$strHash]['especieNome'] : '[Desconhecido]';
                      
                        $objReceberComponenteDigitalRN->receberComponenteDigital($objComponenteDigitalDTOEnviado);

                        // @join_tec US008.09 (#23092)
                        $this->objProcedimentoAndamentoRN->cadastrar(sprintf('Recebendo %s %s', $strNomeDocumento, $objComponenteDigitalDTOEnviado->getStrProtocoloDocumentoFormatado()), 'S');
                  }
                  // @join_tec US008.10 (#23092)
                $this->objProcedimentoAndamentoRN->cadastrar('Todos os componentes digitais foram recebidos', 'S');

            }else{
              $this->objProcedimentoAndamentoRN->cadastrar('Nenhum componente digital para receber', 'S');
            }                        
          }
    }
    
    //$this->fecharProcedimentoEmOutraUnidades($objProcedimentoDTO, $objMetadadosProcedimento);

   $objEnviarReciboTramiteRN = new EnviarReciboTramiteRN();
   $objEnviarReciboTramiteRN->enviarReciboTramiteProcesso($parNumIdentificacaoTramite, $arrayHash);
   
   $objPenTramiteProcessadoRN = new PenTramiteProcessadoRN(PenTramiteProcessadoRN::STR_TIPO_PROCESSO);
   $objPenTramiteProcessadoRN->setRecebido($parNumIdentificacaoTramite);
            
  }
  
    /**
     * Retorna um array com alguns metadados, onde o indice de � o hash do arquivo
     * 
     * @return array[String]
     */
    private function listarMetaDadosComponentesDigitais($objProcesso){
        
        $objMapBD = new GenericoBD($this->getObjInfraIBanco());
        $arrMetadadoDocumento = array();
        $arrObjDocumento = is_array($objProcesso->documento) ? $objProcesso->documento : array($objProcesso->documento);

        foreach($arrObjDocumento as $objDocumento){

            $strHash = ProcessoEletronicoRN::getHashFromMetaDados($objDocumento->componenteDigital->hash);
                        
            $objMapDTO = new PenRelTipoDocMapRecebidoDTO(true);
            $objMapDTO->setNumMaxRegistrosRetorno(1);
            $objMapDTO->setNumCodigoEspecie($objDocumento->especie->codigo);
            $objMapDTO->retStrNomeSerie();

            $objMapDTO = $objMapBD->consultar($objMapDTO);

            if(empty($objMapDTO)) {
                $strNomeDocumento = '[ref '.$objDocumento->especie->nomeNoProdutor.']';
            }
            else {
                $strNomeDocumento = $objMapDTO->getStrNomeSerie();
            }
            
            $arrMetadadoDocumento[$strHash] = array(
                'especieNome' => $strNomeDocumento
            );            
        }
        
        return $arrMetadadoDocumento;
    }
  
    /**
     * Valida cada componente digital, se n�o algum n�o for aceito recusa o tramite
     * do procedimento para esta unidade
     */
    private function validarComponentesDigitais($objProcesso, $parNumIdentificacaoTramite){

        $arrObjDocumentos = is_array($objProcesso->documento) ? $objProcesso->documento : array($objProcesso->documento);
        
        foreach($arrObjDocumentos as $objDocument){

            $objPenRelTipoDocMapEnviadoDTO = new PenRelTipoDocMapRecebidoDTO();
            $objPenRelTipoDocMapEnviadoDTO->retTodos();
            $objPenRelTipoDocMapEnviadoDTO->setNumCodigoEspecie($objDocument->especie->codigo);

            $objProcessoEletronicoDB = new PenRelTipoDocMapRecebidoBD(BancoSEI::getInstance());
            $numContador = (integer)$objProcessoEletronicoDB->contar($objPenRelTipoDocMapEnviadoDTO);

            // N�o achou, ou seja, n�o esta cadastrado na tabela, ent�o n�o �
            // aceito nesta unidade como v�lido
            if($numContador <= 0) {
                $this->objProcessoEletronicoRN->recusarTramite($parNumIdentificacaoTramite, sprintf('Documento do tipo %s n�o est� mapeado', $objDocument->especie->nomeNoProdutor), ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_FORMATO);
                throw new InfraException(sprintf('Documento do tipo %s n�o est� mapeado. Motivo da Recusa no Barramento: %s', $objDocument->especie->nomeNoProdutor, ProcessoEletronicoRN::$MOTIVOS_RECUSA[ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_FORMATO]));
            } 
        }


        $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
        $numTamDocExterno = $objInfraParametro->getValor('SEI_TAM_MB_DOC_EXTERNO');


        foreach($arrObjDocumentos as $objDocument) {


            if (is_null($objDocument->componenteDigital->tamanhoEmBytes) || $objDocument->componenteDigital->tamanhoEmBytes == 0){  
                
                throw new InfraException('Tamanho de componente digital n�o informado.', null, 'RECUSA: '.ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_OUTROU);
                
            }

            if($objDocument->componenteDigital->tamanhoEmBytes > ($numTamDocExterno * 1024 * 1024)){

                $numTamanhoMb = $objDocument->componenteDigital->tamanhoEmBytes / ( 1024 * 1024);
                $this->objProcessoEletronicoRN->recusarTramite($parNumIdentificacaoTramite, 'Componente digital n�o pode ultrapassar '.$numTamDocExterno.', o tamanho do anexo � '.$numTamanhoMb.' .', ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_OUTROU);
                throw new InfraException('Componente digital n�o pode ultrapassar '.$numTamDocExterno.', o tamanho do anexo � '.$numTamanhoMb);

            }
        }
        
    }


  private function registrarProcesso($parStrNumeroRegistro, $parNumIdentificacaoTramite, $parObjProcesso, $parObjMetadadosProcedimento)
  {
    // Valida��o dos dados do processo recebido
    $objInfraException = new InfraException();
    $this->validarDadosProcesso($objInfraException, $parObjProcesso);
    $this->validarDadosDocumentos($objInfraException, $parObjProcesso);

    //TODO: Regra de Neg�cio - Processos recebidos pelo Barramento n�o poder�o disponibilizar a op��o de reordena��o e cancelamento de documentos 
    //para o usu�rio final, mesmo possuindo permiss�o para isso

    $objInfraException->lancarValidacoes();

    //Verificar se procedimento j� existia na base de dados do sistema
    $dblIdProcedimento = $this->consultarProcedimentoExistente($parStrNumeroRegistro, $parObjProcesso->protocolo);

    if(isset($dblIdProcedimento)){
      //TODO: Tratar situa��o em que o processo (NUP) j� existia na base do sistema mas n�o havia nenhum NRE registrado para ele
      $objProcedimentoDTO = $this->atualizarProcedimento($dblIdProcedimento, $parObjMetadadosProcedimento, $parObjProcesso);                
    }
    else {            
      //TODO: Gerar Procedimento com status BLOQUEADO, aguardando o recebimento dos componentes digitais
      $objProcedimentoDTO = $this->gerarProcedimento($parObjMetadadosProcedimento, $parObjProcesso);
    }

    //TODO: Fazer o envio de cada um dos procedimentos apensados (Processo principal e seus apensados, caso exista)
    //...        

    //Chamada recursiva para registro dos processos apensados
    if(isset($objProcesso->processoApensado)) {
      if(!is_array($objProcesso->processoApensado)) {
        $objProcesso->processoApensado = array($objProcesso->processoApensado);
      }

      foreach ($objProcesso->processoApensado as $objProcessoApensado) {
        $this->registrarProcesso($parStrNumeroRegistro, $parNumIdentificacaoTramite, $objProcessoApensado, $parObjMetadadosProcedimento);
      }                
    }

    return $objProcedimentoDTO;
  }

  private function tramiteRegistrado($parStrNumeroRegistro, $parNumIdentificacaoTramite) {

    $objTramiteDTO = new TramiteDTO();
    $objTramiteDTO->setStrNumeroRegistro($parStrNumeroRegistro);
    $objTramiteDTO->setNumIdTramite($parNumIdentificacaoTramite);

    $objTramiteBD = new TramiteBD($this->getObjInfraIBanco());
    return $objTramiteBD->contar($objTramiteDTO) > 0;
  }

  private function consultarProcedimentoExistente($parStrNumeroRegistro, $parStrProtocolo = null) {

    $dblIdProcedimento = null;        

    $objProcessoEletronicoDTO = new ProcessoEletronicoDTO();
    $objProcessoEletronicoDTO->retDblIdProcedimento();
    $objProcessoEletronicoDTO->setStrNumeroRegistro($parStrNumeroRegistro);

        //TODO: Manter o padr�o o sistema em chamar uma classe de regra de neg�cio (RN) e n�o diretamente um classe BD
    $objProcessoEletronicoBD = new ProcessoEletronicoBD($this->getObjInfraIBanco());
    $objProcessoEletronicoDTO = $objProcessoEletronicoBD->consultar($objProcessoEletronicoDTO);

    if(isset($objProcessoEletronicoDTO)){
      $dblIdProcedimento = $objProcessoEletronicoDTO->getDblIdProcedimento();
    }

    return $dblIdProcedimento;
  }

  private function atualizarProcedimento($parDblIdProcedimento, $objMetadadosProcedimento, $objProcesso){

    if(!isset($parDblIdProcedimento)){
      throw new InfraException('Par�metro $parDblIdProcedimento n�o informado.');
    }        

    if(!isset($objMetadadosProcedimento)){
      throw new InfraException('Par�metro $objMetadadosProcedimento n�o informado.');
    }
    
    $objDestinatario = $objMetadadosProcedimento->metadados->destinatario;

        //TODO: Refatorar c�digo para criar m�todo de pesquisa do procedimento e reutiliz�-la

        //$objProcedimentoDTO = new ProcedimentoDTO();
        //$objProcedimentoDTO->setDblIdProcedimento($parDblIdProcedimento);
        //$objProcedimentoDTO->retTodos();
        //$objProcedimentoDTO->retStrProtocoloProcedimentoFormatado();        
        //$objProcedimentoDTO->setStrSinDocTodos('S');
    
        //$objProcedimentoRN = new ProcedimentoRN();
        //$arrObjProcedimentoDTO = $objProcedimentoRN->listarCompleto($objProcedimentoDTO);

        //if(count($arrObjProcedimentoDTO) == 0){
        //    throw new InfraException('Processo n�o pode ser localizado. ('.$parDblIdProcedimento.')');
        //}

        //$objProcedimentoDTO = $arrObjProcedimentoDTO[0];
    
    //REALIZA O DESBLOQUEIO DO PROCESSO
    $objEntradaDesbloquearProcessoAPI = new EntradaDesbloquearProcessoAPI();
    $objEntradaDesbloquearProcessoAPI->setIdProcedimento($parDblIdProcedimento);
    
    $objSeiRN = new SeiRN();
    $objSeiRN->desbloquearProcesso($objEntradaDesbloquearProcessoAPI);

    
    $objProcedimentoDTO = new ProcedimentoDTO();
    $objProcedimentoDTO->setDblIdProcedimento($parDblIdProcedimento);
    $objProcedimentoDTO->retTodos();
    $objProcedimentoDTO->retStrProtocoloProcedimentoFormatado();

    $objProcedimentoRN = new ProcedimentoRN();
    $objProcedimentoDTO = $objProcedimentoRN->consultarRN0201($objProcedimentoDTO);

        //TODO: Obter c�digo da unidade atrav�s de mapeamento entre SEI e Barramento
    $objUnidadeDTO = $this->atribuirDadosUnidade($objProcedimentoDTO, $objDestinatario);

    $this->registrarAndamentoRecebimentoProcesso($objProcedimentoDTO, $objMetadadosProcedimento, $objUnidadeDTO);
    $this->atribuirDocumentos($objProcedimentoDTO, $objProcesso, $objUnidadeDTO, $objMetadadosProcedimento); 
    $this->registrarProcedimentoNaoVisualizado($objProcedimentoDTO);

        //TODO: Avaliar necessidade de restringir refer�ncia circular entre processos
        //TODO: Registrar que o processo foi recebido com outros apensados. Necess�rio para posterior reenvio
    $this->atribuirProcessosApensados($objProcedimentoDTO, $objProcesso->processoApensado);

        //TODO: Finalizar o envio do documento para a respectiva unidade
    $this->enviarProcedimentoUnidade($objProcedimentoDTO, true);

        //TODO: Avaliar necessidade de criar acesso externo para o processo recebido
        //TODO: Avaliar necessidade de tal recurso
        //FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
        //FeedSEIProtocolos::getInstance()->indexarFeeds();

        //InfraDebug::getInstance()->gravar('RETORNO:'.print_r($ret,true));
        //LogSEI::getInstance()->gravar(InfraDebug::getInstance()->getStrDebug());

    $this->removerAndamentosProcedimento($objProcedimentoDTO);
    return $objProcedimentoDTO;


  }

  private function gerarProcedimento($objMetadadosProcedimento, $objProcesso){

    if(!isset($objMetadadosProcedimento)){
      throw new InfraException('Par�metro $objMetadadosProcedimento n�o informado.');
    }

        //TODO: Usar dados do destinat�rio em outro m�todo espec�fico para envio
        // Dados do procedimento enviados pelos �rg�o externo integrado ao PEN        
        //$objProcesso = $objMetadadosProcedimento->metadados->processo;
    $objRemetente = $objMetadadosProcedimento->metadados->remetente;
    $objDestinatario = $objMetadadosProcedimento->metadados->destinatario;

        //TODO: TESTES DE RECEBIMENTO DE PROCESSOS
        //REMOVER APOS TESTES DO SISTEMA
        //$objProcesso->protocolo = rand(100000000, 999999999);

        //Atribui��o de dados do protocolo
        //TODO: Validar cada uma das informa��es de entrada do webservice
    $objProtocoloDTO = new ProtocoloDTO();
    $objProtocoloDTO->setDblIdProtocolo(null);
    $objProtocoloDTO->setStrDescricao(utf8_decode($objProcesso->descricao));
    $objProtocoloDTO->setStrStaNivelAcessoLocal($this->obterNivelSigiloSEI($objProcesso->nivelDeSigilo));
    $objProtocoloDTO->setStrProtocoloFormatado(utf8_decode($objProcesso->protocolo));
    $objProtocoloDTO->setDtaGeracao($this->objProcessoEletronicoRN->converterDataSEI($objProcesso->dataHoraDeProducao));
    $objProtocoloDTO->setArrObjAnexoDTO(array());
    $objProtocoloDTO->setArrObjRelProtocoloAssuntoDTO(array());
    $objProtocoloDTO->setArrObjRelProtocoloProtocoloDTO(array());
    //$objProtocoloDTO->setStrStaEstado(ProtocoloRN::$TE_BLOQUEADO);
    $this->atribuirRemetente($objProtocoloDTO, $objRemetente);
    $this->atribuirParticipantes($objProtocoloDTO, $objProcesso->interessado);
     
    $strDescricao  = sprintf('Tipo de processo no �rg�o de origem: %s', utf8_decode($objProcesso->processoDeNegocio)).PHP_EOL;
    $strDescricao .= $objProcesso->observacao;
    
    $objObservacaoDTO  = new ObservacaoDTO();
    $objObservacaoDTO->setStrDescricao($strDescricao);
    $objProtocoloDTO->setArrObjObservacaoDTO(array($objObservacaoDTO));

        //Atribui��o de dados do procedimento
        //TODO: Validar cada uma das informa��es de entrada do webservice
    $objProcedimentoDTO = new ProcedimentoDTO();        
    $objProcedimentoDTO->setDblIdProcedimento(null);
    $objProcedimentoDTO->setObjProtocoloDTO($objProtocoloDTO);        
    $objProcedimentoDTO->setStrNomeTipoProcedimento(utf8_decode($objProcesso->processoDeNegocio));
    $objProcedimentoDTO->setDtaGeracaoProtocolo($this->objProcessoEletronicoRN->converterDataSEI($objProcesso->dataHoraDeProducao));
    $objProcedimentoDTO->setStrProtocoloProcedimentoFormatado(utf8_decode($objProcesso->protocolo));
    $objProcedimentoDTO->setStrSinGerarPendencia('S');
       // $objProcedimentoDTO->setNumVersaoLock(0);  //TODO: Avaliar o comportamento desse campo no cadastro do processo
        $objProcedimentoDTO->setArrObjDocumentoDTO(array());
        
        //TODO: Identificar o tipo de procedimento correto para atribui��o ao novo processo
        $numIdTipoProcedimento = $this->objInfraParametro->getValor('PEN_TIPO_PROCESSO_EXTERNO');
        $this->atribuirTipoProcedimento($objProcedimentoDTO, $numIdTipoProcedimento, $objProcesso->processoDeNegocio);        

        //TODO: Obter c�digo da unidade atrav�s de mapeamento entre SEI e Barramento
        $objUnidadeDTO = $this->atribuirDadosUnidade($objProcedimentoDTO, $objDestinatario);

        //TODO: Tratar processamento de atributos procedimento_cadastro:177
        //...
        
        //TODO: Atribuir Dados do produtor do processo
        //$this->atribuirProdutorProcesso($objProcesso, 
        //    $objProcedimentoDTO->getNumIdUsuarioGeradorProtocolo(), 
        //    $objProcedimentoDTO->getNumIdUnidadeGeradoraProtocolo());        


        

        //TODO:Adicionar demais informa��es do processo
        //<protocoloAnterior>
        //<historico>
        
        //$objProcesso->idProcedimentoSEI = $dblIdProcedimento;
        
        //TODO: Avaliar necessidade de tal recurso
        //FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

        //TODO: Analisar impacto do par�metro SEI_HABILITAR_NUMERO_PROCESSO_INFORMADO no recebimento do processo
        //$objSeiRN = new SeiRN();
        //$objWSRetornoGerarProcedimentoDTO = $objSeiRN->gerarProcedimento($objWSEntradaGerarProcedimentoDTO);

        //TODO: Finalizar cria��o do procedimento
        $objProcedimentoRN = new ProcedimentoRN();
        $objProcedimentoDTOGerado = $objProcedimentoRN->gerarRN0156($objProcedimentoDTO);
        $objProcedimentoDTO->setDblIdProcedimento($objProcedimentoDTOGerado->getDblIdProcedimento());

        $this->registrarAndamentoRecebimentoProcesso($objProcedimentoDTO, $objMetadadosProcedimento, $objUnidadeDTO);
        $this->atribuirDocumentos($objProcedimentoDTO, $objProcesso, $objUnidadeDTO, $objMetadadosProcedimento);        
        $this->registrarProcedimentoNaoVisualizado($objProcedimentoDTOGerado);
        
        //TODO: Avaliar necessidade de restringir refer�ncia circular entre processos
        //TODO: Registrar que o processo foi recebido com outros apensados. Necess�rio para posterior reenvio
        $this->atribuirProcessosApensados($objProcedimentoDTO, $objProcesso->processoApensado);

        //TODO: Finalizar o envio do documento para a respectiva unidade
        $this->enviarProcedimentoUnidade($objProcedimentoDTO);

        //TODO: Avaliar necessidade de criar acesso externo para o processo recebido
        //TODO: Avaliar necessidade de tal recurso
        //FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
        //FeedSEIProtocolos::getInstance()->indexarFeeds();

        //InfraDebug::getInstance()->gravar('RETORNO:'.print_r($ret,true));
        //LogSEI::getInstance()->gravar(InfraDebug::getInstance()->getStrDebug());

        $this->removerAndamentosProcedimento($objProcedimentoDTO);
        return $objProcedimentoDTO;
      }

   
      private function removerAndamentosProcedimento($parObjProtocoloDTO) 
      {
        //TODO: Remover apenas as atividades geradas pelo recebimento do processo, n�o as atividades geradas anteriormente
        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->retNumIdAtividade();
        $objAtividadeDTO->setDblIdProtocolo($parObjProtocoloDTO->getDblIdProcedimento());
        $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_GERACAO_PROCEDIMENTO);

        $objAtividadeRN = new AtividadeRN();
        $objAtividadeRN->excluirRN0034($objAtividadeRN->listarRN0036($objAtividadeDTO));        
      }

      private function registrarAndamentoRecebimentoProcesso(ProcedimentoDTO $objProcedimentoDTO, $parObjMetadadosProcedimento, $objUnidadeDTO)
      {
        //Processo recebido da entidade @ENTIDADE_ORIGEM@ - @REPOSITORIO_ORIGEM@ 
        //TODO: Atribuir atributos necess�rios para forma��o da mensagem do andamento
        //TODO: Especificar quais andamentos ser�o registrados        
        $objRemetente = $parObjMetadadosProcedimento->metadados->remetente;
        $objProcesso = $objMetadadosProcedimento->metadados->processo;        

        $arrObjAtributoAndamentoDTO = array();

        //TODO: Otimizar c�digo. Pesquisar 1 �nico elemento no barramento de servi�os
        $objRepositorioDTO = $this->objProcessoEletronicoRN->consultarRepositoriosDeEstruturas(
          $objRemetente->identificacaoDoRepositorioDeEstruturas);

        //TODO: Otimizar c�digo. Apenas buscar no barramento os dados da estrutura 1 �nica vez (AtribuirRemetente tamb�m utiliza)
        $objEstrutura = $this->objProcessoEletronicoRN->consultarEstrutura(
          $objRemetente->identificacaoDoRepositorioDeEstruturas, 
          $objRemetente->numeroDeIdentificacaoDaEstrutura,
          true
        );

        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('REPOSITORIO_ORIGEM');
        $objAtributoAndamentoDTO->setStrValor($objRepositorioDTO->getStrNome());
        $objAtributoAndamentoDTO->setStrIdOrigem($objRepositorioDTO->getNumId());
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('ENTIDADE_ORIGEM');
        $objAtributoAndamentoDTO->setStrValor($objEstrutura->nome);
        $objAtributoAndamentoDTO->setStrIdOrigem($objEstrutura->numeroDeIdentificacaoDaEstrutura);
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
        
        if(isset($objEstrutura->hierarquia)) {
            
            $arrObjNivel = $objEstrutura->hierarquia->nivel;
         
            $nome = "";
            $siglasUnidades = array();
            $siglasUnidades[] = $objEstrutura->sigla;
            
            foreach($arrObjNivel as $key => $objNivel){
                $siglasUnidades[] = $objNivel->sigla  ;
            }
            
            for($i = 1; $i <= 3; $i++){
                if(isset($siglasUnidades[count($siglasUnidades) - 1])){
                    unset($siglasUnidades[count($siglasUnidades) - 1]);
                }
            }

            foreach($siglasUnidades as $key => $nomeUnidade){
                if($key == (count($siglasUnidades) - 1)){
                    $nome .= $nomeUnidade." ";
                }else{
                    $nome .= $nomeUnidade." / ";
                }
            }
            
            $objNivel = current($arrObjNivel);

            $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
            $objAtributoAndamentoDTO->setStrNome('ENTIDADE_ORIGEM_HIRARQUIA');
            $objAtributoAndamentoDTO->setStrValor($nome);
            $objAtributoAndamentoDTO->setStrIdOrigem($objNivel->numeroDeIdentificacaoDaEstrutura);
            $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
        }
                
        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->setDblIdProtocolo($objProcedimentoDTO->getDblIdProcedimento());
        $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objAtividadeDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
        $objAtividadeDTO->setNumIdTarefa(ProcessoEletronicoRN::obterIdTarefaModulo(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_RECEBIDO));
        $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

        $objAtividadeRN = new AtividadeRN();
        $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
               
      }


    //TODO: Avaliar a necessidade de registrar os dados do remetente como participante do processo
      private function atribuirRemetente(ProtocoloDTO $objProtocoloDTO, $objRemetente)
      {           
        $arrObjParticipantesDTO = array();        
        if($objProtocoloDTO->isSetArrObjParticipanteDTO()) {
          $arrObjParticipantesDTO = $objProtocoloDTO->getArrObjParticipanteDTO();        
        }
        
        //Obten��o de detalhes do remetente na infraestrutura do PEN
        $objEstruturaDTO = $this->objProcessoEletronicoRN->consultarEstrutura(
          $objRemetente->identificacaoDoRepositorioDeEstruturas, 
          $objRemetente->numeroDeIdentificacaoDaEstrutura);

        if(!empty($objEstruturaDTO)) {
          $objParticipanteDTO  = new ParticipanteDTO();
          $objParticipanteDTO->setStrSiglaContato($objEstruturaDTO->getStrSigla());
          $objParticipanteDTO->setStrNomeContato($objEstruturaDTO->getStrNome());
          $objParticipanteDTO->setStrStaParticipacao(ParticipanteRN::$TP_REMETENTE);
          $objParticipanteDTO->setNumSequencia(0);
          $arrObjParticipantesDTO[] = $objParticipanteDTO;
          $arrObjParticipantesDTO = $this->prepararParticipantes($arrObjParticipantesDTO);
        }

        $objProtocoloDTO->setArrObjParticipanteDTO($arrObjParticipantesDTO);
      }


      private function atribuirParticipantes(ProtocoloDTO $objProtocoloDTO, $arrObjInteressados)
      {        
        $arrObjParticipantesDTO = array();        
        if($objProtocoloDTO->isSetArrObjParticipanteDTO()) {
          $arrObjParticipantesDTO = $objProtocoloDTO->getArrObjParticipanteDTO();        
        }

        if (!is_array($arrObjInteressados)) {
          $arrObjInteressados = array($arrObjInteressados);
        }

        for($i=0; $i < count($arrObjInteressados); $i++){
          $objInteressado = $arrObjInteressados[$i];
          $objParticipanteDTO  = new ParticipanteDTO();
          $objParticipanteDTO->setStrSiglaContato($objInteressado->numeroDeIdentificacao);
          $objParticipanteDTO->setStrNomeContato(utf8_decode($objInteressado->nome));
          $objParticipanteDTO->setStrStaParticipacao(ParticipanteRN::$TP_INTERESSADO);
          $objParticipanteDTO->setNumSequencia($i);
          $arrObjParticipantesDTO[] = $objParticipanteDTO;
        }

        $arrObjParticipanteDTO = $this->prepararParticipantes($arrObjParticipantesDTO);
        $objProtocoloDTO->setArrObjParticipanteDTO($arrObjParticipantesDTO);

      }

      private function atribuirTipoProcedimento(ProcedimentoDTO $objProcedimentoDTO, $numIdTipoProcedimento)
      {
        if(!isset($numIdTipoProcedimento)){
          throw new InfraException('Par�metro $numIdTipoProcedimento n�o informado.');
        }

        $objTipoProcedimentoDTO = new TipoProcedimentoDTO();
        $objTipoProcedimentoDTO->retNumIdTipoProcedimento();
        $objTipoProcedimentoDTO->retStrNome();
        $objTipoProcedimentoDTO->setNumIdTipoProcedimento($numIdTipoProcedimento);

        $objTipoProcedimentoRN = new TipoProcedimentoRN();
        $objTipoProcedimentoDTO = $objTipoProcedimentoRN->consultarRN0267($objTipoProcedimentoDTO);

        if ($objTipoProcedimentoDTO==null){
          throw new InfraException('Tipo de processo n�o encontrado.');
        }

        $objProcedimentoDTO->setNumIdTipoProcedimento($objTipoProcedimentoDTO->getNumIdTipoProcedimento());
        $objProcedimentoDTO->setStrNomeTipoProcedimento($objTipoProcedimentoDTO->getStrNome());

        //Busca e adiciona os assuntos sugeridos para o tipo informado    
        $objRelTipoProcedimentoAssuntoDTO = new RelTipoProcedimentoAssuntoDTO();
        $objRelTipoProcedimentoAssuntoDTO->retNumIdAssunto();
        $objRelTipoProcedimentoAssuntoDTO->retNumSequencia();
        $objRelTipoProcedimentoAssuntoDTO->setNumIdTipoProcedimento($objProcedimentoDTO->getNumIdTipoProcedimento());  

        $objRelTipoProcedimentoAssuntoRN = new RelTipoProcedimentoAssuntoRN();
        $arrObjRelTipoProcedimentoAssuntoDTO = $objRelTipoProcedimentoAssuntoRN->listarRN0192($objRelTipoProcedimentoAssuntoDTO);
        $arrObjAssuntoDTO = $objProcedimentoDTO->getObjProtocoloDTO()->getArrObjRelProtocoloAssuntoDTO();

        foreach($arrObjRelTipoProcedimentoAssuntoDTO as $objRelTipoProcedimentoAssuntoDTO){
          $objRelProtocoloAssuntoDTO = new RelProtocoloAssuntoDTO();
          $objRelProtocoloAssuntoDTO->setNumIdAssunto($objRelTipoProcedimentoAssuntoDTO->getNumIdAssunto());
          $objRelProtocoloAssuntoDTO->setNumSequencia($objRelTipoProcedimentoAssuntoDTO->getNumSequencia());
          $arrObjAssuntoDTO[] = $objRelProtocoloAssuntoDTO;
        }

        $objProcedimentoDTO->getObjProtocoloDTO()->setArrObjRelProtocoloAssuntoDTO($arrObjAssuntoDTO);
      }

      protected function atribuirDadosUnidade(ProcedimentoDTO $objProcedimentoDTO, $objDestinatario){

        if(!isset($objDestinatario)){
          throw new InfraException('Par�metro $objDestinatario n�o informado.');
        }

        $objUnidadeDTOEnvio = $this->obterUnidadeMapeada($objDestinatario->numeroDeIdentificacaoDaEstrutura);

        if(!isset($objUnidadeDTOEnvio))
          throw new InfraException('Unidade de destino n�o pode ser encontrada. Reposit�rio: '.$objDestinatario->identificacaoDoRepositorioDeEstruturas.', N�mero: ' . $objDestinatario->numeroDeIdentificacaoDaEstrutura);

        $arrObjUnidadeDTO = array();        
        $arrObjUnidadeDTO[] = $objUnidadeDTOEnvio;           
        $objProcedimentoDTO->setArrObjUnidadeDTO($arrObjUnidadeDTO);

        return $objUnidadeDTOEnvio;
      }


    //TODO: Grande parte da regra de neg�cio se baseou em SEIRN:199 - incluirDocumento.
    //Avaliar a refatora��o para impedir a duplica��o de c�digo
      private function atribuirDocumentos($objProcedimentoDTO, $objProcesso, $objUnidadeDTO, $parObjMetadadosProcedimento)
      {    
          
        if(!isset($objProcesso)) {
          throw new InfraException('Par�metro $objProcesso n�o informado.');
        }

        if(!isset($objUnidadeDTO)) {
          throw new InfraException('Unidade respons�vel pelo documento n�o informada.');
        }

        if(!isset($objProcesso->documento)) {
          throw new InfraException('Lista de documentos do processo n�o informada.');
        }

        $arrObjDocumentos = $objProcesso->documento;
        if(!is_array($arrObjDocumentos)) {
          $arrObjDocumentos = array($arrObjDocumentos);    
        }

        $strNumeroRegistro = $parObjMetadadosProcedimento->metadados->NRE;
        //$numTramite = $parObjMetadadosProcedimento->metadados->IDT;

        //Ordena��o dos documentos conforme informado pelo remetente. Campo documento->ordem
        usort($arrObjDocumentos, array("ReceberProcedimentoRN", "comparacaoOrdemDocumentos"));    

        //Obter dados dos documentos j� registrados no sistema
        $objComponenteDigitalDTO = new ComponenteDigitalDTO();
        $objComponenteDigitalDTO->retNumOrdem();
        $objComponenteDigitalDTO->retDblIdDocumento();
        $objComponenteDigitalDTO->retStrHashConteudo();
        $objComponenteDigitalDTO->setStrNumeroRegistro($strNumeroRegistro);
        $objComponenteDigitalDTO->setOrdNumOrdem(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objComponenteDigitalBD = new ComponenteDigitalBD($this->getObjInfraIBanco());
        $arrObjComponenteDigitalDTO = $objComponenteDigitalBD->listar($objComponenteDigitalDTO);
        $arrObjComponenteDigitalDTOIndexado = InfraArray::indexarArrInfraDTO($arrObjComponenteDigitalDTO, "Ordem");
        $arrStrHashConteudo = InfraArray::converterArrInfraDTO($arrObjComponenteDigitalDTO, 'IdDocumento', 'HashConteudo');

        $objProtocoloBD = new ProtocoloBD($this->getObjInfraIBanco());
        
        $arrObjDocumentoDTO = array();
        foreach($arrObjDocumentos as $objDocumento){
            
            // @join_tec US027 (#3498)
            // Previne que o documento digital seja cadastrado na base de dados
           if(isset($objDocumento->retirado) && $objDocumento->retirado === true) {

                $strHashConteudo = ProcessoEletronicoRN::getHashFromMetaDados($objDocumento->componenteDigital->hash);
                
                // Caso j� esteja cadastrado, de um reenvio anterior, ent�o move para bloqueado
                if(array_key_exists($strHashConteudo, $arrStrHashConteudo)) {
                    
                    //Busca o ID do protocolo
                    $dblIdProtocolo = $arrStrHashConteudo[$strHashConteudo];
                    
                    //Instancia o DTO do protocolo
                    $objProtocoloDTO = new ProtocoloDTO();
                    $objProtocoloDTO->setDblIdProtocolo($dblIdProtocolo);
                    $objProtocoloDTO->retTodos();
                    
                    $objProtocoloDTO = $objProtocoloBD->consultar($objProtocoloDTO);
                    
                    if($objProtocoloDTO->getStrStaEstado() != ProtocoloRN::$TE_CANCELADO){
                        $objProtocoloDTO->setStrMotivoCancelamento('Cancelado pelo remetente');
                        $objProtocoloRN = new PenProtocoloRN();
                        $objProtocoloRN->cancelar($objProtocoloDTO);
                    }
 
                    
                    continue;
            
                }
                //continue;
            }

            if(array_key_exists($objDocumento->ordem, $arrObjComponenteDigitalDTOIndexado)){
                continue;
            }

            //Valida��o dos dados dos documentos
          if(!isset($objDocumento->especie)){
            throw new InfraException('Esp�cie do documento ['.$objDocumento->descricao.'] n�o informada.');
          }
          
//---------------------------------------------------------------------------------------------------            

          $objDocumentoDTO = new DocumentoDTO();
          $objDocumentoDTO->setDblIdDocumento(null);
          $objDocumentoDTO->setDblIdProcedimento($objProcedimentoDTO->getDblIdProcedimento());

          $objSerieDTO = $this->obterSerieMapeada($objDocumento->especie->codigo);

          if ($objSerieDTO==null){
            throw new InfraException('Tipo de documento [Esp�cie '.$objDocumento->especie->codigo.'] n�o encontrado.');
          }

          if (InfraString::isBolVazia($objDocumento->dataHoraDeProducao)) {
            $objInfraException->lancarValidacao('Data do documento n�o informada.');
          }

          $objProcedimentoDTO2 = new ProcedimentoDTO();
          $objProcedimentoDTO2->retDblIdProcedimento();
          $objProcedimentoDTO2->retNumIdUsuarioGeradorProtocolo();
          $objProcedimentoDTO2->retNumIdTipoProcedimento();
          $objProcedimentoDTO2->retStrStaNivelAcessoGlobalProtocolo();
          $objProcedimentoDTO2->retStrProtocoloProcedimentoFormatado();
          $objProcedimentoDTO2->retNumIdTipoProcedimento();
          $objProcedimentoDTO2->retStrNomeTipoProcedimento();
          $objProcedimentoDTO2->adicionarCriterio(array('IdProcedimento','ProtocoloProcedimentoFormatado','ProtocoloProcedimentoFormatadoPesquisa'),
            array(InfraDTO::$OPER_IGUAL,InfraDTO::$OPER_IGUAL,InfraDTO::$OPER_IGUAL),
            array($objDocumentoDTO->getDblIdProcedimento(),$objDocumentoDTO->getDblIdProcedimento(),$objDocumentoDTO->getDblIdProcedimento()),
            array(InfraDTO::$OPER_LOGICO_OR,InfraDTO::$OPER_LOGICO_OR));

          $objProcedimentoRN = new ProcedimentoRN();
          $objProcedimentoDTO = $objProcedimentoRN->consultarRN0201($objProcedimentoDTO2);

          if ($objProcedimentoDTO==null){
            throw new InfraException('Processo ['.$objDocumentoDTO->getDblIdProcedimento().'] n�o encontrado.');
          }

          $objDocumentoDTO->setDblIdProcedimento($objProcedimentoDTO->getDblIdProcedimento());
          $objDocumentoDTO->setNumIdSerie($objSerieDTO->getNumIdSerie());
          $objDocumentoDTO->setStrNomeSerie($objSerieDTO->getStrNome());

          $objDocumentoDTO->setDblIdDocumentoEdoc(null);
          $objDocumentoDTO->setDblIdDocumentoEdocBase(null);
          $objDocumentoDTO->setNumIdUnidadeResponsavel($objUnidadeDTO->getNumIdUnidade());
          $objDocumentoDTO->setNumIdTipoConferencia(null);
          $objDocumentoDTO->setStrConteudo(null);
          $objDocumentoDTO->setStrStaDocumento(DocumentoRN::$TD_EXTERNO);
         // $objDocumentoDTO->setNumVersaoLock(0);

          $objProtocoloDTO = new ProtocoloDTO();
          $objDocumentoDTO->setObjProtocoloDTO($objProtocoloDTO);
          $objProtocoloDTO->setDblIdProtocolo(null);
          $objProtocoloDTO->setStrStaProtocolo(ProtocoloRN::$TP_DOCUMENTO_RECEBIDO);
          
          if($objDocumento->descricao != '***'){
              $objProtocoloDTO->setStrDescricao(utf8_decode($objDocumento->descricao));
              $objDocumentoDTO->setStrNumero(utf8_decode($objDocumento->descricao));
          }else{
              $objProtocoloDTO->setStrDescricao("");
              $objDocumentoDTO->setStrNumero("");
          }
            //TODO: Avaliar regra de forma��o do n�mero do documento
                      
          $objProtocoloDTO->setStrStaNivelAcessoLocal($this->obterNivelSigiloSEI($objDocumento->nivelDeSigilo));
          $objProtocoloDTO->setDtaGeracao($this->objProcessoEletronicoRN->converterDataSEI($objDocumento->dataHoraDeProducao));
          $objProtocoloDTO->setArrObjAnexoDTO(array());
          $objProtocoloDTO->setArrObjRelProtocoloAssuntoDTO(array());
          $objProtocoloDTO->setArrObjRelProtocoloProtocoloDTO(array());
          $objProtocoloDTO->setArrObjParticipanteDTO(array());
                    
            //TODO: Analisar se o modelo de dados do PEN possui destinat�rios espec�ficos para os documentos
            //caso n�o possua, analisar o repasse de tais informa��es via par�metros adicionais

          $objObservacaoDTO  = new ObservacaoDTO();
          $objObservacaoDTO->setStrDescricao("N�mero SEI do Documento na Origem: ".$objDocumento->produtor->numeroDeIdentificacao);
          $objProtocoloDTO->setArrObjObservacaoDTO(array($objObservacaoDTO));


          $bolReabriuAutomaticamente = false;
          if ($objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo()==ProtocoloRN::$NA_PUBLICO || $objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo()==ProtocoloRN::$NA_RESTRITO) {

            $objAtividadeDTO = new AtividadeDTO();
            $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
            $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());

                //TODO: Possivelmente, essa regra � desnecess�ria j� que o processo pode ser enviado para outra unidade do �rg�o atrav�s da expedi��o
            $objAtividadeRN = new AtividadeRN();
            if ($objAtividadeRN->contarRN0035($objAtividadeDTO) == 0) {
              throw new InfraException('Unidade '.$objUnidadeDTO->getStrSigla().' n�o possui acesso ao Procedimento '.$objProcedimentoDTO->getStrProtocoloProcedimentoFormatado().'.');
            }

            $objAtividadeDTO = new AtividadeDTO();
            $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
            $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
            $objAtividadeDTO->setDthConclusao(null);

            if ($objAtividadeRN->contarRN0035($objAtividadeDTO) == 0) {
                    //reabertura autom�tica
              $objReabrirProcessoDTO = new ReabrirProcessoDTO();
              $objReabrirProcessoDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
              $objReabrirProcessoDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
              $objReabrirProcessoDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
              $objProcedimentoRN->reabrirRN0966($objReabrirProcessoDTO);
              $bolReabriuAutomaticamente = true;
            }             
          }
          
            //$objOperacaoServicoDTO = new OperacaoServicoDTO();
            //$this->adicionarCriteriosUnidadeProcessoDocumento ($objOperacaoServicoDTO,$objUnidadeDTO,$objProcedimentoDTO,$objDocumentoDTO);
            //$objOperacaoServicoDTO->setNumStaOperacaoServico(OperacaoServicoRN::$TS_INCLUIR_DOCUMENTO);
            //$objOperacaoServicoDTO->setNumIdServico($objServicoDTO->getNumIdServico());

            //$objOperacaoServicoRN = new OperacaoServicoRN();
            //if ($objOperacaoServicoRN->contar($objOperacaoServicoDTO)==0){
            //    $objInfraException->lancarValidacao('Nenhuma opera��o configurada para inclus�o de documento do Tipo ['.$objSerieDTO->getStrNome().'] no Tipo de Processo ['.$objProcedimentoDTO->getStrNomeTipoProcedimento().'] na Unidade ['.$objUnidadeDTO->getStrSigla().'] pelo Servi�o ['.$objServicoDTO->getStrIdentificacao().'] do Sistema ['.$objServicoDTO->getStrSiglaUsuario().'].');
            //}

          $objTipoProcedimentoDTO = new TipoProcedimentoDTO();
          $objTipoProcedimentoDTO->retStrStaNivelAcessoSugestao();
          $objTipoProcedimentoDTO->retStrStaGrauSigiloSugestao();
          $objTipoProcedimentoDTO->retNumIdHipoteseLegalSugestao();
          $objTipoProcedimentoDTO->setNumIdTipoProcedimento($objProcedimentoDTO->getNumIdTipoProcedimento());

          $objTipoProcedimentoRN = new TipoProcedimentoRN();
          $objTipoProcedimentoDTO = $objTipoProcedimentoRN->consultarRN0267($objTipoProcedimentoDTO);

          if (InfraString::isBolVazia($objDocumentoDTO->getObjProtocoloDTO()->getStrStaNivelAcessoLocal()) || $objDocumentoDTO->getObjProtocoloDTO()->getStrStaNivelAcessoLocal()==$objTipoProcedimentoDTO->getStrStaNivelAcessoSugestao()) {
            $objDocumentoDTO->getObjProtocoloDTO()->setStrStaNivelAcessoLocal($objTipoProcedimentoDTO->getStrStaNivelAcessoSugestao());
            $objDocumentoDTO->getObjProtocoloDTO()->setStrStaGrauSigilo($objTipoProcedimentoDTO->getStrStaGrauSigiloSugestao());
            $objDocumentoDTO->getObjProtocoloDTO()->setNumIdHipoteseLegal($objTipoProcedimentoDTO->getNumIdHipoteseLegalSugestao());
          }

          $objDocumentoDTO->getObjProtocoloDTO()->setArrObjParticipanteDTO($this->prepararParticipantes($objDocumentoDTO->getObjProtocoloDTO()->getArrObjParticipanteDTO()));

          $objDocumentoRN = new DocumentoRN();

          $strConteudoCodificado = $objDocumentoDTO->getStrConteudo();
          $objDocumentoDTO->setStrConteudo(null);
          //$objDocumentoDTO->setStrSinFormulario('N');
          
            // @join_tec US027 (#3498)
            $numIdUnidadeGeradora = $this->objInfraParametro->getValor('PEN_UNIDADE_GERADORA_DOCUMENTO_RECEBIDO', false);
            // Registro existe e pode estar vazio
            if(!empty($numIdUnidadeGeradora)) {
              $objDocumentoDTO->getObjProtocoloDTO()->setNumIdUnidadeGeradora($numIdUnidadeGeradora);
            }
            $objDocumentoDTO->setStrSinBloqueado('S');
            
          //TODO: Fazer a atribui��o dos componentes digitais do processo a partir desse ponto
          $this->atribuirComponentesDigitais($objDocumentoDTO, $objDocumento->componenteDigital);            
          $objDocumentoDTOGerado = $objDocumentoRN->cadastrarRN0003($objDocumentoDTO);      

          $objAtividadeDTOVisualizacao = new AtividadeDTO();
          $objAtividadeDTOVisualizacao->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
          $objAtividadeDTOVisualizacao->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());

          if (!$bolReabriuAutomaticamente){
            $objAtividadeDTOVisualizacao->setNumTipoVisualizacao(AtividadeRN::$TV_ATENCAO);
          }else{
            $objAtividadeDTOVisualizacao->setNumTipoVisualizacao(AtividadeRN::$TV_NAO_VISUALIZADO | AtividadeRN::$TV_ATENCAO);
          }

          $objAtividadeRN = new AtividadeRN();
          $objAtividadeRN->atualizarVisualizacaoUnidade($objAtividadeDTOVisualizacao);

          $objDocumento->idDocumentoSEI = $objDocumentoDTO->getDblIdDocumento();
          $arrObjDocumentoDTO[] = $objDocumentoDTO;
          
          if(isset($objDocumento->retirado) && $objDocumento->retirado === true) {
              $this->documentosRetirados[] = $objDocumento->idDocumentoSEI;
          }
          
        }

        $objProcedimentoDTO->setArrObjDocumentoDTO($arrObjDocumentoDTO);
      }

    //TODO: M�todo dever� poder� ser transferido para a classe respons�vel por fazer o recebimento dos componentes digitais
      private function atribuirComponentesDigitais(DocumentoDTO $parObjDocumentoDTO, $parArrObjComponentesDigitais) 
      {
        if(!isset($parArrObjComponentesDigitais)) {
          throw new InfraException('Componentes digitais do documento n�o informado.');            
        }

        //TODO: Aplicar mesmas valida��es realizadas no momento do upload de um documento InfraPagina::processarUpload
        //TODO: Avaliar a refatora��o do c�digo abaixo para impedir a duplica��o de regras de neg�cios
        
        
        $arrObjAnexoDTO = array();
        if($parObjDocumentoDTO->getObjProtocoloDTO()->isSetArrObjAnexoDTO()) {
          $arrObjAnexoDTO = $parObjDocumentoDTO->getObjProtocoloDTO()->getArrObjAnexoDTO();
        }

        if (!is_array($parArrObjComponentesDigitais)) {
          $parArrObjComponentesDigitais = array($parArrObjComponentesDigitais);
        }

        //TODO: Tratar a ordem dos componentes digitais
        //...


        $parObjDocumentoDTO->getObjProtocoloDTO()->setArrObjAnexoDTO($arrObjAnexoDTO);
      }

      private function atribuirAssunto(ProtocoloDTO $objProtocoloDTO, $numIdAssunto)
      {
        //TODO: Removido. Ser�o utilizados os tipos de procedimento enviados atribu�dos ao tipo de processo externo (PEN_TIPO_PROCESSO_EXTERNO)
      }

      private function atribuirProcessosApensados(ProcedimentoDTO $objProtocoloDTO, $objProcedimento)
      {
        if(isset($objProcedimento->processoApensado)) {
          if(!is_array($objProcedimento->processoApensado)){
            $objProcedimento->processoApensado = array($objProcedimento->processoApensado);
          }

          $objProcedimentoDTOApensado = null;
          foreach ($objProcedimento->processoApensado as $processoApensado) {
            $objProcedimentoDTOApensado = $this->gerarProcedimento($objMetadadosProcedimento, $processoApensado);
            $this->relacionarProcedimentos($objProcedimentoDTOPrincipal, $objProcedimentoDTOApensado);
            $this->registrarProcedimentoNaoVisualizado($objProcedimentoDTOApensado);
          }
        }
      }

      private function bloquearProcedimento($objProcesso){

      }

      private function atribuirDataHoraDeRegistro(){

      }    

      private function cadastrarTramiteDeProcesso($objTramite, $objProcesso){

      }

      private function validarDadosDestinatario(InfraException $objInfraException, $objMetadadosProcedimento){

        if(isset($objDestinatario)){
          throw new InfraException("Par�metro $objDestinatario n�o informado.");
        }

        $objDestinatario = $objMetadadosProcedimento->metadados->destinatario;
        $numIdRepositorioOrigem = $this->objInfraParametro->getValor('PEN_ID_REPOSITORIO_ORIGEM');
        $numIdRepositorioDestinoProcesso = $objDestinatario->identificacaoDoRepositorioDeEstruturas;
        $numeroDeIdentificacaoDaEstrutura = $objDestinatario->numeroDeIdentificacaoDaEstrutura;

        //Valida��o do reposit�rio de destino do processo
        if($numIdRepositorioDestinoProcesso != $numIdRepositorioOrigem){
          $objInfraException->adicionarValidacao("Identifica��o do reposit�rio de origem do processo [$numIdRepositorioDestinoProcesso] n�o reconhecida.");
        }

        //Valida��o do unidade de destino do processo
        $objUnidadeDTO = new PenUnidadeDTO();
        $objUnidadeDTO->setNumIdUnidadeRH($numeroDeIdentificacaoDaEstrutura); 
        $objUnidadeDTO->setStrSinAtivo('S');
        $objUnidadeDTO->retNumIdUnidade();

        $objUnidadeRN = new UnidadeRN();
        $objUnidadeDTO = $objUnidadeRN->consultarRN0125($objUnidadeDTO);

        if(!isset($objUnidadeDTO)){
          $objInfraException->adicionarValidacao("Unidade de destino [Estrutura: XXXX] n�o localizada.");
          $objInfraException->adicionarValidacao("Dados: {$numeroDeIdentificacaoDaEstrutura}");
          
        }                
      }

      private function validarDadosRemetente(InfraException $objInfraException, $objMetadadosProcedimento){

      }

      private function validarDadosProcesso(InfraException $objInfraException, $objMetadadosProcedimento){

      }    

      private function validarDadosDocumentos(InfraException $objInfraException, $objMetadadosProcedimento){

      }

      private function obterNivelSigiloSEI($strNivelSigiloPEN) {
        switch ($strNivelSigiloPEN) {

          case ProcessoEletronicoRN::$STA_SIGILO_PUBLICO: return ProtocoloRN::$NA_PUBLICO;
          break;
          case ProcessoEletronicoRN::$STA_SIGILO_RESTRITO: return ProtocoloRN::$NA_RESTRITO;
          break;
          case ProcessoEletronicoRN::$STA_SIGILO_SIGILOSO: return ProtocoloRN::$NA_SIGILOSO;
          break;
          default:
          break;
        }
      }

    //TODO: Implementar o mapeamento entre as unidade do SEI e Barramento de Servi�os (Secretaria de Sa�de: 218794)
      private function obterUnidadeMapeada($numIdentificacaoDaEstrutura)
      {
        $objUnidadeDTO = new PenUnidadeDTO();
        $objUnidadeDTO->setNumIdUnidadeRH($numIdentificacaoDaEstrutura); 
        $objUnidadeDTO->setStrSinAtivo('S');
        $objUnidadeDTO->retNumIdUnidade();
        $objUnidadeDTO->retNumIdOrgao();
        $objUnidadeDTO->retStrSigla();
        $objUnidadeDTO->retStrDescricao();

        $objUnidadeRN = new UnidadeRN();
        return $objUnidadeRN->consultarRN0125($objUnidadeDTO);
      }

      /**
       * 
       * @return SerieDTO
       */
      private function obterSerieMapeada($numCodigoEspecie)
      {
        $objSerieDTO = null;

        $objMapDTO = new PenRelTipoDocMapRecebidoDTO();
        $objMapDTO->setNumCodigoEspecie($numCodigoEspecie);
        $objMapDTO->retNumIdSerie();

        $objGenericoBD = new GenericoBD($this->getObjInfraIBanco());
        $objMapDTO = $objGenericoBD->consultar($objMapDTO);
        
        if(empty($objMapDTO)) {
          $objMapDTO = new PenRelTipoDocMapRecebidoDTO();
          $objMapDTO->retNumIdSerie();
          $objMapDTO->setStrPadrao('S');
          $objMapDTO->setNumMaxRegistrosRetorno(1);
          $objMapDTO = $objGenericoBD->consultar($objMapDTO);
        }

        if(!empty($objMapDTO)) {
          $objSerieDTO = new SerieDTO();
          $objSerieDTO->retStrNome();
          $objSerieDTO->retNumIdSerie();
          $objSerieDTO->setNumIdSerie($objMapDTO->getNumIdSerie());

          $objSerieRN = new SerieRN();
          $objSerieDTO = $objSerieRN->consultarRN0644($objSerieDTO);
        }

        return $objSerieDTO;
      }

      private function relacionarProcedimentos($objProcedimentoDTO1, $objProcedimentoDTO2) 
      {
        if(!isset($objProcedimentoDTO1) || !isset($objProcedimentoDTO1)) {
          throw new InfraException('Par�metro $objProcedimentoDTO n�o informado.');
        }

        $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
        $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objProcedimentoDTO2->getDblIdProcedimento());
        $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objProcedimentoDTO1->getDblIdProcedimento());
        $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_PROCEDIMENTO_RELACIONADO);
        $objRelProtocoloProtocoloDTO->setStrMotivo(self::STR_APENSACAO_PROCEDIMENTOS);

        $objProcedimentoRN = new ProcedimentoRN();
        $objProcedimentoRN->relacionarProcedimentoRN1020($objRelProtocoloProtocoloDTO);
      }

    //TODO: M�todo identico ao localizado na classe SeiRN:2214
    //Refatorar c�digo para evitar problemas de manuten��o
      private function prepararParticipantes($arrObjParticipanteDTO)
      {
        $objContatoRN = new ContatoRN();
        $objUsuarioRN = new UsuarioRN();

        foreach($arrObjParticipanteDTO as $objParticipanteDTO) {

          $objContatoDTO = new ContatoDTO();
          $objContatoDTO->retNumIdContato();

          if (!InfraString::isBolVazia($objParticipanteDTO->getStrSiglaContato()) && !InfraString::isBolVazia($objParticipanteDTO->getStrNomeContato())) {
            $objContatoDTO->setStrSigla($objParticipanteDTO->getStrSiglaContato());
            $objContatoDTO->setStrNome($objParticipanteDTO->getStrNomeContato());

          }  else if (!InfraString::isBolVazia($objParticipanteDTO->getStrSiglaContato())) {
            $objContatoDTO->setStrSigla($objParticipanteDTO->getStrSiglaContato());

          } else if (!InfraString::isBolVazia($objParticipanteDTO->getStrNomeContato())) {
            $objContatoDTO->setStrNome($objParticipanteDTO->getStrNomeContato());
          } else {
            if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_INTERESSADO) {
              throw new InfraException('Interessado vazio ou nulo.');
            } 
            else if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_REMETENTE) {
              throw new InfraException('Remetente vazio ou nulo.');
            } 
            else if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_DESTINATARIO) {
              throw new InfraException('Destinat�rio vazio ou nulo.');
            }
          }

          $arrObjContatoDTO = $objContatoRN->listarRN0325($objContatoDTO);

          if (count($arrObjContatoDTO)) {

            $objContatoDTO = null;

                //preferencia para contatos que representam usuarios
            foreach($arrObjContatoDTO as $dto) {

              $objUsuarioDTO = new UsuarioDTO();
              $objUsuarioDTO->setBolExclusaoLogica(false);
              $objUsuarioDTO->setNumIdContato($dto->getNumIdContato());

              if ($objUsuarioRN->contarRN0492($objUsuarioDTO)) {
                $objContatoDTO = $dto;
                break;
              }
            }

                //nao achou contato de usuario pega o primeiro retornado
            if ($objContatoDTO==null)   {
              $objContatoDTO = $arrObjContatoDTO[0];
            }
          } else {
            $objContatoDTO = $objContatoRN->cadastrarContextoTemporario($objContatoDTO);
          }

          $objParticipanteDTO->setNumIdContato($objContatoDTO->getNumIdContato());
        }

        return $arrObjParticipanteDTO;
      }

      private function registrarProcedimentoNaoVisualizado(ProcedimentoDTO $parObjProcedimentoDTO) 
      {
        $objAtividadeDTOVisualizacao = new AtividadeDTO();
        $objAtividadeDTOVisualizacao->setDblIdProtocolo($parObjProcedimentoDTO->getDblIdProcedimento());
        $objAtividadeDTOVisualizacao->setNumTipoVisualizacao(AtividadeRN::$TV_NAO_VISUALIZADO);

        $objAtividadeRN = new AtividadeRN();
        $objAtividadeRN->atualizarVisualizacao($objAtividadeDTOVisualizacao);
      }

      private function enviarProcedimentoUnidade(ProcedimentoDTO $parObjProcedimentoDTO, $retransmissao = false) 
      {
        $objAtividadeRN = new PenAtividadeRN();
        $objInfraException = new InfraException();

        if(!$parObjProcedimentoDTO->isSetArrObjUnidadeDTO() || count($parObjProcedimentoDTO->getArrObjUnidadeDTO()) == 0) {
          $objInfraException->lancarValidacao('Unidade de destino do processo n�o informada.');            
        }

        $arrObjUnidadeDTO = $parObjProcedimentoDTO->getArrObjUnidadeDTO();

        if(count($parObjProcedimentoDTO->getArrObjUnidadeDTO()) > 1) {
          $objInfraException->lancarValidacao('N�o permitido a indica��o de m�ltiplas unidades de destino para um processo recebido externamente.');
        }

        $arrObjUnidadeDTO = array_values($parObjProcedimentoDTO->getArrObjUnidadeDTO());
        $objUnidadeDTO = $arrObjUnidadeDTO[0];

        $objProcedimentoDTO = new ProcedimentoDTO();
        $objProcedimentoDTO->retDblIdProcedimento();
        $objProcedimentoDTO->retNumIdTipoProcedimento();
        $objProcedimentoDTO->retStrProtocoloProcedimentoFormatado();
        $objProcedimentoDTO->retNumIdTipoProcedimento();
        $objProcedimentoDTO->retStrNomeTipoProcedimento();
        $objProcedimentoDTO->retStrStaNivelAcessoGlobalProtocolo();
//        $objProcedimentoDTO->retStrStaEstadoProtocolo();
        $objProcedimentoDTO->setStrProtocoloProcedimentoFormatado($parObjProcedimentoDTO->getStrProtocoloProcedimentoFormatado());

        $objProcedimentoRN = new ProcedimentoRN();
        $objProcedimentoDTO = $objProcedimentoRN->consultarRN0201($objProcedimentoDTO);

        if ($objProcedimentoDTO == null || $objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo()==ProtocoloRN::$NA_SIGILOSO) {
          $objInfraException->lancarValidacao('Processo ['.$parObjProcedimentoDTO->getStrProtocoloProcedimentoFormatado().'] n�o encontrado.');
        }

        if ($objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo()==ProtocoloRN::$NA_RESTRITO) {
          $objAcessoDTO = new AcessoDTO();
          $objAcessoDTO->setDblIdProtocolo($objProcedimentoDTO->getDblIdProcedimento());
          $objAcessoDTO->setNumIdUnidade($objUnidadeDTO->getNumIdUnidade());

          $objAcessoRN = new AcessoRN();
          if ($objAcessoRN->contar($objAcessoDTO)==0) {
            $objInfraException->adicionarValidacao('Unidade ['.$objUnidadeDTO->getStrSigla().'] n�o possui acesso ao processo ['.$objProcedimentoDTO->getStrProtocoloProcedimentoFormatado().'].');
          }
        }

        $objPesquisaPendenciaDTO = new PesquisaPendenciaDTO();
        $objPesquisaPendenciaDTO->setDblIdProtocolo(array($objProcedimentoDTO->getDblIdProcedimento()));
        $objPesquisaPendenciaDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
        $objPesquisaPendenciaDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        
        if($retransmissao){
            $objAtividadeRN->setStatusPesquisa(false);
            
        }
        
        $objAtividadeDTO2 = new AtividadeDTO();
        $objAtividadeDTO2->setDblIdProtocolo($objProcedimentoDTO->getDblIdProcedimento());
        $objAtividadeDTO2->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objAtividadeDTO2->setDthConclusao(null);
        
        
        if ($objAtividadeRN->contarRN0035($objAtividadeDTO2) == 0) {

          //reabertura autom�tica
          $objReabrirProcessoDTO = new ReabrirProcessoDTO();
          $objReabrirProcessoDTO->setDblIdProcedimento($objAtividadeDTO2->getDblIdProtocolo());
          $objReabrirProcessoDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
          $objReabrirProcessoDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
          $objProcedimentoRN->reabrirRN0966($objReabrirProcessoDTO);
          
        } 
        
        //$objPenAtividadeRN = new PenAtividadeRN();
        $arrObjProcedimentoDTO = $objAtividadeRN->listarPendenciasRN0754($objPesquisaPendenciaDTO);
        
        $objInfraException->lancarValidacoes();
        
        
        $objEnviarProcessoDTO = new EnviarProcessoDTO();
        $objEnviarProcessoDTO->setArrAtividadesOrigem($arrObjProcedimentoDTO[0]->getArrObjAtividadeDTO());

        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->setDblIdProtocolo($objProcedimentoDTO->getDblIdProcedimento());
        $objAtividadeDTO->setNumIdUsuario(null);
        $objAtividadeDTO->setNumIdUsuarioOrigem(SessaoSEI::getInstance()->getNumIdUsuario());
        $objAtividadeDTO->setNumIdUnidade($objUnidadeDTO->getNumIdUnidade());
        $objAtividadeDTO->setNumIdUnidadeOrigem(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objEnviarProcessoDTO->setArrAtividades(array($objAtividadeDTO));    

        $objEnviarProcessoDTO->setStrSinManterAberto('N');
        $strEnviaEmailNotificacao = ($this->objInfraParametro->getValor('PEN_ENVIA_EMAIL_NOTIFICACAO_RECEBIMENTO', false) == 'S') ? 'S' : 'N';
        $objEnviarProcessoDTO->setStrSinEnviarEmailNotificacao($strEnviaEmailNotificacao);
        $objEnviarProcessoDTO->setStrSinRemoverAnotacoes('S');
        $objEnviarProcessoDTO->setDtaPrazo(null);
        $objEnviarProcessoDTO->setNumDias(null);
        $objEnviarProcessoDTO->setStrSinDiasUteis('N');
        
        $objAtividadeRN->enviarRN0023($objEnviarProcessoDTO);
        
      }

      /* Essa � a fun��o est�tica de compara��o */
      static function comparacaoOrdemDocumentos($parDocumento1, $parDocumento2)
      {
        $numOrdemDocumento1 = strtolower($parDocumento1->ordem);
        $numOrdemDocumento2 = strtolower($parDocumento2->ordem);        
        return $numOrdemDocumento1 - $numOrdemDocumento2;         
      }    
      
      /**/
      protected function receberTramitesRecusados($parNumIdentificacaoTramite) {

        if(empty($parNumIdentificacaoTramite)) {
            throw new InfraException('Par�metro $parNumIdentificacaoTramite n�o informado.');
        }

        $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
        $parNumIdRespositorio = $objInfraParametro->getValor('PEN_ID_REPOSITORIO_ORIGEM');
        $parNumIdEstrutura = SessaoSEI::getInstance()->getNumIdUnidadeAtual();
        
        $arrObjTramite = (array)$this->objProcessoEletronicoRN->consultarTramitesRecusados($parNumIdRespositorio, $parNumIdEstrutura);
        
        if(empty($arrObjTramite)) {    
            return null;
        }
        
        foreach($arrObjTramite as $objTramite) {
                        
            $strNumeroRegistro = $objTramite->NRE;
            
            if(empty($strNumeroRegistro)) {
                throw new InfraException('Falha ao consultar n�mero do registro na lista de tramites recusados');
            }
            
            $objReceberTramiteRecusadoDTO = new ReceberTramiteRecusadoDTO();
            $objReceberTramiteRecusadoDTO->retTodos();
            $objReceberTramiteRecusadoDTO->setNumRegistro($strNumeroRegistro);

            $objReceberTramiteRecusadoBD = new ReceberTramiteRecusadoBD(BancoSEI::getInstance());
            if($objReceberTramiteRecusadoBD->contar($objReceberTramiteRecusadoDTO) > 0){
                // J� foi cadastrado no banco de dados, ent�o j� foi modificado para normal
                continue;
            }
            
            // Muda o estado de em processamento para bloqueado
            try {
                $objProcessoEletronicoDTO = new ProcessoEletronicoDTO();
                $objProcessoEletronicoDTO->setStrNumeroRegistro($strNumeroRegistro);
                $objProcessoEletronicoDTO->retDblIdProcedimento();

                $objProcessoEletronicoDB = new ProcessoEletronicoBD(BancoSEI::getInstance());
                $objProcessoEletronicoDTO = $objProcessoEletronicoDB->consultar($objProcessoEletronicoDTO);

                $objProtocoloDTO = new ProtocoloDTO();
                $objProtocoloDTO->retTodos();
                $objProtocoloDTO->setDblIdProtocolo($objProcessoEletronicoDTO->getDblIdProcedimento());

                $objProtocoloBD = new ProtocoloBD(BancoSEI::getInstance());
                $objProtocoloDTO = $objProtocoloBD->consultar($objProtocoloDTO);

                $objProtocoloDTO->setStrStaProtocolo(ProtocoloRN::$TE_NORMAL);
                $objProtocoloBD->alterar($objProtocoloDTO);
                
                // Cadastra na tabela de hist�rico de 
                $objReceberTramiteRecusadoDTO = new ReceberTramiteRecusadoDTO();
                $objReceberTramiteRecusadoDTO->setNumRegistro($strNumeroRegistro);
                $objReceberTramiteRecusadoDTO->setDblIdTramite($objTramite->IDT);

                $objReceberTramiteRecusadoBD->cadastrar($objReceberTramiteRecusadoDTO);
            }
            catch(Exception $e) {

                $strMessage = 'Falha ao mudar o estado do procedimento ao receber a lista de tramites recusados.';

                LogSEI::getInstance()->gravar($strMessage.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
                throw new InfraException($strMessage, $e);
            }
        }
    }
    
    /**
     * M�todo que realiza a valida��o da extens�o dos componentes digitais a serem recebidos 
     * 
     * @param integer $parIdTramite
     * @param object $parObjProcesso
     * @throws InfraException
     */
    public function validarExtensaoComponentesDigitais($parIdTramite, $parObjProcesso){
        
        //Armazena o array de documentos
        $arrDocumentos = is_array($parObjProcesso->documento) ? $parObjProcesso->documento : array($parObjProcesso->documento) ;
        
        //Instancia o bd do arquivoExtens�o 
        $arquivoExtensaoBD = new ArquivoExtensaoBD($this->getObjInfraIBanco());
        
        //Percorre os documentos
        foreach($arrDocumentos as $documento){
            
            //Busca o nome do documento 
            $nomeDocumento = $documento->componenteDigital->nome;
            
            //Busca pela extens�o do documento
            $arrNomeDocumento = explode('.', $nomeDocumento);
            $extDocumento = $arrNomeDocumento[count($arrNomeDocumento) - 1];
            
            //Verifica se a extens�o do arquivo est� cadastrada e ativa 
            $arquivoExtensaoDTO = new ArquivoExtensaoDTO();
            $arquivoExtensaoDTO->setStrSinAtivo('S');
            $arquivoExtensaoDTO->setStrExtensao($extDocumento);
            $arquivoExtensaoDTO->retStrExtensao();
            
            if($arquivoExtensaoBD->contar($arquivoExtensaoDTO) == 0){
                $this->objProcessoEletronicoRN->recusarTramite($parIdTramite, 'Componentes digitais com formato inv�lido no destinat�rio. ', ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_FORMATO);
                throw new InfraException("Processo recusado devido a exist�ncia de documento em formato {$extDocumento} n�o permitido pelo sistema. ");
            }
            
            
        }
    }
    
    /**
     * M�todo que verifica as permiss�es de escrita nos diret�rios utilizados no recebimento de processos e documentos
     * 
     * @param integer $parIdTramite
     * @throws InfraException
     */
    public function verificarPermissoesDiretorios($parIdTramite){
        
        //Verifica se o usu�rio possui permiss�es de escrita no reposit�rio de arquivos externos
        if(!is_writable(ConfiguracaoSEI::getInstance()->getValor('SEI', 'RepositorioArquivos'))){
            
            $this->objProcessoEletronicoRN->recusarTramite($parIdTramite, 'O sistema n�o possui permiss�o de escrita no diret�rio de armazenamento de documentos externos', ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_OUTROU);
            throw new InfraException('O sistema n�o possui permiss�o de escrita no diret�rio de armazenamento de documentos externos');
            
        }
        
        //Verifica se o usu�rio possui permiss�es de escrita no diret�rio tempor�rio de arquivos
        if(!is_writable(DIR_SEI_TEMP)){
            
            $this->objProcessoEletronicoRN->recusarTramite($parIdTramite, 'O sistema n�o possui permiss�o de escrita no diret�rio de armazenamento de arquivos tempor�rios do sistema.', ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_OUTROU);
            throw new InfraException('O sistema n�o possui permiss�o de escrita no diret�rio de armazenamento de arquivos tempor�rios do sistema.');
            
        }
        
        
    }
}