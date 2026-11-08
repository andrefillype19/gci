<?php

Yii::import('application.models._base.BaseEvento');

class Evento extends BaseEvento
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}