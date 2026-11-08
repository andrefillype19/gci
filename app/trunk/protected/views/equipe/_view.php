<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('id_inscricao')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idInscricao)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('id_subevento')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idSubevento)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('campo')); ?>:
	<?php echo GxHtml::encode($data->campo); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('horascertificado')); ?>:
	<?php echo GxHtml::encode($data->horascertificado); ?>
	<br />

</div>