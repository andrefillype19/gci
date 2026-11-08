<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('id_evento')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idEvento)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('cpf_dadospessoais')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->cpfDadospessoais)); ?>
	<br />

</div>