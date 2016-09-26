<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

/**
 * 
 * @author Join Tecnologia
 */
class PenTramiteProcessadoDTO extends InfraDTO {
    
    public function getStrNomeTabela() {
        return 'md_pen_tramite_processado';        
    }
    
    public function montar() {
        
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DBL, 'IdTramite', 'id_tramite');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DTH, 'Ultimo', 'dth_ultimo_processamento');
	$this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'Tentativas', 'numero_tentativas');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Recebido', 'sin_recebimento_concluido');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Tipo', 'tipo_tramite_processo');

        $this->configurarPK('IdTramite', InfraDTO::$TIPO_PK_INFORMADO);
    }
}
