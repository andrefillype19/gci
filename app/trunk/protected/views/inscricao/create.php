<?php

$this->breadcrumbs = array(
	$model->label(2) => array('index'),
	Yii::t('app', 'Create'),
);

$this->menu = array(
	array('label'=>Yii::t('app', 'List') . ' ' . $model->label(2), 'url' => array('index')),
	array('label'=>Yii::t('app', 'Manage') . ' ' . $model->label(2), 'url' => array('admin'),'visible'=>Yii::app()->user->role=='Authenticated'?false:true),
);
?>

<h1><?php echo 'Realizar' . ' ' . GxHtml::encode($model->label()); ?></h1>

<div class="form">


	<?php $form = $this->beginWidget('GxActiveForm', array(
		'id' => 'inscricao-form',
		'enableAjaxValidation' => false,
	));
	?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php 
		echo $form->errorSummary($model); 
		if ($model->hasErrors('id'))
		{	
			echo CHtml::link('Clique aqui para alterar sua inscrição',
			['inscricao/update','id'=>strtok($model->errors['id'][0],'-')]);
		}
	?>


	<div class="row">
		<?php echo 'Selecione um evento' ?>

		<?php
		if(Yii::app()->user->role=='Authenticated') {
			$result = Evento::model()->findAllAttributes(array('tituloevento'), true, "(datainscricaof + interval '23 hours') >= NOW() and datainscricaoi <= NOW()",['order'=>'tituloevento']);
            $data=GxHtml::listDataEx($result);
		}
		else {
            $criteria = Yii::app()->db->createCommand();
            $result = $criteria->select("id,case WHEN datafinal + Interval '60 days'  > now()  THEN 'Atuais' ELSE 'StandBy' END as tempo, tituloevento as nome")
                ->from('Evento')
                ->order('tempo,unaccent_string(tituloevento)')
                ->queryall();
            $data=GxHtml::listDataEx($result,'id','nome','tempo');
        }


		echo $form->dropDownList($model, 'id_evento',$data ,
			array(
				'disabled'=>$this->action->Id=='create'?false:true,
				'empty'=>'Selecione um evento ',
				'ajax' => array(
					'type'=>'POST', //request type
					'url'=>CController::createUrl('inscricao/dynamiccat'), //url to call.
					//Style: CController::createUrl('currentController/methodToCall')te
					//'data'=>'js:javascript statement'
					'update'=>'#'.CHtml::activeId($model, 'atividades'), //selector to upda
					//leave out the data key to pass all form values through
				))); ?>

		<?php echo $form->error($model,'id_evento'); ?>
	</div><!-- row -->
	<div class="row">
		<?php echo $form->labelEx($model,'cpf_dadospessoais'); ?>
		<?php
		$roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
		foreach($roles as $role)
			if($role->name == 'Authenticated')
			{
                $this->widget('ext.select2.ESelect2', array(
                    'model' => $model,
                    'attribute' => 'cpf_dadospessoais',
                    'data' => GxHtml::listDataEx(
                        DadosPessoais::model()->findAllAttributes(null, true,
                            "cpf = :param1",array(':param1'=>Yii::app()->user->cpf))),
                    'options' => array(
                        'allowClear' => true,
                        'style' => 'width: 400px',
                        'disabled'=>'disabled'
                    ),
                ));
				//echo $form->dropDownList($model, 'cpf_dadospessoais', ,array('disabled'=>true));
					$time ="AND  vagasdisponiveis > 0 AND data+horafinal+'2:00:00' >= now() ";
			}
			else
			{
                $criteria = Yii::app()->db->createCommand();
                $dadospeople=$criteria->select ("concat('',LPAD(cast (cpf as char(11)),11,'0'),'-',upper(nome)) as nome, cpf")
                    ->from ('DadosPessoais')
                    ->order('unaccent_string(nome)')
                    ->queryall();
                $this->widget('ext.select2.ESelect2', array(
                    'model' => $model,
                    'attribute' => 'cpf_dadospessoais',
                    'data' => CHtml::listData($dadospeople,'cpf','nome'),
                    'options' => array(
                        'placeholder' => 'Digite um nome..............................................................',
                        'allowClear' => true,
                        'style' => 'width: 400px',
                    ),
                ));
				//echo $form->dropDownList($model, 'cpf_dadospessoais',  );
				$time="";
			}
		?>
		<?php echo $form->error($model,'cpf_dadospessoais'); ?>
	</div><!-- row -->

	
	<div class="row">
	<?php
	if ( Yii::app()->user->role=='Admin' || Yii::app()->user->role=='Coordenadores') {
		echo "Inscrição por Lista de CPFs(opcional)";
		echo CHtml::textArea('cpf_area', null,
			array('id' => 'id_cpf_area',
				'rows' => 7,
				'cols' => 50));
	}
	?>
	</div><!-- row -->

	<div class="form">
		<?php
		//Este bloco de código serve apenas para quando houver erro mostrar as atviidades selecionadas.
		$criteria = new CDbCriteria;

		$criteria->select = 't.id,data,horainicial,horafinal,tema,eletivo,id_subevento,tipo';
		$criteria->addCondition("id_evento = :para  $time");
		$criteria->order='data asc,horainicial asc, horafinal asc';
		$criteria->params =array(':para' => (int)$model->id_evento);
		$query=Atividade::model()->findAll($criteria);
			echo $form->checkBoxList($model, 'atividades', GxHtml::encodeEx(GxHtml::listDataEx($query)), array('template'=>'<tr><td>{input}</td><td>{label}</td></tr>'));
		?>
	</div>


	<?php
	echo GxHtml::submitButton('Inscrever');
	$this->endWidget();
	?>
</div><!-- form -->