<div class="wide form">

<?php $form = $this->beginWidget('GxActiveForm', array(
	'action' => Yii::app()->createUrl($this->route),
	'method' => 'get',
)); ?>

	<div class="row">
		<?php echo $form->label($model, 'id'); ?>
		<?php echo $form->textField($model, 'id'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'id_inscricao'); ?>
		<?php echo $form->dropDownList($model, 'id_inscricao', GxHtml::listDataEx(Inscricao::model()->findAllAttributes(null, true)), array('prompt' => Yii::t('app', 'All'))); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1a'); ?>
		<?php echo $form->textField($model, 'q1a'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1b'); ?>
		<?php echo $form->textField($model, 'q1b'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1c'); ?>
		<?php echo $form->textField($model, 'q1c'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1d'); ?>
		<?php echo $form->textField($model, 'q1d'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1e'); ?>
		<?php echo $form->textField($model, 'q1e'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1f'); ?>
		<?php echo $form->textField($model, 'q1f'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1g'); ?>
		<?php echo $form->textField($model, 'q1g'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1h'); ?>
		<?php echo $form->textField($model, 'q1h'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q1i'); ?>
		<?php echo $form->textField($model, 'q1i'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q2'); ?>
		<?php echo $form->textField($model, 'q2', array('maxlength' => 64)); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q3'); ?>
		<?php echo $form->textField($model, 'q3'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'q4'); ?>
		<?php echo $form->textArea($model, 'q4'); ?>
	</div>

	<div class="row buttons">
		<?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- search-form -->
