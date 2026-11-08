<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('id_inscricao')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idInscricao)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('id_atividade')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idAtividade)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('presenca')); ?>:
	<?php echo GxHtml::encode($data->presenca); ?>
	<br />

</div>