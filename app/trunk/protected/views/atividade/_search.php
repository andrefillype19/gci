<div class="form">

<?php $form = $this->beginWidget('GxActiveForm', array(
	'action' => Yii::app()->createUrl($this->route),
	'method' => 'get',
)); ?>

	<div class="row">
		<?php echo $form->label($model, 'tema'); ?>
		<?php echo $form->textField($model, 'tema', array('maxlength' => 40)); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'id_evento'); ?>
        <?php
        $criteria = Yii::app()->db->createCommand();
        $result = $criteria->select("id,case WHEN datafinal + Interval '60 days'  > now()  THEN 'Atuais' ELSE 'StandBy' END as tempo, tituloevento as nome")
            ->from('Evento')
            ->order('tempo,unaccent_string(tituloevento)')
            ->queryall();
        echo $form->dropDownList($model, 'id_evento', GxHtml::listDataEx($result,'id','nome','tempo'),
            array(
                'prompt' => Yii::t('app', 'Escolha o evento...'),
                'ajax' => array(
                    'type' => 'POST', //request type
                    'url' => CController::createUrl('atividade/dynamicinsc'), //url to call.
                    //Style: CController::createUrl('currentController/methodToCall')
                    'update' => '#' . CHtml::activeId($model, 'id_subevento'), //selector to update
                    //'data'=>'js:javascript statement'
                    //leave out the data key to pass all form values through
                )));
        ?>
        <?php echo $form->error($model,'id_evento'); ?>
    </div><!-- row -->
    <div class="row">
        <?php echo $form->labelEx($model,'id_subevento'); ?>
        <?php
        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_subevento',
            'data' => [null=>''],
            'options' => array(
                'placeholder' => 'Escolha o certificado..........................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>
	</div>

    <div class="row">
        <?php echo $form->label($model, 'lancado'); ?>
        <?php echo $form->dropDownList($model, 'id', array(0 => Yii::t('app', 'All'),1 => Yii::t('app', 'No'), 2 => Yii::t('app', 'Yes'))); ?>
    </div>


	<div class="row buttons">
		<?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- search-form -->
