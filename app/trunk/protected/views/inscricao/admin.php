<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	Yii::t('app', 'Manage'),
);

$this->menu = array(
		array('label'=>Yii::t('app', 'List') . ' ' . $model->label(2), 'url'=>array('index')),
		array('label'=>Yii::t('app', 'Create') . ' ' . $model->label(), 'url'=>array('create')),
	);

Yii::app()->clientScript->registerScript('search', "
$('.search-button').click(function(){
	$('.search-form').toggle();
	return false;
});
$('.search-form form').submit(function(){
	$.fn.yiiGridView.update('inscricao-grid', {
		data: $(this).serialize()
	});
	return false;
});
");
?>

<h1><?php echo Yii::t('app', 'Manage') . ' ' . GxHtml::encode($model->label(2)); ?></h1>

<?php
foreach (Yii::app()->user->getFlashes() as $key => $message)
{
	echo '<div class="alert alert-', $key.'">'.$message."</div>\n";
}
?>

<p>
Você pode, opcionalmente, digitar um operador de comparação (<, <=,>,> =, <> ou =) no início de cada um dos seus valores de pesquisa para especificar como a comparação deve ser feita.
</p>

<?php echo GxHtml::link(Yii::t('app', 'Pesquisa Avançada'), '#', array('class' => 'search-button')); ?>
<div class="search-form">
<?php $this->renderPartial('_search', array('model' => $model)); ?>
</div><!-- search-form -->

<?php $this->widget('zii.widgets.grid.CGridView', array(
	'id' => 'inscricao-grid',
	'itemsCssClass'=>'table table-bordered',
	'dataProvider' => $model->search(),
	//'filter' => $model,
	'columns' => array(
		'id',
		array(
				'name'=>'id_evento',
				'value'=>'GxHtml::valueEx($data->idEvento)',
				),
		array(
				'name'=>'cpf_dadospessoais',
				'value'=>'GxHtml::valueEx($data->cpfDadospessoais)',
				),
		array(
			'class' => 'CButtonColumn',
		),
	),
)); ?>