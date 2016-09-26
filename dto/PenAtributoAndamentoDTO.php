<?php

/**
 * @author Join Tecnologia
 */
require_once dirname(__FILE__) . '/../../../SEI.php';

class PenAtributoAndamentoDTO extends AtributoAndamentoDTO {

    public function montar() {

        parent::montar();

        $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_DTH, 'ConclusaoAtividade', 'dth_conclusao', 'atividade');
        $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'ProtocoloFormatadoAtividade', 'protocolo_formatado', 'protocolo');
        $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'EstadoProtocoloAtividade', 'sta_estado', 'protocolo');

        $this->configurarFK('IdProtocoloAtividade', 'protocolo', 'id_protocolo');
    }
}