<?php

Yii::import('application.models._base.BaseInscricaoatividade');

class Inscricaoatividade extends BaseInscricaoatividade
{
	
	public function __toString()
	{
		$inscricao = Inscricao::model()->findByPk($this->id_inscricao);
        $dados  = DadosPessoais::model()->findByPk($inscricao->cpf_dadospessoais);
        return  $dados->nome." ".$this->id_inscricao;
	}
	
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}