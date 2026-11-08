<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	GxHtml::valueEx($model),
);

$this->menu=array(
	array('label'=>Yii::t('app', 'List') . ' ' . $model->label(2), 'url'=>array('index')),
	array('label'=>Yii::t('app', 'Create') . ' ' . $model->label(), 'url'=>array('create')),
	array('label'=>Yii::t('app', 'Update') . ' ' . $model->label(), 'url'=>array('update', 'id' => $model->id)),
	array('label'=>Yii::t('app', 'Delete') . ' ' . $model->label(), 'url'=>'#', 'linkOptions' => array('submit' => array('delete', 'id' => $model->id), 'confirm'=>'Are you sure you want to delete this item?')),
	array('label'=>Yii::t('app', 'Manage') . ' ' . $model->label(2), 'url'=>array('admin')),
	array('label'=>Yii::t('app', 'Imprimir') . ' Lista de Presença', 'url'=>array('viewpdf','id'=>$model->id)),
    array('label'=>Yii::t('app', 'Dados') . ' dos Participantes', 'url'=>array('viewparticipantes','id'=>$model->id)),
    array('label'=>'Certificado do Palestrante-Padrão', 'url'=>array('certpal','id'=>$model->id,'mode'=>1)),
    array('label'=>'Certificado do Palestrante-Branco', 'url'=>array('certpal','id'=>$model->id,'mode'=>2)),
    array('label'=>'Certificado do Palestrante-Sem Assinatura', 'url'=>array('certpal','id'=>$model->id,'mode'=>3)),
);
?>

<h1><?php echo GxHtml::encode($model->label()) . ' ' . GxHtml::encode(GxHtml::valueEx($model)); ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data' => $model,
	'attributes' => array(
'tema',
'Proponente',
'qtdhoras',
'local',
'vagasdisponiveis',
'qtdeinscritos',
'multihorario:boolean',
'idSubevento',
'presenca:boolean',
'eletivo:boolean',
array(
			'name' => 'idEvento',
			'type' => 'raw',
			'value' => $model->idEvento !== null ? GxHtml::link(GxHtml::encode(GxHtml::valueEx($model->idEvento)), array('evento/view', 'id' => GxActiveRecord::extractPkValue($model->idEvento, true))) : null,
			),
'id',
'data',
'horainicial',
'horafinal',
	),
)); ?>

<h2><?php echo GxHtml::encode($model->getRelationLabel('inscricaoatividades')); ?></h2>
<?php
    
    $ativ= $model->inscricaoatividades;
    /*@usort($ativ, function($a, $b)
    {
        return strcasecmp( GxHtml::valueEx($a), GxHtml::valueEx($b)) ;
    });*/
        
	echo GxHtml::openTag('ul');
	foreach($ativ as $relatedModel) {
		echo GxHtml::openTag('li');
		echo GxHtml::link(GxHtml::encode(GxHtml::valueEx($relatedModel)), array('inscricaoatividade/view', 'id' => GxActiveRecord::extractPkValue($relatedModel, true)));
		echo GxHtml::closeTag('li');
	}
	echo GxHtml::closeTag('ul');
?>