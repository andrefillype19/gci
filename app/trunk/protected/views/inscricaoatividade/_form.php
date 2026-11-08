<div class="form">


<?php $form = $this->beginWidget('GxActiveForm', array(
	'id' => 'inscricaoatividade-form',
	'enableAjaxValidation' => false,
));
?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php echo $form->errorSummary($model); ?>

		<div class="row">
		<?php echo $form->labelEx($model,'id_inscricao'); ?>
		<?php echo $form->textField($model, 'id_inscricao' );   ?>
		<?php echo $form->error($model,'id_inscricao'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'id_atividade'); ?>
		<?php
		
		$ativ2= Atividade::model()->findAllAttributes(array('tema','data','horainicial','horafinal'), true);
    
        @usort($ativ2, function($a, $b)
        {
            return strcasecmp( GxHtml::valueEx($a), GxHtml::valueEx($b)) ;
        });
		 echo $form->dropDownList($model, 'id_atividade', GxHtml::listDataEx($ativ2)) ; 
		 ?>
		<?php echo $form->error($model,'id_atividade'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'presenca'); ?>
		<?php echo $form->checkBox($model, 'presenca'); ?>
		<?php echo $form->error($model,'presenca'); ?>
		</div><!-- row -->


<?php
echo GxHtml::submitButton(Yii::t('app', 'Save'));
$this->endWidget();
?>
</div><!-- form -->