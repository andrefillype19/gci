<?php $this->pageTitle=Yii::app()->name . ' - '.UserModule::t("Profile");
$this->breadcrumbs=array(
	UserModule::t("Profile")=>array('profile'),
	UserModule::t("Edit"),
);
/*$this->menu=array(
	((UserModule::isAdmin())
		?array('label'=>UserModule::t('Manage Users'), 'url'=>array('/user/admin'))
		:array()),
    array('label'=>UserModule::t('List User'), 'url'=>array('/user')),
    array('label'=>UserModule::t('Profile'), 'url'=>array('/user/profile')),
    array('label'=>UserModule::t('Change password'), 'url'=>array('changepassword')),
    array('label'=>UserModule::t('Logout'), 'url'=>array('/user/logout')),
); */
?><h1><?php echo "Preencha estas informações para continuar"?></h1>

<?php if(Yii::app()->user->hasFlash('profileMessage')): ?>
<div class="success">
<?php echo Yii::app()->user->getFlash('profileMessage'); ?>
</div>
<?php endif; ?>
<div class="form">
<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'profile-form',
	'enableAjaxValidation'=>true,
	'htmlOptions' => array('enctype'=>'multipart/form-data'),
)); ?>

	<p class="note"><?php echo UserModule::t('Fields with <span class="required">*</span> are required.'); ?></p>

	<?php echo $form->errorSummary(array($model,$profile)); ?>

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
	<div class="row">
		<?php //echo $form->labelEx($model,'username');
		 echo $form->hiddenField($model,'username',array('size'=>20,'maxlength'=>20));
		 //echo $form->error($model,'username'); ?>
	</div>

	<div class="row">
		<?php /*php echo $form->labelEx($model,'email');
		echo $form->textField($model,'email',array('size'=>60,'maxlength'=>128));
		echo $form->error($model,'email');*/ ?>
	</div>

        <?=$form->labelEx($profile,'necesp'); ?>
        <?=$form->dropDownList($profile,'necesp',Profile::range('1==Não;2==Sim'));?>

        <div id="depdiv1"  <?=$profile->necesp==2?"":"style='display:none'"?>>
            <?=$form->labelEx($profile,'deficiencia');?>
            <table>
                <?= $form->checkBoxlist($profile,'deficiencia',GxHtml::encodeEx(['física'=>'Deficiência Física','visual'=>'Deficiência Visual','auditiva'=>'Deficiência Auditiva','intelectual'=>'Deficiência Intelectual',
                    'autista'=>'Transtorno do Espectro Autista','down'=>'Síndrome de Down','tda'=>'Transtorno do Déficit de Atenção e/ou Hiperatividade','superdotado'=>'Altas Habilidades/Superdotação','outros'=>'Outros']),
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


	<div class="row buttons">
		<?php echo CHtml::submitButton($model->isNewRecord ? UserModule::t('Create') : UserModule::t('Save')); ?>
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