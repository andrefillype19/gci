<div class="form">


<?php $form = $this->beginWidget('GxActiveForm', array(
	'id' => 'evento-form',
	'enableAjaxValidation' => false,
));
?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php echo $form->errorSummary($model); ?>

		<div class="row">
		<?php echo $form->labelEx($model,'tituloevento'); ?>
		<?php echo $form->textField($model, 'tituloevento', array('maxlength' => 512)); ?>
		<?php echo $form->error($model,'tituloevento'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'local'); ?>
		<?php echo $form->textField($model, 'local', array('maxlength' => 128)); ?>
		<?php echo $form->error($model,'local'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'instituicaopromotora'); ?>
		<?php echo $form->textField($model, 'instituicaopromotora', array('maxlength' => 512)); ?>
		<?php echo $form->error($model,'instituicaopromotora'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'cidade'); ?>
		<?php echo $form->textField($model, 'cidade', array('maxlength' => 128)); ?>
		<?php echo $form->error($model,'cidade'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'datainicial'); ?>
		<?php $form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => $model,
			'attribute' => 'datainicial',
			'value' => $model->datainicial,
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'dd/mm/yy',
				),
			));
		; ?>
		<?php echo $form->error($model,'datainicial'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'datafinal'); ?>
		<?php $form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => $model,
			'attribute' => 'datafinal',
			'value' => $model->datafinal,
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'dd/mm/yy',
				),
			));
; ?>
		<?php echo $form->error($model,'datafinal'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'datainscricaoi'); ?>
		<?php $form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => $model,
			'attribute' => 'datainscricaoi',
			'value' => $model->datainscricaoi,
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'dd/mm/yy',
				),
			));
; ?>
		<?php echo $form->error($model,'datainscricaoi'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'datainscricaofform'); ?>
		<?php $form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => $model,
			'attribute' => 'datainscricaof',
			'value' => $model->datainscricaof,
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'dd/mm/yy',
				),
			));
; ?>
		<?php echo $form->error($model,'datainscricaof'); ?>
		</div><!-- row -->
		<div class="row">
			<?php echo $form->labelEx($model,'printcertificate'); ?>
			<?php echo $form->checkBox($model, 'printcertificate'); ?>
			<?php echo $form->error($model,'printcertificate'); ?>
		</div><!-- row -->
Caso queira, você pode criar um novo usuário para gerenciar as atividades deste evento.
    Recomenda-se que o login seja uma abreviação única do evento, Ex:simpcomp2019<br>
    <?php
    echo GxHtml::label('Login','login');
    echo GxHtml::textField('login');
    echo GxHtml::label('Senha','senha');
    echo GxHtml::textField('senha');
    ?>

    <br>

<?php
echo GxHtml::submitButton(Yii::t('app', 'Save'));
$this->endWidget();
?>
</div><!-- form -->