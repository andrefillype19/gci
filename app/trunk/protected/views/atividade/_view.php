<div class="view">

	<?php echo GxHtml::encode($data->getAttributeLabel('data')); ?>:
    <?php echo GxHtml::encode($data->data); ?>
    <br />
    <?php echo GxHtml::encode($data->getAttributeLabel('horainicial')); ?>:
    <?php echo GxHtml::encode($data->horainicial); ?>
    <br />
    <?php echo GxHtml::encode($data->getAttributeLabel('horafinal')); ?>:
    <?php echo GxHtml::encode($data->horafinal); ?>
    <br />
    <?php echo GxHtml::encode($data->getAttributeLabel('id_evento')); ?>:
    <?php echo GxHtml::encode($data->idEvento->tituloevento); ?>
    <br />
    <?php echo GxHtml::encode($data->getAttributeLabel('id_subevento')); ?>:
    <?php echo GxHtml::encode($data->idSubevento->nome); ?>
    <br />
	<?php echo GxHtml::encode($data->getAttributeLabel('tema')); ?>:
	<?php echo GxHtml::link(GxHtml::encode($data->tema), array('view', 'id' => $data->id)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('Proponente')); ?>:
    <?php echo GxHtml::encode($data->Proponente); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('vagasdisponiveis')); ?>:
	<?php echo GxHtml::encode($data->vagasdisponiveis); ?>
	<br />
	<?php /*
	<?php echo GxHtml::encode($data->getAttributeLabel('eletivo')); ?>:
	<?php echo GxHtml::encode($data->eletivo); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('id_evento')); ?>:
		<?php echo GxHtml::encode(GxHtml::valueEx($data->idEvento)); ?>
	<br />
	<?php echo GxHtml::encode($data->getAttributeLabel('id')); ?>:
    <?php echo GxHtml::link(GxHtml::encode($data->id), array('view', 'id' => $data->id)); ?>
	<br />
    <?php echo GxHtml::encode($data->getAttributeLabel('multihorario')); ?>:
    <?php echo GxHtml::encode($data->multihorario); ?>
	<br />
    <?php echo GxHtml::encode($data->getAttributeLabel('qtdhoras')); ?>:
    <?php echo GxHtml::encode($data->qtdhoras); 
	<br />
    <?php echo GxHtml::encode($data->getAttributeLabel('presenca')); ?>:
    <?php echo GxHtml::encode($data->presenca); ?>
	<br />
	*/ ?>

</div>