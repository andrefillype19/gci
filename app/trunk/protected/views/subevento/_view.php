<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('id_evento')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idEvento)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('nome')); ?>:
	<?php echo GxHtml::encode($data->nome); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('generomasculino')); ?>:
	<?php echo GxHtml::encode($data->generomasculino); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('filepath')); ?>:
	<?php echo GxHtml::encode($data->filepath); ?>
	<br />

</div>