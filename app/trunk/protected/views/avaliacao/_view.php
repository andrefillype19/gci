<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />

	<?php echo GxHtml::encode($data->getAttributeLabel('id_inscricao')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idInscricao)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1a')); ?>:
	<?php echo GxHtml::encode($data->q1a); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1b')); ?>:
	<?php echo GxHtml::encode($data->q1b); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1c')); ?>:
	<?php echo GxHtml::encode($data->q1c); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1d')); ?>:
	<?php echo GxHtml::encode($data->q1d); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1e')); ?>:
	<?php echo GxHtml::encode($data->q1e); ?>
	<br />
	<?php /*
	<?php echo GxHtml::encode($data->getAttributeLabel('q1f')); ?>:
	<?php echo GxHtml::encode($data->q1f); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1g')); ?>:
	<?php echo GxHtml::encode($data->q1g); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1h')); ?>:
	<?php echo GxHtml::encode($data->q1h); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q1i')); ?>:
	<?php echo GxHtml::encode($data->q1i); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q2')); ?>:
	<?php echo GxHtml::encode($data->q2); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q3')); ?>:
	<?php echo GxHtml::encode($data->q3); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('q4')); ?>:
	<?php echo GxHtml::encode($data->q4); ?>
	<br />
	*/ ?>

</div>