<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	GxHtml::valueEx($model) => array('view', 'id' => GxActiveRecord::extractPkValue($model, true)),
	Yii::t('app', 'Update'),
);

$this->menu = array(
	array('label' => Yii::t('app', 'List') . ' ' . $model->label(2), 'url'=>array('index')),
	array('label' => Yii::t('app', 'Manage') . ' ' . $model->label(2), 'url'=>array('admin'),'visible'=>Yii::app()->user->role=='Authenticated'?false:true),
);
?>

<h1><?php echo Yii::t('app', 'Update') . ' ' . GxHtml::encode($model->label()) . ' ' . GxHtml::encode(GxHtml::valueEx($model)); ?></h1>

<div class="form">


	<?php $form = $this->beginWidget('GxActiveForm', array(
		'id' => 'inscricao-form',
		'enableAjaxValidation' => false,
	));
	?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php echo $form->errorSummary($model); ?>

	<div class="row">
		<?php echo 'Selecione um evento' ?>

		<?php
		if(Yii::app()->user->role=='Authenticated')
		{
			$result=Evento::model()->findAllAttributes(array ('tituloevento' ), true,"(datainscricaof + interval '23 hours') >= NOW() and datainscricaoi <= NOW()");
		}
		else
			$result=Evento::model()->findAllAttributes(array('tituloevento' ),true);

		echo $form->dropDownList($model, 'id_evento', GxHtml::listDataEx($result),
			array(
				'disabled'=>true,
				)); ?>

		<?php echo $form->error($model,'id_evento'); ?>
	</div><!-- row -->
	<div class="row">
		<?php echo $form->labelEx($model,'cpf_dadospessoais'); ?>
		<?php
		$roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
		foreach($roles as $role)
			if($role->name == 'Authenticated')
			{
				echo $form->dropDownList($model, 'cpf_dadospessoais', GxHtml::listDataEx(
					DadosPessoais::model()->findAllAttributes(null, true,"cpf = :param1",array(':param1'=>Yii::app()->user->cpf))),array('disabled'=>true));
			}
			else
			{
				echo $form->dropDownList($model, 'cpf_dadospessoais', GxHtml::listDataEx(DadosPessoais::model()->findAllAttributes(null, true)),array('disabled'=>true));
			}


		?>
		<?php echo $form->error($model,'cpf_dadospessoais'); ?>
	</div><!-- row -->



	<label><?php echo 'Selecione as atividades que deseje participar. Atividades indicadas não podem ser removidas.' ?></label>
	<?php
		echo $form->error($model,'atividades');
	?>
	<div class="form">
		<?php

		$criteria = new CDbCriteria;
		//esta consulta é diferente do create , pois é feito um join 
		$criteria->select = 'DISTINCT ON (t.id,t.data,horainicial,horafinal) t.id,t.data,id_subevento,horainicial,horafinal,tema,eletivo,tipo';
		$criteria->join = 'LEFT JOIN inscricaoatividade AS tu ON tu.id_atividade = t.id';
		//exibir atividades inscritas(necessário para que o sistema nao as remova) ou aquelas que ainda não ocorreram e possuem vagas.
		$criteria->addCondition("id_evento = :param2  AND ( (vagasdisponiveis > 0  AND  (data+horafinal+'2:00:00') >= now() ) OR  id_inscricao=:param1)");
		$criteria->order='data asc,horainicial asc, horafinal asc';
		$criteria->params =array(':param1' =>$model->id , ':param2' => $model->id_evento);
		$query=Atividade::model()->findAll($criteria);

		echo $form->checkBoxList($model, 'atividades', GxHtml::encodeEx(GxHtml::listDataEx($query)), array('template'=>'<tr><td>{input}</td><td>{label}</td></tr>'));
		?>

	</div>


	<?php
	echo GxHtml::submitButton('Atualizar');
	$this->endWidget();
	?>
</div><!-- form -->