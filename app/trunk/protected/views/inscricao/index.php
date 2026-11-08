<?php

$this->breadcrumbs = array(
	Inscricao::label(2),
	Yii::t('app', 'Index'),
);

$this->menu = array(
	array('label'=>'Nova' . ' ' . Inscricao::label(), 'url' => array('create')),
    array('label'=>'Relatório de Horas Complementares', 'url' => array('relatorio')),
	array('label'=>Yii::t('app', 'Manage') . ' ' . Inscricao::label(2), 'url' => array('admin'),'visible'=>Yii::app()->user->role=='Authenticated'?false:true),
);
?>

<h1><?php echo GxHtml::encode(Inscricao::label(2)); ?></h1>

<h4>Clique no número de inscrição para ver detalhes, alterá-la e imprimir certificados</h4>
<?php $this->widget('zii.widgets.CListView', array(
	'dataProvider'=>$dataProvider,
	'itemView'=>'_view',
)); 