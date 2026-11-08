<?php

Yii::import('application.models._base.BaseInscricao');

class Inscricao extends BaseInscricao
{
	
	public function __toString()
	{
		
		$dados  = DadosPessoais::model()->findByPk($this->cpf_dadospessoais);
		return  strtoupper($dados->nome)." ".$this->id;
		
	}
	
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}