<div class="form">

<?php $form = $this->beginWidget('GxActiveForm', array(
	'action' => Yii::app()->createUrl($this->route),
	'method' => 'get',
)); ?>

	<div class="row">
		<?php echo $form->label($model, 'id_evento'); ?>
        <?php
        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_evento',
            'data' => GxHtml::listDataEx(Evento::model()->findAll(['order' => 'unaccent_string(tituloevento)']), 'id','tituloevento'),
            'options' => array(
                'placeholder' => 'Digite um nome..............................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>
<!--		--><?php //echo $form->dropDownList($model, 'id_evento', GxHtml::listDataEx(Evento::model()->findAllAttributes(null, true)), array('prompt' => Yii::t('app', 'All'))); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'nome'); ?>
		<?php echo $form->textField($model, 'nome', array('maxlength' => 128)); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'generomasculino'); ?>
		<?php echo $form->dropDownList($model, 'generomasculino', array('0' => Yii::t('app', 'No'), '1' => Yii::t('app', 'Yes')), array('prompt' => Yii::t('app', 'All'))); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'filepath'); ?>
		<?php echo $form->textField($model, 'filepath', array('maxlength' => 255)); ?>
	</div>

	<div class="row buttons">
		<?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- search-form -->
