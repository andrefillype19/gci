<div class="form">

<?php $form = $this->beginWidget('GxActiveForm', array(
	'action' => Yii::app()->createUrl($this->route),
	'method' => 'get',
)); ?>


    <?php
    echo CHtml::label('Evento','id_evento');
    $criteria = Yii::app()->db->createCommand();
    $result = $criteria->select("id,case WHEN datafinal + Interval '60 days'  > now()  THEN 'Atuais' ELSE 'StandBy' END as tempo, tituloevento as nome")
        ->from('Evento')
        ->order('tempo,unaccent_string(tituloevento)')
        ->queryall();

    echo $form->dropDownList($model,'id',GxHtml::listDataEx($result,'id','nome','tempo'),
        array(
            'prompt' => Yii::t('app', 'Escolha o evento...'),
            'style' => 'width: 400px',
            'ajax' => array(
                'type' => 'POST', //request type
                'url' => CController::createUrl('inscricaoatividade/dynamicativ'), //url to call.
                'update' => '#' . CHtml::activeId($model, 'id_atividade'), //selector to update
                //'data'=>'js:javascript statement'
                //leave out the data key to pass all form values through
            )));

    ?>

	<div class="row">
		<?php echo $form->label($model, 'id_atividade');
        $criteria = Yii::app()->db->createCommand();
        $dados = [];
        $data=CHtml::listData($dados,'id','tema');
		$this->widget('ext.select2.ESelect2',array(
			'model'=>$model,
			'attribute'=>'id_atividade',
			'data'=>[0=>''],
			'options'=>array(
				'placeholder'=>'Digite o nome da atividade..............................................................',
				'allowClear'=>true,
			),
		));
        ?>
	</div>

    <div class="row">
        <?php echo 'Participante (deixe vazio para exibir todos) <BR>' ?>
        <?php echo $form->textField($model, 'id_inscricao'); ?>
        <?php echo $form->error($model,'id_inscricao'); //This is added.  ?>
    </div>

	<div class="row">
		<?php echo $form->label($model, 'presenca'); ?>
		<?php echo $form->dropDownList($model, 'presenca', array('0' => Yii::t('app', 'No'), '1' => Yii::t('app', 'Yes')), array('prompt' => Yii::t('app', 'All'))); ?>
	</div>


	<div class="row buttons">
		<?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- search-form -->
