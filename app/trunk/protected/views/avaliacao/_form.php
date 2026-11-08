<div class="form">


<?php $form = $this->beginWidget('GxActiveForm', array(
	'id' => 'avaliacao-form',
	'enableAjaxValidation' => false,
));
?>

	<p class="note">
		Para acessar seu certificado, avalie o evento com uma nota de 0 a 10, quanto a:
	</p>

	<?php echo $form->errorSummary($model); ?>

		
		<div class="row">
		<?php echo $form->labelEx($model,'q1a'); ?>
		<?php echo $form->dropDownList($model, 'q1a',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1a'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1b'); ?>
		<?php echo $form->dropDownList($model, 'q1b',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1b'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1c'); ?>
		<?php echo $form->dropDownList($model, 'q1c',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1c'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1d'); ?>
		<?php echo $form->dropDownList($model, 'q1d',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1d'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1e'); ?>
		<?php echo $form->dropDownList($model, 'q1e',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1e'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1f'); ?>
		<?php echo $form->dropDownList($model, 'q1f',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1f'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1g'); ?>
		<?php echo $form->dropDownList($model, 'q1g',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1g'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1h'); ?>
		<?php echo $form->dropDownList($model, 'q1h',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1h'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q1i'); ?>
		<?php echo $form->dropDownList($model, 'q1i',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q1i'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q2'); ?>
		<?php echo $form->dropDownList($model, 'q2', array('Equipe do IFG' => 'Equipe do IFG','TV' => 'TV','Internet' => 'Internet','Facebook' => 'Facebook','Amigos' => 'Amigos','Carro de som' => 'Carro de som','outro' => 'outro')); ?>
		<?php echo $form->error($model,'q2'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q3'); ?>
		<?php echo $form->dropDownList($model, 'q3',array(0=>'0',1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10')); ?>
		<?php echo $form->error($model,'q3'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'q4'); ?>
		<?php echo $form->textArea($model, 'q4',array('maxlength' => 600, 'rows' => 6, 'cols' => 50)); ?>
		<?php echo $form->error($model,'q4'); ?>
		</div><!-- row -->


<?php
echo GxHtml::submitButton(Yii::t('app', 'Save'));
$this->endWidget();
?>
</div><!-- form -->