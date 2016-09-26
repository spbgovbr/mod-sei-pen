<?php
require_once dirname(__FILE__) . '/../../../SEI.php';

class ReceberComponenteDigitalRN extends InfraRN
{
    private $objProcessoEletronicoRN;
    private $objInfraParametro;
    private $arrAnexos = array();
    
    public function __construct()
    {
        parent::__construct();

        $this->objInfraParametro = new InfraParametro(BancoSEI::getInstance());
        $this->objProcessoEletronicoRN = new ProcessoEletronicoRN();
    }

    public function setArrAnexos($arrAnexos){
        $this->arrAnexos = $arrAnexos;
    }
    
    public function getArrAnexos(){
        return $this->arrAnexos;
    }
    
    protected function inicializarObjInfraIBanco()
    {
        return BancoSEI::getInstance();
    }

    //TODO: Implementar o recebimento fracionado dos componentes digitais
    protected function receberComponenteDigitalConectado(ComponenteDigitalDTO $parObjComponenteDigitalDTO)
    {
        if(!isset($parObjComponenteDigitalDTO) || !isset($parObjComponenteDigitalDTO)) {
            throw new InfraException('Par�metro $parObjComponenteDigitalDTO n�o informado.');
        }

        //Obter os dados do componente digital
//        $objComponenteDigital = $this->objProcessoEletronicoRN->receberComponenteDigital(
//            $parObjComponenteDigitalDTO->getNumIdTramite(), 
//            $parObjComponenteDigitalDTO->getStrHashConteudo(), 
//            $parObjComponenteDigitalDTO->getStrProtocolo());

//        if(!isset($objComponenteDigital) || InfraString::isBolVazia($objComponenteDigital->conteudoDoComponenteDigital)) {
//            throw new InfraException("N�o foi poss�vel obter informa��es do componente digital identificado (".$parObjComponenteDigitalDTO->getStrHashConteudo().")");
//        }

        //Copiar dados dos componentes digitais para o diret�rio de upload
//        $objAnexoDTO = $this->copiarComponenteDigitalPastaTemporaria($objComponenteDigital);
        
        
        
        $objAnexoDTO = $this->arrAnexos[$parObjComponenteDigitalDTO->getStrHashConteudo()];
        
        //Validar o hash do documento recebido com os dados informados pelo remetente
        //$this->validarIntegridadeDoComponenteDigital($objAnexoDTO, $parObjComponenteDigitalDTO);

        //Transaferir documentos validados para o reposit�rio final de arquivos
        $this->cadastrarComponenteDigital($parObjComponenteDigitalDTO, $objAnexoDTO);

        //Registrar anexo relacionado com o componente digital
        $this->registrarAnexoDoComponenteDigital($parObjComponenteDigitalDTO, $objAnexoDTO);
    }

    private function registrarAnexoDoComponenteDigital($parObjComponenteDigitalDTO, $parObjAnexoDTO)
    {
        $objComponenteDigitalDTO = new ComponenteDigitalDTO();
        $objComponenteDigitalDTO->setNumIdTramite($parObjComponenteDigitalDTO->getNumIdTramite());
        $objComponenteDigitalDTO->setStrNumeroRegistro($parObjComponenteDigitalDTO->getStrNumeroRegistro());
        $objComponenteDigitalDTO->setDblIdDocumento($parObjComponenteDigitalDTO->getDblIdDocumento());

        $objComponenteDigitalDTO->setNumIdAnexo($parObjAnexoDTO->getNumIdAnexo());

        $objComponenteDigitalBD = new ComponenteDigitalBD($this->getObjInfraIBanco());
        $objComponenteDigitalDTO = $objComponenteDigitalBD->alterar($objComponenteDigitalDTO);
    }

