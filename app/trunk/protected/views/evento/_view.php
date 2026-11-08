<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('tituloevento')); ?>:
	<?php echo GxHtml::encode($data->tituloevento); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('local')); ?>:
	<?php echo GxHtml::encode($data->local); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('instituicaopromotora')); ?>:
	<?php echo GxHtml::encode($data->instituicaopromotora); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('cidade')); ?>:
	<?php echo GxHtml::encode($data->cidade); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('datainicial')); ?>:
	<?php echo GxHtml::encode($data->datainicial); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('datafinal')); ?>:
	<?php echo GxHtml::encode($data->datafinal); ?>
	<br />
	<?php /*
	<?php echo GxHtml::encode($data->getAttributeLabel('datainscricaoi')); ?>:
	<?php echo GxHtml::encode($data->datainscricaoi); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('datainscricaof')); ?>:
	<?php echo GxHtml::encode($data->datainscricaof); ?>
	<br />
	*/ ?>

</div>