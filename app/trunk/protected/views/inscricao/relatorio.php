<?php

/* @var $model Relatorio */
/* @var $form CActiveForm */
?>

<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'relatorio-relatorio-form',
	// Please note: When you enable ajax validation, make sure the corresponding
	// controller action is handling ajax validation correctly.
	// See class documentation of CActiveForm for details on this,
	// you need to use the performAjaxValidation()-method described there.
	'enableAjaxValidation'=>false,
)); ?>

	<p class="note">Fields with <span class="required">*</span> are required.</p>

	<?php echo $form->errorSummary($model); ?>

	<div class="row">
		<?php echo $form->label($model,'cpf_dados'); ?>

        <?php
        $roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
        foreach($roles as $role)
            if($role->name == 'Authenticated')
            {
                echo $form->dropDownList($model, 'cpf_dados', GxHtml::listDataEx(
                    DadosPessoais::model()->findAllAttributes(null, true,"cpf = :param1",array(':param1'=>Yii::app()->user->cpf))),array('disabled'=>true));
            }
            else
            {
                echo $form->dropDownList($model, 'cpf_dados', CHtml::listData(DadosPessoais::model()->findAll(array('order'=>'unaccent_string(nome)' )),'cpf',function($m) {
                    return CHtml::encode(str_pad($m->cpf, 11, "0", STR_PAD_LEFT)."-".$m->nome);
                }
                ) );
            }
        ?>


		<?php echo $form->error($model,'cpf_dados'); ?>
	</div>


	<div class="row">
		<?php echo $form->labelEx($model,'matricula'); ?>
		<?php echo $form->textField($model,'matricula'); ?>
		<?php echo $form->error($model,'matricula'); ?>
	</div>


	<div class="row buttons">
		<?php echo CHtml::submitButton('Submit'); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->