    public function copiarComponenteDigitalPastaTemporaria($objComponenteDigital)
    {
        $objAnexoRN = new AnexoRN();
        $strNomeArquivoUpload = $objAnexoRN->gerarNomeArquivoTemporario();
        $strConteudoCodificado = $objComponenteDigital->conteudoDoComponenteDigital;
        $strNome = $objComponenteDigital->nome;
        
        
        $fp = fopen(DIR_SEI_TEMP.'/'.$strNomeArquivoUpload,'w');
        fwrite($fp,$strConteudoCodificado);
        fclose($fp);
                
        //Atribui informa��es do arquivo anexo
        $objAnexoDTO = new AnexoDTO();
        $objAnexoDTO->setNumIdAnexo($strNomeArquivoUpload);
        $objAnexoDTO->setDthInclusao(InfraData::getStrDataHoraAtual());
        $objAnexoDTO->setNumTamanho(filesize(DIR_SEI_TEMP.'/'.$strNomeArquivoUpload));
        $objAnexoDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());

        return $objAnexoDTO;
    }

    public function validarIntegridadeDoComponenteDigital(AnexoDTO $objAnexoDTO, $strHashConteudo, $parNumIdentificacaoTramite)
    {
        $strHashInformado = $strHashConteudo;
        $strHashInformado = base64_decode($strHashInformado);

        $objAnexoRN = new AnexoRN();
        $strCaminhoAnexo = DIR_SEI_TEMP.'/'.$objAnexoDTO->getNumIdAnexo();
        $strHashDoArquivo = hash_file("sha256", $strCaminhoAnexo, true);

        if(strcmp($strHashInformado, $strHashDoArquivo) != 0) {
            
            $this->objProcessoEletronicoRN->recusarTramite($parNumIdentificacaoTramite, "Hash do componente digital n�o confere com o valor informado pelo remetente.", ProcessoEletronicoRN::MTV_RCSR_TRAM_CD_CORROMPIDO);

            // Adiciono nos detalhes o nome do m�todo para poder manipular o cache
            throw new InfraException("Hash do componente digital n�o confere com o valor informado pelo remetente.", null, __METHOD__);            
        }
    }

    public function cadastrarComponenteDigital(ComponenteDigitalDTO $parObjComponenteDigitalDTO, AnexoDTO $parObjAnexoDTO)
    {
        //Obter dados do documento
        $objDocumentoDTO = new DocumentoDTO();
        $objDocumentoDTO->retDblIdDocumento();
        $objDocumentoDTO->retDblIdProcedimento();
        $objDocumentoDTO->setDblIdDocumento($parObjComponenteDigitalDTO->getDblIdDocumento());
        
        $objDocumentoRN = new DocumentoRN();
        $objDocumentoDTO = $objDocumentoRN->consultarRN0005($objDocumentoDTO);
        
        if ($objDocumentoDTO==null){
          throw new InfraException("Registro n�o encontrado.");
        }

        $objProtocoloDTO = new ProtocoloDTO();
        $objProtocoloDTO->retDblIdProtocolo();
        $objProtocoloDTO->retStrProtocoloFormatado();
        $objProtocoloDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

        $objProtocoloRN = new ProtocoloRN();
        $objProtocoloDTO = $objProtocoloRN->consultarRN0186($objProtocoloDTO);
            
        //Complementa informa��es do componente digital
        $parObjAnexoDTO->setStrNome($parObjComponenteDigitalDTO->getStrNome());
        
        $arrStrNome = explode('.',$parObjComponenteDigitalDTO->getStrNome());
        $strProtocoloFormatado = current($arrStrNome);
        
        $objDocumentoDTO->setObjProtocoloDTO($objProtocoloDTO);
        $objProtocoloDTO->setArrObjAnexoDTO(array($parObjAnexoDTO));
        $objDocumentoDTO = $objDocumentoRN->alterarRecebidoRN0992($objDocumentoDTO);
        
        // @join_tec US029 (#3790)
        /*$objObservacaoDTO = new ObservacaoDTO();
        $objObservacaoDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProtocolo());
        $objObservacaoDTO->setStrDescricao(sprintf('N�mero SEI do Documento na Origem: %s', $strProtocoloFormatado));
        $objObservacaoDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        
        $objObservacaoBD = new ObservacaoRN();
        $objObservacaoBD->cadastrarRN0222($objObservacaoDTO);*/
    }
}