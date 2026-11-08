<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	GxHtml::valueEx($model),
);

$this->menu=array(
	
	array('label'=>Yii::t('app', 'Update') . ' ' . $model->label(), 'url'=>array('update', 'id' => $model->id)),
	array('label'=>Yii::t('app', 'Delete') . ' ' . $model->label(), 'url'=>'#','visible'=>Yii::app()->user->role=='Authenticated'?false:true, 'linkOptions' => array('submit' => array('delete', 'id' => $model->id), 'confirm'=>'Você deseja realmente remover esta inscrição?')),
	array('label'=>Yii::t('app', 'Manage') . ' ' . $model->label(2), 'url'=>array('admin'),'visible'=>Yii::app()->user->role=='Authenticated'?false:true),
	array('label'=>'Imprimir Certificado', 'url'=>array('certificado','id'=>$model->id)),
);
?>

<?php
foreach (Yii::app()->user->getFlashes() as $key => $message)
{
	echo '<div class="alert alert-', $key.'">'.$message."</div>\n";
}
?>
<h1><?php //echo Yii::t('app', 'View') . ' ' . GxHtml::encode($model->label()) . ' ' . GxHtml::encode(GxHtml::valueEx($model)); ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data' => $model,
	'attributes' => array(
'id',
array(
			'name' => 'idEvento',
			'type' => 'raw',
			'value' => $model->idEvento !== null ? GxHtml::link(GxHtml::encode(GxHtml::valueEx($model->idEvento)), array('evento/view', 'id' => GxActiveRecord::extractPkValue($model->idEvento, true))) : null,
			),
array(
			'name' => 'cpfDadospessoais',
			'type' => 'raw',
			'value' => $model->cpfDadospessoais !== null ? GxHtml::link(GxHtml::encode(GxHtml::valueEx($model->cpfDadospessoais)), array('dadosPessoais/view', 'id' => GxActiveRecord::extractPkValue($model->cpfDadospessoais, true))) : null,
			),
	),
)); ?>

<h2><?php echo GxHtml::encode($model->getRelationLabel('atividades')); ?> Inscritas </h2>
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
?>