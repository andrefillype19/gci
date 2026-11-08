<?php $this->pageTitle=Yii::app()->name . ' - '.UserModule::t("Registration");
$this->breadcrumbs=array(
	UserModule::t("Registration"),
);
?>

<h1><?php echo UserModule::t("Registration"); ?></h1>

<?php if(Yii::app()->user->hasFlash('registration')): ?>
<div class="success">
<?php echo Yii::app()->user->getFlash('registration'); ?>
</div>
<?php else: ?>

<div class="form">
<?php $form=$this->beginWidget('UActiveForm', array(
	'id'=>'registration-form',
	'enableAjaxValidation'=>false,
	'disableAjaxValidationAttributes'=>array('RegistrationForm_verifyCode'),
	'clientOptions'=>array(
		'validateOnSubmit'=>true,
	),
	'htmlOptions' => array('enctype'=>'multipart/form-data'),
)); ?>

	<p class="note"><?php echo UserModule::t('Fields with <span class="required">*</span> are required.'); ?></p>
	
	<?php echo $form->errorSummary(array($model,$profile)); ?>
  	

	<div class="row">
	<?php echo $form->labelEx($model,'username'); ?>
	<?php echo $form->textField($model,'username'); ?>
	<?php echo $form->error($model,'username'); ?>
	</div>
	
	<div class="row">
	<?php echo $form->labelEx($model,'password'); ?>
	<?php echo $form->passwordField($model,'password'); ?>
	<?php echo $form->error($model,'password'); ?>
	<p class="hint">
	<?php echo UserModule::t("Minimal password length 4 symbols."); ?>
	</p>
	</div>
	
	<div class="row">
	<?php echo $form->labelEx($model,'verifyPassword'); ?>
	<?php echo $form->passwordField($model,'verifyPassword'); ?>
	<?php echo $form->error($model,'verifyPassword'); ?>
	</div>
	
	<div class="row">
	<?php echo $form->labelEx($model,'email'); ?>
	<?php echo $form->textField($model,'email'); ?>
	<?php echo $form->error($model,'email'); ?>
	<p class="hint">
        <?php echo UserModule::t("Você deverá acessar seu e-mail para ativar a sua conta e realizar a inscrição. Tenha certeza que ainda possua o acesso ao e-mail acima."); ?>
    </p>
    
	</div>
	
<?php 
		/*$profileFields=Profile::getFields();
		if ($profileFields) {
			foreach($profileFields as $field) {*/
			?>
	<div class="row">
		<?php  /*echo $form->labelEx($profile,$field->varname);
		if ($widgetEdit = $field->widgetEdit($profile)) {
			echo $widgetEdit;
		} elseif ($field->range) {
			echo $form->dropDownList($profile,$field->varname,Profile::range($field->range));
		} elseif ($field->field_type=="TEXT") {
			echo$form->textArea($profile,$field->varname,array('rows'=>6, 'cols'=>50));
		} else {
			echo $form->textField($profile,$field->varname,array('size'=>60,'maxlength'=>(($field->field_size)?$field->field_size:255)));
		}
		echo $form->error($profile,$field->varname);
            }*/   ?>
	</div>
    <?=$form->labelEx($profile,'firstname'); ?>
    <?=$form->textField($profile,'firstname',array('size'=>60,'maxlength'=>255));?>
    <?=$form->labelEx($profile,'cpf'); ?>
    <?=$form->textField($profile,'cpf',array('size'=>60,'maxlength'=>11));?>
    <?=$form->labelEx($profile,'empresa'); ?>
    <?=$form->textField($profile,'empresa',array('size'=>60,'maxlength'=>255));?>
    <?=$form->labelEx($profile,'necesp'); ?>
    <?=$form->dropDownList($profile,'necesp',Profile::range('1==Não;2==Sim'));?>

    <div id="depdiv1"  <?=$profile->necesp==2?"":"style='display:none'"?>>
        <?=$form->labelEx($profile,'deficiencia');?>
        <table>
        <?= $form->checkBoxlist($profile,'deficiencia',GxHtml::encodeEx(['física'=>'Deficiência Física','visual'=>'Deficiência Visual','auditiva'=>'Deficiência Auditiva','intelectual'=>'Deficiência Intelectual',
            'autista'=>'Transtorno do Espectro Autista','down'=>'Síndrome de Down','tda'=>'Transtorno do Déficit de Atenção e/ou Hiperatividade','superdotado'=>'Altas Habilidades/Superdotação', 'outros'=>'Outros']),
            array('template' => '<tr><td>{label}</td><td>{input}'))?>
        </table>
    </div>

    <?=$form->labelEx($profile,'recurso'); ?>
    <?=$form->dropDownList($profile,'recurso',Profile::range('1==Não;2==Sim'));?>
    <div id="depdiv2" <?=$profile->recurso==2?"":"style='display:none'"?>>
    <?=$form->labelEx($profile,'recursos'); ?>
        <table >
            <?= $form->checkBoxlist($profile,'recursos',['interprete'=>'Tradutor e Intérprete de Língua Brasileira de Sinais','braile'=>'Material impresso em Braille','audiodescrição'=>'Audiodescrição','abafador'=>'Abafador de Ruídos',
                'aumentativa'=>'Tecnologia de Comunicação alternativa ou aumentativa','legenda'=>'Legenda','transcritor'=>'Transcritor','cão'=>'Cão-guia','lupa'=>'Lupa','outros'=>'Outros'],
                array('template' => '<tr><td >{label}</td><td>{input}'))?>
        </table>
        O Instituto Federal de Goiás preza pela inclusão em todas as esferas de atuação, por isso, agradecemos seu interesse e participação em nossos eventos. Faremos o possível para atender à sua necessidade específica. Caso não seja atendida a sua necessidade específica, contamos com sua compreensão e salientamos que estamos em processo de implantação de uma política de inclusão que permita a participação de todos!
    </div>
	<?php if (UserModule::doCaptcha('registration')): ?>
	<div class="row">
		<?php echo $form->labelEx($model,'verifyCode'); ?>
		
		<?php $this->widget('CCaptcha'); ?>
		<?php echo $form->textField($model,'verifyCode'); ?>
		<?php echo $form->error($model,'verifyCode'); ?>
		
		<p class="hint"><?php echo UserModule::t("Please enter the letters as they are shown in the image above."); ?>
		<br/><?php echo UserModule::t("Letters are not case-sensitive."); ?></p>
	</div>
	<?php endif; ?>
	
	<div class="row submit">
		<?php echo CHtml::submitButton(UserModule::t("Register")); ?>
	</div>

<?php $this->endWidget(); ?>
</div><!-- form -->
    <script type="text/javascript">
        $('#Profile_necesp').on('change', function(){
            if (this.value==2)
                $('#depdiv1').show();
            else
                $('#depdiv1').hide();
        });

        $('#Profile_recurso').on('change', function(){
            if (this.value==2)
                $('#depdiv2').show();
            else
                $('#depdiv2').hide();
        });
    </script>

<?php endif; ?>