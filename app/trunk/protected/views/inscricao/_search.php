<div class="form">

<?php $form = $this->beginWidget('GxActiveForm', array(
	'action' => Yii::app()->createUrl($this->route),
	'method' => 'get',
)); ?>

	<div class="row">
		<?php echo $form->label($model, 'id'); ?>
		<?php echo $form->textField($model, 'id'); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'id_evento'); ?>
        <?php

        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_evento',
            'data' => GxHtml::listDataEx(Evento::model()->findAll(['order' => 'unaccent_string(tituloevento)'], 'tituloevento')),
            'options' => array(
                'placeholder' => 'Digite um nome..............................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>
	</div>

	<div class="row">
		<?php echo $form->label($model, 'cpf_dadospessoais');
        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'cpf_dadospessoais',
            'data' => GxHtml::listDataEx(DadosPessoais::model()->findAll(['order' => 'unaccent_string(nome)'], 'nome')),
            'options' => array(
                'placeholder' => 'Escolha um Participante..............................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        ));

        ?>
	</div>

	<div class="row buttons">
		<?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- search-form -->
