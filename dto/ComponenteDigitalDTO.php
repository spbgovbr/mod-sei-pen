<?php

require_once dirname(__FILE__).'/../../../SEI.php';

class ComponenteDigitalDTO extends InfraDTO {

  public function getStrNomeTabela() {
     return 'md_pen_componente_digital';
  }

  public function montar() {
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'NumeroRegistro', 'numero_registro');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DBL, 'IdProcedimento', 'id_procedimento');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DBL, 'IdDocumento', 'id_documento');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdTramite', 'id_tramite');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdAnexo', 'id_anexo');

    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Nome', 'nome');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'HashConteudo', 'hash_conteudo');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Protocolo', 'protocolo');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'AlgoritmoHash', 'algoritmo_hash');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'TipoConteudo', 'tipo_conteudo');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'MimeType', 'mime_type');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'DadosComplementares', 'dados_complementares');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'Tamanho', 'tamanho');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'Ordem', 'ordem');
    $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'SinEnviar', 'sin_enviar');    

    $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM, 'TicketEnvioComponentes', 'ticket_envio_componentes', 'md_pen_tramite');
    $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'ConteudoAssinaturaDocumento', 'conteudo_assinatura', 'documento_conteudo');    
    $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'ProtocoloDocumentoFormatado', 'protocolo_formatado', 'protocolo');

    $this->configurarPK('NumeroRegistro', InfraDTO::$TIPO_PK_INFORMADO);
    $this->configurarPK('IdDocumento', InfraDTO::$TIPO_PK_INFORMADO);

    $this->configurarFK('NumeroRegistro', 'md_pen_tramite', 'numero_registro', InfraDTO::$TIPO_FK_OBRIGATORIA);
    $this->configurarFK('IdTramite', 'md_pen_tramite', 'id_tramite', InfraDTO::$TIPO_FK_OBRIGATORIA);  
    $this->configurarFK('IdDocumento', 'documento', 'id_documento', InfraDTO::$TIPO_FK_OBRIGATORIA);
    $this->configurarFK('IdDocumento', 'protocolo', 'id_protocolo', InfraDTO::$TIPO_FK_OBRIGATORIA);
    $this->configurarFK('IdDocumento', 'documento_conteudo', 'id_documento', InfraDTO::$TIPO_FK_OBRIGATORIA);
  }
}