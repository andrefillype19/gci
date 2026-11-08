<div class="form">


<?php $form = $this->beginWidget('GxActiveForm', array(
	'id' => 'subevento-form',
	'enableAjaxValidation' => false,
	'htmlOptions' => array(
		'enctype' => 'multipart/form-data'
	)
));
?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php echo $form->errorSummary($model); ?>

		<div class="row">
		<?php echo $form->labelEx($model,'id_evento'); ?>
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

		<?php echo $form->error($model,'id_evento'); ?>
		</div><!-- row -->
        <div class="row">
        <?php echo $form->labelEx($model,'tipo'); ?>
        <?php
        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'tipo',
            'data' => GxHtml::listDataEx(Grupohoras::model()->findAll('grau=1'), 'resolucao','nome'),
            'options' => array(
                'placeholder' => 'Escolha o tipo do Certificado.........................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>

        <?php echo $form->error($model,'tipo'); ?>
        </div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'nome'); ?>
		<?php echo $form->textField($model, 'nome'); ?>
		<?php echo $form->error($model,'nome'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'generomasculino'); ?>
		<?php echo $form->checkBox($model, 'generomasculino'); ?>
		<?php echo $form->error($model,'generomasculino'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'filepath'); ?>
		<?php echo $form->fileField($model, 'filepath'); ?>
		<?php echo $form->error($model,'filepath'); ?>
		</div><!-- row -->
        <div class="row">
            <?php echo $form->labelEx($model,'altfile1'); ?>
            <?php echo $form->fileField($model, 'altfile1'); ?>
            <?php echo $form->error($model,'altfile1'); ?>
        </div><!-- row -->
        <div class="row">
            <?php echo $form->labelEx($model,'altfile2'); ?>
            <?php echo $form->fileField($model, 'altfile2'); ?>
            <?php echo $form->error($model,'altfile2'); ?>
        </div><!-- row -->

<?php
echo GxHtml::submitButton(Yii::t('app', 'Save'));
$this->endWidget();
?>

</div><!-- form -->