<?php
/**
 * Atualizador do sistema SEI para instalar/atualizar o m�dulo PEN
 * 
 * @author Join Tecnologia
 */
class PenAtualizarSeiRN extends PenAtualizadorRN {

    protected $versaoMinRequirida = '2.6.0';
    protected $sei_versao = 'PEN_VERSAO_MODULO_SEI';
    
    protected function inicializarObjInfraIBanco() {
        if(empty($this->objBanco)) {
            
            $this->objBanco = BancoSEI::getInstance();
        }
        return $this->objBanco;
    }
    
    protected function instalarV001(){
        
        $objInfraBanco = $this->inicializarObjInfraIBanco();
        $objMetaBD = $this->inicializarObjMetaBanco();

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_processo_eletronico',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_procedimento' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro'),
            'uk' => array('numero_registro', 'id_procedimento'),
            'fks' => array(
                'procedimento' => array('id_procedimento', 'id_procedimento')
            )
        ));
        
        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_tramite',
            'cols' => array(
                'numero_registro' => array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'ticket_envio_componentes'=> array($objMetaBD->tipoTextoGrande(), PenMetaBD::SNULLO),
                'dth_registro'=> array($objMetaBD->tipoDataHora(), PenMetaBD::SNULLO),
                'id_andamento'=> array($objMetaBD->tipoNumero(), PenMetaBD::SNULLO),
                'id_usuario'=> array($objMetaBD->tipoNumero(), PenMetaBD::SNULLO),
                'id_unidade'=> array($objMetaBD->tipoNumero(), PenMetaBD::SNULLO)
            ),
            'pk' => array('id_tramite'),
            'uk' => array('numero_registro', 'id_tramite'),
            'fks' => array(
                'md_pen_processo_eletronico' => array('numero_registro', 'numero_registro'),
                'usuario' => array('id_usuario', 'id_usuario'),
                'unidade' => array('id_unidade', 'id_unidade')
            )
        ));
         
        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_especie_documental',
            'cols' => array(
                'id_especie'=> array($objMetaBD->tipoNumero(16), PenMetaBD::NNULLO),
                'nome_especie'=> array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO),
                'descricao'=> array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_especie')
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_tramite_pendente',
            'cols' => array(
                'id'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'numero_tramite'=> array($objMetaBD->tipoTextoVariavel(255)),
                'id_atividade_expedicao'=> array($objMetaBD->tipoNumero(), PenMetaBD::SNULLO)
            ),
            'pk' => array('id')
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_tramite_recibo_envio',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'dth_recebimento' => array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'hash_assinatura' => array($objMetaBD->tipoTextoVariavel(345), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro', 'id_tramite')   
        ));


        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_procedimento_andamento',
            'cols' => array(
                'id_andamento'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'id_procedimento' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'situacao' => array($objMetaBD->tipoTextoFixo(1), 'N'),
                'data' => array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'mensagem' => array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO),
                'hash' => array($objMetaBD->tipoTextoFixo(32), PenMetaBD::NNULLO),
                'id_tarefa' => array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_andamento')   
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_protocolo',
            'cols' => array(
                'id_protocolo'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'sin_obteve_recusa' => array($objMetaBD->tipoTextoFixo(1), 'N')
            ),
            'pk' => array('id_protocolo'),
            'fks' => array(
                'protocolo' => array('id_protocolo', 'id_protocolo')
            )
        ));

   /*     $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_tramite_recusado',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_tramite')
        ));*/

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_recibo_tramite',
            'cols' => array(
                'numero_registro' => array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'dth_recebimento' => array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'hash_assinatura' => array($objMetaBD->tipoTextoVariavel(345), PenMetaBD::NNULLO),
                'cadeia_certificado' => array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro', 'id_tramite'),
            'fks' => array(
                'md_pen_tramite' => array(array('numero_registro', 'id_tramite'), array('numero_registro', 'id_tramite'))
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_recibo_tramite_enviado',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'dth_recebimento'=> array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'hash_assinatura'=> array($objMetaBD->tipoTextoVariavel(345), PenMetaBD::NNULLO),
                'cadeia_certificado ' => array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro', 'id_tramite'),
            'fks' => array(
                'md_pen_tramite' => array(array('numero_registro', 'id_tramite'), array('numero_registro', 'id_tramite'))
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_recibo_tramite_recebido',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_tramite' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'dth_recebimento'=> array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'hash_assinatura'=> array($objMetaBD->tipoTextoVariavel(345), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro', 'id_tramite', 'hash_assinatura'),
            'fks' => array(
                'md_pen_tramite' => array(array('numero_registro', 'id_tramite'), array('numero_registro', 'id_tramite'))
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_processo_apensado',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_procedimento_apensado' => array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'protocolo'=> array($objMetaBD->tipoTextoVariavel(50), PenMetaBD::NNULLO)
            ),
            'pk' => array('numero_registro', 'id_procedimento_apensado'),
            'fks' => array(
                'md_pen_processo_eletronico' => array('numero_registro', 'numero_registro')
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_serie_especie',
            'cols' => array(
                'codigo_especie'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'id_serie' => array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_padrao'=> array($objMetaBD->tipoTextoFixo(1), 'N')
            ),
            'pk' => array('id_serie'),
            'uk' => array('codigo_especie', 'id_serie'),
            'fks' => array(
                'serie' => array('id_serie', 'id_serie')
            )
        ));
        
        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_tarefa_operacao',
            'cols' => array(
                'id_tarefa'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'codigo_operacao'=> array($objMetaBD->tipoTextoFixo(2), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_tarefa', 'codigo_operacao'),
            'fks' => array(
                'tarefa' => array('id_tarefa', 'id_tarefa')
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_tipo_documento_mapeamento_recebido',
            'cols' => array(
                'codigo_especie'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'id_serie'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_padrao'=> array($objMetaBD->tipoTextoFixo(2), PenMetaBD::NNULLO)
            ),
            'pk' => array('codigo_especie', 'id_serie'),
            'fks' => array(
                'serie' => array('id_serie', 'id_serie')
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_componente_digital',
            'cols' => array(
                'numero_registro'=> array($objMetaBD->tipoTextoFixo(16), PenMetaBD::NNULLO),
                'id_procedimento'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'id_documento'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'id_tramite'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'id_anexo'=> array($objMetaBD->tipoNumero(), PenMetaBD::SNULLO),
                'protocolo'=> array($objMetaBD->tipoTextoVariavel(50), PenMetaBD::NNULLO),
                'nome'=> array($objMetaBD->tipoTextoVariavel(100), PenMetaBD::NNULLO),
                'hash_conteudo'=> array($objMetaBD->tipoTextoVariavel(255), PenMetaBD::NNULLO),
                'algoritmo_hash'=> array($objMetaBD->tipoTextoVariavel(20), PenMetaBD::NNULLO),
                'tipo_conteudo'=> array($objMetaBD->tipoTextoFixo(3), PenMetaBD::NNULLO),
                'mime_type'=> array($objMetaBD->tipoTextoVariavel(100), PenMetaBD::NNULLO),
                'dados_complementares'=> array($objMetaBD->tipoTextoVariavel(1000), PenMetaBD::SNULLO),
                'tamanho'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'ordem'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_enviar'=> array($objMetaBD->tipoTextoFixo(1), 'N')
            ),
            'pk' => array('numero_registro', 'id_procedimento', 'id_documento', 'id_tramite'),
            'fks' => array(
                'anexo' => array('id_anexo', 'id_anexo'),
                'documento' => array('id_documento', 'id_documento'),
                'procedimento' => array('id_procedimento', 'id_procedimento'),
                'md_pen_processo_eletronico' => array('numero_registro', 'numero_registro'),
                'md_pen_tramite' => array(array('numero_registro', 'id_tramite'), array('numero_registro', 'id_tramite'))
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_unidade',
            'cols' => array(
                'id_unidade'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'id_unidade_rh'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_unidade'),
            'fks' => array(
                'unidade' => array('id_unidade', 'id_unidade')
            )
        ));
        
        //----------------------------------------------------------------------
        // Novas sequ�ncias
        //----------------------------------------------------------------------
        $objInfraSequencia = new InfraSequencia($objInfraBanco);
        
        if(!$objInfraSequencia->verificarSequencia('md_pen_procedimento_andamento')){
            
            $objInfraSequencia->criarSequencia('md_pen_procedimento_andamento', '1', '1', '9999999999');
        }
        
        if(!$objInfraSequencia->verificarSequencia('md_pen_tramite_pendente')){

            $objInfraSequencia->criarSequencia('md_pen_tramite_pendente', '1', '1', '9999999999');
        }
        //----------------------------------------------------------------------
        // Par�metros
        //----------------------------------------------------------------------
        
        $objInfraParametro = new InfraParametro($objInfraBanco);

        $objInfraParametro->setValor('PEN_ID_REPOSITORIO_ORIGEM', '');
        $objInfraParametro->setValor('PEN_ENDERECO_WEBSERVICE', '');
        $objInfraParametro->setValor('PEN_SENHA_CERTIFICADO_DIGITAL', '1234');
        $objInfraParametro->setValor('PEN_TIPO_PROCESSO_EXTERNO', '100000320');
        $objInfraParametro->setValor('PEN_ENVIA_EMAIL_NOTIFICACAO_RECEBIMENTO', 'N');
        $objInfraParametro->setValor('PEN_ENDERECO_WEBSERVICE_PENDENCIAS', '');
        $objInfraParametro->setValor('PEN_UNIDADE_GERADORA_DOCUMENTO_RECEBIDO', '');
        $objInfraParametro->setValor('PEN_LOCALIZACAO_CERTIFICADO_DIGITAL', '');
   
        //----------------------------------------------------------------------
        // Especie de Documento
        //----------------------------------------------------------------------
        
        $objBD = new GenericoBD($this->inicializarObjInfraIBanco());
        $objDTO = new EspecieDocumentalDTO();

        $fnCadastrar = function($dblIdEspecie, $strNomeEspecie, $strDescricao) use($objDTO, $objBD){
           
            $objDTO->unSetTodos();
            $objDTO->setStrNomeEspecie($strNomeEspecie);

            if($objBD->contar($objDTO) == 0){  
                $objDTO->setDblIdEspecie($dblIdEspecie);
                $objDTO->setStrDescricao($strDescricao);
                $objBD->cadastrar($objDTO);
            }   
        };

        $fnCadastrar(1,'Abaixo-assinado','Podendo ser complementado: de Reivindica��o');
        $fnCadastrar(2,'Ac�rd�o','Expressa decis�o proferida pelo Conselho Diretor, n�o abrangida pelos demais instrumentos deliberativos anteriores.');
        $fnCadastrar(3,'Acordo','Podendo ser complementado: de N�vel de Servi�o; Coletivo de Trabalho');
        $fnCadastrar(4,'Alvar�','Podendo ser complementado: de Funcionamento; Judicial');
        $fnCadastrar(5,'Anais','Podendo ser complementado: de Eventos; de Engenharia');
        $fnCadastrar(6,'Anteprojeto','Podendo ser complementado: de Lei');
        $fnCadastrar(7,'Ap�lice','Podendo ser complementado: de Seguro');
        $fnCadastrar(8,'Apostila','Podendo ser complementado: de Curso');
        $fnCadastrar(9,'Ata','Como Documento Externo pode ser complementado: de Reuni�o; de Realiza��o de Preg�o');
        $fnCadastrar(10,'Atestado','Podendo ser complementado: M�dico; de Comparecimento; de Capacidade T�cnica');
        $fnCadastrar(11,'Ato','Expressa decis�o sobre outorga, expedi��o, modifica��o, transfer�ncia, prorroga��o, adapta��o e extin��o de concess�es, permiss�es e autoriza��es para explora��o de servi�os, uso de recursos escassos e explora��o de sat�lite, e Chamamento P�blico.');
        $fnCadastrar(12,'Auto','Podendo ser complementado: de Vistoria; de Infra��o');
        $fnCadastrar(13,'Aviso','Podendo ser complementado: de Recebimento; de Sinistro; de F�rias');
        $fnCadastrar(14,'Balancete','Podendo ser complementado: Financeiro');
        $fnCadastrar(15,'Balan�o','Podendo ser complementado: Patrimonial - BP; Financeiro');
        $fnCadastrar(16,'Bilhete','Podendo ser complementado: de Pagamento; de Loteria');
        $fnCadastrar(17,'Boletim','Podendo ser complementado: de Ocorr�ncia; Informativo');
        $fnCadastrar(18,'Carta','Podendo ser complementado: Convite');
        $fnCadastrar(19,'Cartaz','Podendo ser complementado: de Evento');
        $fnCadastrar(20,'C�dula','Podendo ser complementado: de Identidade; de Cr�dito Banc�rio; de Cr�dito Comercial; de Cr�dito Imobili�rio');
        $fnCadastrar(21,'Certid�o','Como Documento Externo pode ser complementado: de Tempo de Servi�o; de Nascimento; de Casamento; de �bito; Negativa de Fal�ncia ou Concordata; Negativa de D�bitos Trabalhistas; Negativa de D�bitos Tribut�rios');
        $fnCadastrar(22,'Certificado','Podendo ser complementado: de Conclus�o de Curso; de Calibra��o de Equipamento; de Marca');
        $fnCadastrar(23,'Cheque','Podendo ser complementado: Cau��o');
        $fnCadastrar(24,'Comprovante','Podendo ser complementado: de Despesa; de Rendimento; de Resid�ncia; de Matr�cula; de Uni�o Est�vel');
        $fnCadastrar(25,'Comunicado','Expediente interno entre uma unidade administrativa e um servidor ou entre um servidor e uma unidade administrativa de um mesmo �rg�o p�blico.');
        $fnCadastrar(26,'Consulta','Podendo ser complementado: P�blica; Interna');
        $fnCadastrar(27,'Contracheque','Esp�cie pr�pria');
        $fnCadastrar(28,'Contrato','Como Documento Externo pode ser complementado: Social');
        $fnCadastrar(29,'Conv�nio','Esp�cie pr�pria');
        $fnCadastrar(30,'Convite','Podendo ser complementado: de Reuni�o; para Evento; de Casamento');
        $fnCadastrar(31,'Conven��o','Podendo ser complementado: Coletiva de Trabalho; Internacional');
        $fnCadastrar(32,'Crach�','Podendo ser complementado: de Identifica��o; de Evento');
        $fnCadastrar(33,'Cronograma','Podendo ser complementado: de Projeto; de Estudos');
        $fnCadastrar(34,'Curr�culo','Podendo ser complementado: de Candidato');
        $fnCadastrar(35,'Deb�nture','Esp�cie pr�pria');
        $fnCadastrar(36,'Decis�o','Podendo ser complementado: Administrativa; Judicial');
        $fnCadastrar(37,'Declara��o','Como Documento Externo pode ser complementado: de Imposto de Renda; de Conformidade; de Responsabilidade T�cnica; de Acumula��o de Aposentadoria; de Acumula��o de Cargos; de Informa��es Econ�mico-Fiscais da Pessoa Jur�dica $fnCadastrar(DIPJ);');
        $fnCadastrar(38,'Decreto','Esp�cie pr�pria');
        $fnCadastrar(39,'Delibera��o','Podendo ser complementado: de Recursos; do Conselho');
        $fnCadastrar(40,'Demonstrativo','Podendo ser complementado: Financeiro; de Pagamento; de Arrecada��o');
        $fnCadastrar(41,'Depoimento','Podendo ser complementado: das Testemunhas');
        $fnCadastrar(42,'Despacho','Esp�cie pr�pria');
        $fnCadastrar(43,'Di�rio','Podendo ser complementado: de Justi�a; Oficial');
        $fnCadastrar(44,'Diploma','Podendo ser complementado: de Conclus�o de Curso');
        $fnCadastrar(45,'Diretriz','Podendo ser complementado: Or�ament�ria');
        $fnCadastrar(46,'Disserta��o','Podendo ser complementado: de Mestrado');
        $fnCadastrar(47,'Dossi�','Podendo ser complementado: de Processo; T�cnico');
        $fnCadastrar(48,'Edital','Podendo ser complementado: de Convoca��o; de Intima��o; de Lan�amento');
        $fnCadastrar(49,'E-mail','Indicado nos Par�metros para corresponder ao envio de Correspond�ncia Eletr�nica do SEI');
        $fnCadastrar(50,'Embargos','Podendo ser complementado: de Declara��o; de Execu��o ou Infringentes');
        $fnCadastrar(51,'Emenda','Podendo ser complementado: Constitucional; de Comiss�o; de Bancada; de Relatoria');
        $fnCadastrar(52,'Escala','Podendo ser complementado: de F�rias');
        $fnCadastrar(53,'Escritura','Podendo ser complementado: P�blica; de Im�vel');
        $fnCadastrar(54,'Estatuto','Podendo ser complementado: Social');
        $fnCadastrar(55,'Exposi��o de Motivos','Esp�cie pr�pria');
        $fnCadastrar(56,'Extrato','Podendo ser complementado: de Sistemas; Banc�rio');
        $fnCadastrar(57,'Fatura','Esp�cie pr�pria');
        $fnCadastrar(58,'Ficha','Podendo ser complementado: de Cadastro; de Inscri��o');
        $fnCadastrar(59,'Fluxograma','Podendo ser complementado: de Processo; de Documentos; de Blocos');
        $fnCadastrar(60,'Folha','Podendo ser complementado: de Frequ�ncia de Estagi�rio; de Frequ�ncia de Servidor');
        $fnCadastrar(61,'Folheto/Folder','Podendo ser complementado: de Evento');
        $fnCadastrar(62,'Formul�rio','Podendo ser complementado: de Contato; de Revis�o');
        $fnCadastrar(63,'Grade Curricular','Podendo ser complementado: do Curso');
        $fnCadastrar(64,'Guia','Podendo ser complementado: de Recolhimento da Uni�o');
        $fnCadastrar(65,'Hist�rico','Podendo ser complementado: Escolar');
        $fnCadastrar(66,'Indica��o','Esp�cie pr�pria utilizada pelo Poder Legislativo');
        $fnCadastrar(67,'Informe','Como Documento Externo pode ser complementado: de Rendimentos');
        $fnCadastrar(68,'Instru��o','Podendo ser complementado: Normativa');
        $fnCadastrar(69,'Invent�rio','Podendo ser complementado: de Estoque; Extrajudicial; Judicial; em Cart�rio');
        $fnCadastrar(70,'Laudo','Podendo ser complementado: M�dico; Conclusivo');
        $fnCadastrar(71,'Lei','Podendo ser complementado: Complementar');
        $fnCadastrar(72,'Lista/Listagem','Podendo ser complementado: de Presen�a');
        $fnCadastrar(73,'Livro','Podendo ser complementado: Caixa');
        $fnCadastrar(74,'Mandado','Podendo ser complementado: de Busca e Apreens�o; de Cita��o; de Intima��o');
        $fnCadastrar(75,'Manifesto','Esp�cie pr�pria');
        $fnCadastrar(76,'Manual','Podendo ser complementado: do Usu�rio; do Sistema; do Equipamento');
        $fnCadastrar(77,'Mapa','Podendo ser complementado: de Ruas; de Risco');
        $fnCadastrar(78,'Medida Provis�ria','Esp�cie pr�pria');
        $fnCadastrar(79,'Memorando','Como Documento Externo pode ser complementado: de Entendimento');
        $fnCadastrar(80,'Memorando-circular','Mesma defini��o do Memorando com apenas uma diferen�a: � encaminhado simultaneamente a mais de um cargo.');
        $fnCadastrar(81,'Memorial','Podendo ser complementado: Descritivo; de Incorpora��o');
        $fnCadastrar(82,'Mensagem','Podendo ser complementado: de Anivers�rio; de Boas Vindas');
        $fnCadastrar(83,'Minuta','Podendo ser complementado: de Portaria; de Resolu��o');
        $fnCadastrar(84,'Mo��o','Podendo ser complementado: de Apoio; de Pesar; de Rep�dio');
        $fnCadastrar(85,'Norma','Podendo ser complementado: T�cnica; de Conduta');
        $fnCadastrar(86,'Nota','Podendo ser complementado: T�cnica; de Empenho');
        $fnCadastrar(87,'Notifica��o','Podendo ser complementado: de Lan�amento');
        $fnCadastrar(88,'Of�cio','Modalidades de comunica��o oficial. � expedido para e pelas autoridades. Tem como finalidade o tratamento de assuntos oficiais pelos �rg�os da Administra��o P�blica entre si e tamb�m com particulares.');
        $fnCadastrar(89,'Of�cio-Circular','Esp�cie pr�pria');
        $fnCadastrar(90,'Or�amento','Podendo ser complementado: de Obra; de Servi�o');
        $fnCadastrar(91,'Ordem','Podendo ser complementado: de Servi�o; de Compra; do Dia');
        $fnCadastrar(92,'Organograma','Podendo ser complementado: da Empresa');
        $fnCadastrar(93,'Orienta��o','Podendo ser complementado: Normativa; Jurisprudencial');
        $fnCadastrar(94,'Panfleto','Podendo ser complementado: de Promo��o; de Evento');
        $fnCadastrar(95,'Parecer','Tipo de Documento pr�prio da AGU e outros �rg�os p�blicos.');
        $fnCadastrar(96,'Passaporte','Esp�cie pr�pria');
        $fnCadastrar(97,'Pauta','Podendo ser complementado: de Julgamentos; de Audi�ncias; das Se��es');
        $fnCadastrar(98,'Peti��o','Podendo ser complementado: Inicial; Incidental');
        $fnCadastrar(99,'Planilha','Podendo ser complementado: de Custos e Forma��o de Pre�os');
        $fnCadastrar(100,'Plano','Podendo ser complementado: de Servi�o; de Contas Cont�bil');
        $fnCadastrar(101,'Planta','Podendo ser complementado: Baixa; de Localiza��o; de Situa��o');
        $fnCadastrar(102,'Portaria','Expressa decis�o relativa a assuntos de interesse interno da Ag�ncia.');
        $fnCadastrar(103,'Precat�rio','Podendo ser complementado: Alimentar; Federal; Estadual; Municipal');
        $fnCadastrar(104,'Processo','Processo');
        $fnCadastrar(105,'Procura��o','Esp�cie pr�pria');
        $fnCadastrar(106,'Programa','Podendo ser complementado: de Governo; de Melhoria');
        $fnCadastrar(107,'Projeto','Podendo ser complementado: T�cnico; Comercial');
        $fnCadastrar(108,'Prontu�rio','Podendo ser complementado: M�dico; Odontol�gico');
        $fnCadastrar(109,'Pronunciamento','Esp�cie pr�pria');
        $fnCadastrar(110,'Proposta','Podendo ser complementado: Comercial; de Or�amento; T�cnica');
        $fnCadastrar(111,'Prospecto','Podendo ser complementado: de Fundos');
        $fnCadastrar(112,'Protocolo','Podendo ser complementado: de Entendimentos; de Entrega');
        $fnCadastrar(113,'Prova','Podendo ser complementado: de Conceito; de Profici�ncia');
        $fnCadastrar(114,'Question�rio','Podendo ser complementado: de Avalia��o; de Pesquisa; Socioecon�mico');
        $fnCadastrar(115,'Receita','Esp�cie pr�pria');
        $fnCadastrar(116,'Recibo','Podendo ser complementado: de Pagamento; de Entrega');
        $fnCadastrar(117,'Recurso','Podendo ser complementado: Administrativo; Judicial');
        $fnCadastrar(118,'Regimento','Podendo ser complementado: Interno');
        $fnCadastrar(119,'Registro','Podendo ser complementado: de Detalhes de Chamadas - CDR; de Acesso; Comercial');
        $fnCadastrar(120,'Regulamento','Podendo ser complementado: Geral; Disciplinar; de Administra��o');
        $fnCadastrar(121,'Rela��o','Podendo ser complementado: de Bens Revers�veis - RBR');
        $fnCadastrar(122,'Relat�rio','Podendo ser complementado: de Conformidade; de Medi��es; de Presta��o de Contas; de Viagem a Servi�o; Fotogr�fico; T�cnico');
        $fnCadastrar(123,'Release','Podendo ser complementado: de Resultados; de Produtos; de Servi�os');
        $fnCadastrar(124,'Representa��o','Podendo ser complementado: Comercial; Processual; Fiscal');
        $fnCadastrar(125,'Requerimento','Podendo ser complementado: Administrativo; de Adapta��o; de Altera��o T�cnica; de Altera��o T�cnica; de Autocadastramento de Esta��o; de Licenciamento de Esta��o; de Servi�o de Telecomunica��es');
        $fnCadastrar(126,'Requisi��o','Podendo ser complementado: de Auditoria; de Exclus�o; de Segunda Via');
        $fnCadastrar(127,'Resolu��o','Expressa decis�o quanto ao provimento normativo que regula a implementa��o da pol�tica de telecomunica��es brasileira, a presta��o dos servi�os de telecomunica��es, a administra��o dos recursos � presta��o e o funcionamento da Ag�ncia.');
        $fnCadastrar(128,'Resumo','Podendo ser complementado: T�cnico');
        $fnCadastrar(129,'Roteiro','Podendo ser complementado: de Instala��o; de Inspe��o');
        $fnCadastrar(130,'Senten�a','Podendo ser complementado: de M�rito; Terminativa; Declarat�ria; Constitutiva; Condenat�ria; Mandamental; Executiva');
        $fnCadastrar(131,'Sinopse','Podendo ser complementado: do Livro; do Estudo T�cnico');
        $fnCadastrar(132,'Solicita��o','Podendo ser complementado: de Pagamento');
        $fnCadastrar(133,'S�mula','Expressa decis�o quanto � interpreta��o da legisla��o de telecomunica��es e fixa entendimento sobre mat�rias de compet�ncia da Ag�ncia, com efeito vinculativo.');
        $fnCadastrar(134,'Tabela','Podendo ser complementado: de Visto; de Passaporte; de Certid�o');
        $fnCadastrar(135,'Telegrama','Esp�cie pr�pria');
        $fnCadastrar(136,'Termo','Podendo ser complementado: de Op��o por Aux�lio Financeiro; de Op��o para Contribui��o ao CPSS; de Concilia��o; de Devolu��o; de Doa��o; de Recebimento; de Rescis�o; de Compromisso de Est�gio; de Representa��o; de Responsabilidade de Instala��o - TRI');
        $fnCadastrar(137,'Tese','Podendo ser complementado: de Doutorado');
        $fnCadastrar(138,'Testamento','Podendo ser complementado: Particular; Vital; Cerrado; Conjuntivo');
        $fnCadastrar(139,'T�tulo','Podendo ser complementado: de Eleitor; P�blico; de Capitaliza��o');
        $fnCadastrar(140,'Voto','Esp�cie pr�pria');
        $fnCadastrar(141,'Carteira','Podendo ser complementado: Nacional de Habilita��o');
        $fnCadastrar(142,'Cart�o','Podendo ser complementado: de Identifica��o');
        $fnCadastrar(143,'CPF/CIC','Esp�cie pr�pria');
        $fnCadastrar(144,'CNPJ','Esp�cie pr�pria');
        $fnCadastrar(145,'Calend�rio','Podendo ser complementado: de Reuni�es');
        $fnCadastrar(146,'CNH','CNH');
        $fnCadastrar(147,'RG','RG');
        $fnCadastrar(148,'Agenda','Podendo ser complementado: de Reuni�o');
        $fnCadastrar(149,'An�lise','Como Documento Externo pode ser complementado: Cont�bil');
        $fnCadastrar(150,'Anota��o','Podendo ser complementado: de Responsabilidade T�cnica - ART');
        $fnCadastrar(151,'�udio','Podendo ser complementado: de Reuni�o');
        $fnCadastrar(152,'Boleto','Podendo ser complementado: de Pagamento; de Cobran�a; de Cobran�a Registrada; de Cobran�a sem Registro');
        $fnCadastrar(153,'Conta','Podendo ser complementado: Telef�nica; de �gua; de Luz');
        $fnCadastrar(154,'Contrarraz�es','Podendo ser complementado: em Recurso; em Apela��o; em Embargos Infringentes');
        $fnCadastrar(155,'Correspond�ncia','Esp�cie pr�pria');
        $fnCadastrar(156,'Cota','Tipo de Documento pr�prio da AGU.');
        $fnCadastrar(157,'Credencial','Podendo ser complementado: de Seguran�a; de Agente de Fiscaliza��o');
        $fnCadastrar(158,'Croqui','Podendo ser complementado: de Acesso, Urbano');
        $fnCadastrar(159,'Defesa','Podendo ser complementado: Administrativa; Judicial');
        $fnCadastrar(160,'Demonstra��o','Podendo ser complementado: de Resultado do Exerc�cio - DRE; de Fluxo de Caixa; Financeira; Cont�bil');
        $fnCadastrar(161,'Den�ncia','Esp�cie pr�pria');
        $fnCadastrar(162,'Esclarecimento','Esp�cie pr�pria utilizada em Licita��o $fnCadastrar(ComprasNet);');
        $fnCadastrar(163,'Escritura��o','Podendo ser complementado: Cont�bil Digital - ECD; Fiscal Digital - EFD; Fiscal Digital - EFD-Contribui��es');
        $fnCadastrar(164,'Estrat�gia','Podendo ser complementado: da Contrata��o');
        $fnCadastrar(165,'Impugna��o','Esp�cie pr�pria utilizada em Licita��o $fnCadastrar(ComprasNet);');
        $fnCadastrar(166,'Informa��o','Tipo de Documento pr�prio da AGU.');
        $fnCadastrar(167,'Inten��o','Podendo ser complementado: de Recurso; de Compra; de Venda');
        $fnCadastrar(168,'Licen�a','Podendo ser complementado: de Esta��o');
        $fnCadastrar(169,'Mat�ria','Podendo ser complementado: para Aprecia��o');
        $fnCadastrar(170,'Material','Podendo ser complementado: Publicit�rio; de Evento; de Promo��o');
        $fnCadastrar(171,'Mem�ria','Podendo ser complementado: de C�lculo');
        $fnCadastrar(172,'Movimenta��o','Podendo ser complementado: de Bens M�veis');
        $fnCadastrar(173,'Pedido','Podendo ser complementado: de Reconsidera��o; de Esclarecimento');
        $fnCadastrar(174,'Reclama��o','Esp�cie pr�pria');
        $fnCadastrar(175,'Referendo','Esp�cie pr�pria');
        $fnCadastrar(176,'Resultado','Podendo ser complementado: de Exame M�dico; de Contesta��o');
        $fnCadastrar(177,'V�deo','Podendo ser complementado: de Reuni�o');
        
                
        //----------------------------------------------------------------------
        // Tarefas
        //----------------------------------------------------------------------
        $objDTO = new TarefaDTO();

        $fnCadastrar = function($strNome = '', $strHistoricoCompleto = 'N', $strHistoricoCompleto = 'N', $strFecharAndamentosAbertos = 'N', $strLancarAndamentoFechado = 'N', $strPermiteProcessoFechado = 'N', $strIdTarefaModulo = '') use($objDTO, $objBD){
          
            $objDTO->unSetTodos();
            $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);

            if($objBD->contar($objDTO) == 0){  
                
                $objUltimaTarefaDTO = new TarefaDTO();
                $objUltimaTarefaDTO->retNumIdTarefa();
                $objUltimaTarefaDTO->setNumMaxRegistrosRetorno(1);
                $objUltimaTarefaDTO->setOrd('IdTarefa', InfraDTO::$TIPO_ORDENACAO_DESC);
                $objUltimaTarefaDTO = $objBD->consultar($objUltimaTarefaDTO);
                
                $objDTO->setNumIdTarefa($objUltimaTarefaDTO->getNumIdTarefa() + 1);
                $objDTO->setStrNome($strNome);
                $objDTO->setStrSinHistoricoResumido($strHistoricoCompleto);
                $objDTO->setStrSinHistoricoCompleto($strHistoricoCompleto);
                $objDTO->setStrSinFecharAndamentosAbertos($strFecharAndamentosAbertos);
                $objDTO->setStrSinLancarAndamentoFechado($strLancarAndamentoFechado);
                $objDTO->setStrSinPermiteProcessoFechado($strPermiteProcessoFechado);
                $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);
                $objBD->cadastrar($objDTO); 
            }   
        };
        
        
        $fnCadastrar('Processo expedido para a entidade @UNIDADE_DESTINO@ - @REPOSITORIO_DESTINO@ (@PROCESSO@, @UNIDADE@, @USUARIO@)', 'S', 'S', 'N', 'S', 'N', 'PEN_PROCESSO_EXPEDIDO');
        $fnCadastrar('Processo recebido da entidade @ENTIDADE_ORIGEM@ - @REPOSITORIO_ORIGEM@ (@PROCESSO@, @ENTIDADE_ORIGEM@, @UNIDADE_DESTINO@, @USUARIO@)', 'S', 'S', 'N', 'S', 'N', 'PEN_PROCESSO_RECEBIDO');
        $fnCadastrar('O processo foi recusado pelo org�o @UNIDADE_DESTINO@ pelo seguinte motivo: @MOTIVO@', 'S', 'S', 'N', 'N', 'S', 'PEN_PROCESSO_RECUSADO');
        $fnCadastrar('Expedi��o do Processo Cancelada em @DATA_HORA@ pelo Usu�rio @USUARIO@', 'S', 'S', 'N', 'S', 'N', 'PEN_PROCESSO_CANCELADO');
        $fnCadastrar('Operacao externa de @OPERACAO@ registrada em @DATA_HORA@ (@PESSOA_IDENTIFICACAO@ - @PESSOA_NOME@)\n @COMPLEMENTO@', 'S', 'S', 'S', 'S', 'N', 'PEN_OPERACAO_EXTERNA');

        //----------------------------------------------------------------------
        // Opera��es por Tarefas
        //----------------------------------------------------------------------
        $objDTO = new RelTarefaOperacaoDTO();

        $fnCadastrar = function($strCodigoOperacao, $numIdTarefa) use($objDTO, $objBD){

            $objDTO->unSetTodos();
            $objDTO->setStrCodigoOperacao($strCodigoOperacao);
            $objDTO->setNumIdTarefa($numIdTarefa);

            if($objBD->contar($objDTO) == 0){   
                $objBD->cadastrar($objDTO); 
            }   
        };

        //$fnCadastrar("01", 0);// Registro (Padr�o);
        $fnCadastrar("02", 32);//  Envio de documento avulso/processo ($TI_PROCESSO_REMETIDO_UNIDADE = 32;);
        $fnCadastrar("03", 51);//  Cancelamento/exclusao ou envio de documento ($TI_CANCELAMENTO_DOCUMENTO = 51;);
        $fnCadastrar("04", 13);//  Recebimento de documento ($TI_RECEBIMENTO_DOCUMENTO = 13;);
        $fnCadastrar("05", 1);// Autuacao ($TI_GERACAO_PROCEDIMENTO = 1;);
        $fnCadastrar("06", 101);// Juntada por anexacao ($TI_ANEXADO_PROCESSO = 101;);
        //$fnCadastrar("07", 0);// Juntada por apensacao;
        //$fnCadastrar("08", 0);// Desapensacao;
        $fnCadastrar("09", 24);//  Arquivamento ($TI_ARQUIVAMENTO = 24;);
        //$fnCadastrar("10", 0);// Arquivamento no Arquivo Nacional;
        //$fnCadastrar("11", 0);// Eliminacao;
        //$fnCadastrar("12", 0);// Sinistro;
        //$fnCadastrar("13", 0);// Reconstituicao de processo;
        $fnCadastrar("14", 26);// Desarquivamento ($TI_DESARQUIVAMENTO = 26;);
        //$fnCadastrar("15", 0);// Desmembramento;
        //$fnCadastrar("16", 0);// Desentranhamento;
        //$fnCadastrar("17", 0);// Encerramento/abertura de volume no processo;
        //$fnCadastrar("18", 0);// Registro de extravio;
        
        $objDTO = new InfraAgendamentoTarefaDTO();

        $fnCadastrar = function($strComando, $strDesc) use($objDTO, $objBD, $objRN){

            $objDTO->unSetTodos();
            $objDTO->setStrComando($strComando);
            
            if($objBD->contar($objDTO) == 0){
                
                $objDTO->setStrDescricao($strDesc);
                $objDTO->setStrStaPeriodicidadeExecucao('D');
                $objDTO->setStrPeriodicidadeComplemento('0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23');
                $objDTO->setStrSinAtivo('S');
                $objDTO->setStrSinSucesso('S');
                
                $objBD->cadastrar($objDTO);
            }   
        };
        
        $fnCadastrar('PENAgendamentoRN::verificarTramitesRecusados', 'Verifica��o de processos recusados');
        $fnCadastrar('PENAgendamentoRN::seiVerificarServicosBarramento', 'Verifica��o dos servi�os de fila de processamento est�o em execu��o');
    
        //----------------------------------------------------------------------
        // Corre��es para id_unidade_rh
        //----------------------------------------------------------------------        
        $objDTO = new UnidadeDTO();
        $objDTO->retNumIdUnidade();
        
        $arrObjDTO = $objBD->listar($objDTO);
        if(!empty($arrObjDTO)) {
            
            $objDTO = new PenUnidadeDTO();
            
            foreach($arrObjDTO as $objUnidadeDTO) {
                
                $objDTO->unSetTodos();
                $objDTO->setNumIdUnidade($objUnidadeDTO->getNumIdUnidade());
                
                if($objBD->contar($objDTO) == 0) {
                    $objDTO->setNumIdUnidadeRH(0);
                    $objBD->cadastrar($objDTO);
    }
            }
        }
        //----------------------------------------------------------------------
    }
    
    /**
     * Tratamento da Fila do Gearman
     * 
     * @refs 3670
     */
    protected function instalarV002R003S000US024(){
        
        $objInfraBanco = $this->inicializarObjInfraIBanco();
        $objMetaBD = $this->inicializarObjMetaBanco();
        
        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_tramite_processado',
            'cols' => array(
                'id_tramite'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'dth_ultimo_processamento' => array($objMetaBD->tipoDataHora(), PenMetaBD::NNULLO),
                'numero_tentativas' => array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_recebimento_concluido' => array($objMetaBD->tipoTextoFixo(1), PenMetaBD::NNULLO)
            ),
            'pk' => array('id_tramite')
        )); 
        
        $objInfraParametro = new InfraParametro($objInfraBanco);
        $objInfraParametro->setValor('PEN_NUMERO_TENTATIVAS_TRAMITE_RECEBIMENTO', '3');
    }
    
    /**
     * Erro no hist�rico de processo no momento do recebimento
     * 
     * @refs 3671
     */
    protected function instalarV002R003S000IW001(){
        
        $objInfraBanco = $this->inicializarObjInfraIBanco();
        //$objMetaBD = $this->inicializarObjMetaBanco();
        
        
        $objDTO = new TarefaDTO();
        $objBD = new TarefaBD($objInfraBanco);
        
        $fnAlterar = function($strIdTarefaModulo, $strNome) use($objDTO, $objBD){
            
            $objDTO->unSetTodos();
            $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);
            $objDTO->setNumMaxRegistrosRetorno(1);
            $objDTO->retStrNome();
            $objDTO->retNumIdTarefa();
            
            $objDTO = $objBD->consultar($objDTO);
            
            if(empty($objDTO)){   
                
                $objDTO->setStrNome($strNome);
                $objBD->cadastrar($objDTO); 
            }else{
                
                $objDTO->setStrNome($strNome);
                $objBD->alterar($objDTO); 
            }   
        };
        
        $fnAlterar('PEN_PROCESSO_RECEBIDO', 'Processo recebido da entidade @ENTIDADE_ORIGEM@ - @REPOSITORIO_ORIGEM@');
    }
    
    /**
     * Tratamento da Fila do Gearman para Recibos de Conclus�o de Tr�mite
     * 
     * @refs 3791
     */
    protected function instalarV002R003S001US035(){
        
        $objMetaBanco = $this->inicializarObjMetaBanco();

        if(!$objMetaBanco->isColuna('md_pen_tramite_processado', 'tipo_tramite_processo')) {
            $objMetaBanco->adicionarColuna('md_pen_tramite_processado', 'tipo_tramite_processo', 'CHAR(2)', PenMetaBD::NNULLO);
            $objMetaBanco->adicionarValorPadraoParaColuna('md_pen_tramite_processado', 'tipo_tramite_processo', 'RP');
        }
        
        if($objMetaBanco->isChaveExiste('md_pen_tramite_processado', 'pk_md_pen_tramite_processado')) {
        
            $objMetaBanco->removerChavePrimaria('md_pen_tramite_processado', 'pk_md_pen_tramite_processado');
            $objMetaBanco->adicionarChavePrimaria('md_pen_tramite_processado', 'pk_md_pen_tramite_processado', array('id_tramite', 'tipo_tramite_processo'));
        }
    }
    
    /**
     * Erro no Mapeamento Tipo de Documento de Envio
     * 
     * @refs 3870
     */
    protected function instalarV003R003S003IW001() {
        
        $objMetaBD = $this->inicializarObjMetaBanco();
        $objInfraBanco = $this->inicializarObjInfraIBanco();
        
        
        //----------------------------------------------------------------------
        // Novas sequ�ncias
        //----------------------------------------------------------------------
        $objInfraSequencia = new InfraSequencia($objInfraBanco);
        
        if(!$objInfraSequencia->verificarSequencia('md_pen_rel_doc_map_enviado')){
            $objInfraSequencia->criarSequencia('md_pen_rel_doc_map_enviado', '1', '1', '9999999999');
        }
        
        if(!$objInfraSequencia->verificarSequencia('md_pen_rel_doc_map_recebido')){
            $objInfraSequencia->criarSequencia('md_pen_rel_doc_map_recebido', '1', '1', '9999999999');
        }
        
        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_doc_map_enviado',
            'cols' => array(
                'id_mapeamento'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'codigo_especie'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'id_serie' => array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_padrao' => array($objMetaBD->tipoTextoFixo(1), 'S')
            ),
            'pk' => array('id_mapeamento'),
            //'uk' => array('codigo_especie', 'id_serie'),
            'fks' => array(
                'serie' => array('id_serie', 'id_serie'),
                'md_pen_especie_documental' => array('id_especie', 'codigo_especie'),
            )
        ));

        $objMetaBD->criarTabela(array(
            'tabela' => 'md_pen_rel_doc_map_recebido',
            'cols' => array(
                'id_mapeamento'=> array($objMetaBD->tipoNumeroGrande(), PenMetaBD::NNULLO),
                'codigo_especie'=> array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'id_serie' => array($objMetaBD->tipoNumero(), PenMetaBD::NNULLO),
                'sin_padrao' => array($objMetaBD->tipoTextoFixo(1), 'S')
            ),
            'pk' => array('id_mapeamento'),
            //'uk' => array('codigo_especie', 'id_serie'),
            'fks' => array(
                'serie' => array('id_serie', 'id_serie'),
                'md_pen_especie_documental' => array('id_especie', 'codigo_especie'),
            )
        )); 
        
        $objBD = new GenericoBD($objInfraBanco);
        
        if($objMetaBD->isTabelaExiste('md_pen_rel_tipo_documento_mapeamento_recebido')) {
                
            $objDTO = new PenRelTipoDocMapRecebidoDTO();

            $fnCadastrar = function($numCodigoEspecie, $numIdSerie) use($objDTO, $objBD){
                
                $objDTO->unSetTodos();
                $objDTO->setNumCodigoEspecie($numCodigoEspecie);
                $objDTO->setNumIdSerie($numIdSerie);

                if($objBD->contar($objDTO) == 0){
                    
                    $objDTO->setStrPadrao('S');
                    $objBD->cadastrar($objDTO); 
                }   
            };
            
            $arrDados = $objInfraBanco->consultarSql('SELECT DISTINCT codigo_especie, id_serie FROM md_pen_rel_tipo_documento_mapeamento_recebido');
            if(!empty($arrDados)) {
                foreach($arrDados as $arrDocMapRecebido) {

                    $fnCadastrar($arrDocMapRecebido['codigo_especie'], $arrDocMapRecebido['id_serie']);
                }
            }

            $objMetaBD->removerTabela('md_pen_rel_tipo_documento_mapeamento_recebido');  
        }
        
        
        if($objMetaBD->isTabelaExiste('md_pen_rel_serie_especie')) {
            
            $objDTO = new PenRelTipoDocMapEnviadoDTO();

            $fnCadastrar = function($numCodigoEspecie, $numIdSerie) use($objDTO, $objBD){
                
                $objDTO->unSetTodos();
                $objDTO->setNumCodigoEspecie($numCodigoEspecie);
                $objDTO->setNumIdSerie($numIdSerie);

                if($objBD->contar($objDTO) == 0){
                    
                    $objDTO->setStrPadrao('S');
                    $objBD->cadastrar($objDTO); 
                }   
            };
            
            $arrDados = $objInfraBanco->consultarSql('SELECT DISTINCT codigo_especie, id_serie FROM md_pen_rel_serie_especie');
            if(!empty($arrDados)) {
                foreach($arrDados as $arrDocMapEnviado) {

                    $fnCadastrar($arrDocMapEnviado['codigo_especie'], $arrDocMapEnviado['id_serie']);
                }
            }

            $objMetaBD->removerTabela('md_pen_rel_serie_especie');  
        }
    }
        
    protected function instalarV004R003S003IW002(){
        
        $strTipo = $this->inicializarObjMetaBanco()->tipoTextoGrande();

        $this->inicializarObjMetaBanco()
            ->alterarColuna('md_pen_recibo_tramite', 'cadeia_certificado', $strTipo)
            ->alterarColuna('md_pen_recibo_tramite_enviado', 'cadeia_certificado', $strTipo);         
    }
    
    /**
     * Criar script de BD para voltar o processo ao normal, como um processo de conting�ncia
     * 
     * @version 4
     * @release 3
     * @sprint 5
     * @refs 4047
     * @return null
     */
    protected function instalarV005R003S005IW018(){
        
        $objBD = new GenericoBD($this->inicializarObjInfraIBanco());
        $objDTO = new TarefaDTO();

        $fnCadastrar = function($strNome = '', $strHistoricoCompleto = 'N', $strHistoricoCompleto = 'N', $strFecharAndamentosAbertos = 'N', $strLancarAndamentoFechado = 'N', $strPermiteProcessoFechado = 'N', $strIdTarefaModulo = '') use($objDTO, $objBD){
          
            $objDTO->unSetTodos();
            $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);

            if($objBD->contar($objDTO) == 0){   
                
                $objUltimaTarefaDTO = new TarefaDTO();
                $objUltimaTarefaDTO->retNumIdTarefa();
                $objUltimaTarefaDTO->setNumMaxRegistrosRetorno(1);
                $objUltimaTarefaDTO->setOrd('IdTarefa', InfraDTO::$TIPO_ORDENACAO_DESC);
                $objUltimaTarefaDTO = $objBD->consultar($objUltimaTarefaDTO);
                
                $objDTO->setNumIdTarefa($objUltimaTarefaDTO->getNumIdTarefa() + 1);
                $objDTO->setStrNome($strNome);
                $objDTO->setStrSinHistoricoResumido($strHistoricoCompleto);
                $objDTO->setStrSinHistoricoCompleto($strHistoricoCompleto);
                $objDTO->setStrSinFecharAndamentosAbertos($strFecharAndamentosAbertos);
                $objDTO->setStrSinLancarAndamentoFechado($strLancarAndamentoFechado);
                $objDTO->setStrSinPermiteProcessoFechado($strPermiteProcessoFechado);
                $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);
                $objBD->cadastrar($objDTO); 
            }   
        };
        
        $fnCadastrar('Expedi��o do processo foi abortada manualmente devido a falha no tr�mite', 'S', 'S', 'N', 'N', 'S', 'PEN_EXPEDICAO_PROCESSO_ABORTADA');
    }
    
        /**
     * Criar script de BD para voltar o processo ao normal, como um processo de conting�ncia
     * 
     * @version 4
     * @release 3
     * @sprint 5
     * @refs 4047
     * @return null
     */
    protected function instalarV005R003S005IW023(){
        
        $objBD = new GenericoBD($this->inicializarObjInfraIBanco());
        
        $objDTO = new TarefaDTO();
        $objDTO->retNumIdTarefa();
        $objDTO->retStrNome();

        $fnAtualizar = function($strIdTarefaModulo, $strNome) use($objDTO, $objBD){
          
            $objDTO->unSetTodos();
            $objDTO->setStrIdTarefaModulo($strIdTarefaModulo);

            $objTarefaDTO = $objBD->consultar($objDTO);
            
            if(!empty($objTarefaDTO)) {
                
                $objTarefaDTO->setStrNome($strNome);
                
                $objBD->alterar($objTarefaDTO);   
            } 
        };
        // Tramita��o externa do processo @processo@ conclu�da com sucesso. Recebido na @UnidadeDestino@ - @hierarquia_superior@ -@reposit�rio_de_estruturas@
        $fnAtualizar('PEN_PROCESSO_EXPEDIDO', 'Processo em tramita��o externa para @UNIDADE_DESTINO@ - @UNIDADE_DESTINO_HIRARQUIA@ - @REPOSITORIO_DESTINO@');
        $fnAtualizar('PEN_PROCESSO_RECEBIDO', 'Processo recebido da unidade externa @ENTIDADE_ORIGEM@ - @ENTIDADE_ORIGEM_HIRARQUIA@ - @REPOSITORIO_ORIGEM@');
        $fnAtualizar('PEN_OPERACAO_EXTERNA', 'Tramita��o externa do processo @PROTOCOLO_FORMATADO@ conclu�da com sucesso. Recebido em @UNIDADE_DESTINO@ - @UNIDADE_DESTINO_HIRARQUIA@ - @REPOSITORIO_DESTINO@');
    }
}