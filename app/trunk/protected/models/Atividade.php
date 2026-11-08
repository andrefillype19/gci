<?php

Yii::import('application.models._base.BaseAtividade');

class Atividade extends BaseAtividade
{
	public function __toString()
	{
	    if (Yii::app()->controller->id=='inscricao' && (Yii::app()->controller->action->id=='dynamiccat'||Yii::app()->controller->action->id=='create' ||Yii::app()->controller->action->id=='update')) {
            return $this->data . ' ' . $this->horainicial . ' às ' . $this->horafinal . '&nbsp&nbsp&nbsp&nbsp&nbsp<i>' . ($this->eletivo ? "Optativa" : "Indicada") . '&nbsp&nbsp&nbsp&nbsp&nbsp' . $this->idSubevento->nome . "</i><br>".($this->tipo=='atividade'?' ':$this->tipo.': ').$this->tema;
        }

        return $this->data.' '.$this->horainicial.'-'.$this->local.'-'.$this->tema;
	}
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}