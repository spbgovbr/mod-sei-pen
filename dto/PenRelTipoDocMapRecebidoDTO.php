<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

class PenRelTipoDocMapRecebidoDTO extends InfraDTO {

    public function getStrNomeTabela() {
        return 'md_pen_rel_doc_map_recebido';
    }

    public function montar() {
        
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DBL, 'IdMap', 'id_mapeamento');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'CodigoEspecie', 'codigo_especie');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdSerie', 'id_serie');
        $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Padrao', 'sin_padrao');
        
        $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'NomeSerie', 'nome', 'serie');
        $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR, 'NomeEspecie','nome_especie', 'md_pen_especie_documental');
        
        $this->configurarPK('IdMap', InfraDTO::$TIPO_PK_SEQUENCIAL);
        $this->configurarFK('IdSerie', 'serie', 'id_serie');
        $this->configurarFK('CodigoEspecie', 'md_pen_especie_documental', 'id_especie');
    }
}
