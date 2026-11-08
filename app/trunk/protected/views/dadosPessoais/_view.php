<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('cpf')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->cpf), array('view', 'id' => $data->cpf)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('nome')); ?>:
	<?php echo GxHtml::encode($data->nome); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('email')); ?>:
	<?php echo GxHtml::encode($data->email); ?>
	<br />

</div>