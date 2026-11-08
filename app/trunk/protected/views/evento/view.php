<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	GxHtml::valueEx($model),
);

$this->menu=array(
    array('label'=>'Adicionar atividade ', 'url'=>array('atividade/create',["id_evento"=>$model->id])),
	array('label'=>Yii::t('app', 'List') . ' ' . $model->label(2), 'url'=>array('index')),
	array('label'=>Yii::t('app', 'Create') . ' ' . $model->label(), 'url'=>array('create')),
	array('label'=>Yii::t('app', 'Update') . ' ' . $model->label(), 'url'=>array('update', 'id' => $model->id)),
	array('label'=>Yii::t('app', 'Delete') . ' ' . $model->label(), 'url'=>'#', 'linkOptions' => array('submit' => array('delete', 'id' => $model->id), 'confirm'=>'Are you sure you want to delete this item?')),
	array('label'=>Yii::t('app', 'Manage') . ' ' . $model->label(2), 'url'=>array('admin')),
	array('label'=>Yii::t('app', 'Imprimir') . ' Lista de Presença', 'url'=>array('viewpdf','id'=>$model->id)),
    array('label'=>Yii::t('app', 'Dados') . ' dos Participantes', 'url'=>array('viewparticipantes','id'=>$model->id)),
);
?>

<h1><?php echo Yii::t('app', 'View') . ' ' . GxHtml::encode($model->label()) . ' ' . GxHtml::encode(GxHtml::valueEx($model)); ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data' => $model,
	'attributes' => array(
        'tituloevento',
        'local',
        'instituicaopromotora',
        'cidade',
        'id',
        'datainicial',
        'datafinal',
        'datainscricaoi',
        'datainscricaof',
        'printcertificate:boolean',
	),
)); ?>

<h2><?php echo GxHtml::encode($model->getRelationLabel('atividades')); ?></h2>
<?php
    $ativ= $model->atividades;
    usort($ativ, function($a, $b)
    {
        if ($a->data != $b->data )
            return strcmp($a->data, $b->data) ;
        if( $a->horainicial != $b->horainicial)
            return strcmp($a->horainicial, $b->horainicial) ;
        if( $a->horafinal != $b->horafinal)
            return strcmp($a->horafinal, $b->horafinal) ;
        
        return 0;
    });
    echo GxHtml::openTag('ul');
    foreach($ativ as $relatedModel) {
        echo GxHtml::openTag('li');
        echo GxHtml::link(GxHtml::encode(GxHtml::valueEx($relatedModel)), array('atividade/view', 'id' => GxActiveRecord::extractPkValue($relatedModel, true)));
        echo GxHtml::closeTag('li');
    }
    echo GxHtml::closeTag('ul');
?><h2><?php //echo GxHtml::encode($model->getRelationLabel('dadosPessoaises')); ?></h2>
<?php
	//echo GxHtml::openTag('ul');
	//foreach($model->dadosPessoaises as $relatedModel) {
	//	echo GxHtml::openTag('li');
		//echo GxHtml::link(GxHtml::encode(GxHtml::valueEx($relatedModel)), array('dadosPessoais/view', 'id' => GxActiveRecord::extractPkValue($relatedModel, true)));
		//echo GxHtml::closeTag('li');
	//}
	//echo GxHtml::closeTag('ul');
?>