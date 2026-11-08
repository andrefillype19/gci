<?php
/* @var $this SiteController */
$this->pageTitle=Yii::app()->name;
?>
<style>
.center {
display: block;
margin-left: auto;
margin-right: auto;
width: 20%;
}
</style>
<img src="https://computacaoifg.com.br/images/LogoVertical.png" alt="Paris" class="center">
<h1><center>Seja Bem Vindo ao <i><?php echo CHtml::encode(Yii::app()->name); ?></center></i></h1>
<?php

$dataProvider = new CActiveDataProvider('Evento',array(
    'criteria'=>array(
        'condition'=>"(datainscricaof + interval '23 hours') >= NOW() and datainscricaoi <= NOW()",
    ),));

$this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'eventosabertos-grid',
    'itemsCssClass'=>'table table-bordered',
    'summaryText'=>'',
    'dataProvider' => $dataProvider,
    'enablePagination' => true,
    'columns' => array(
        array(
            'name' => 'Eventos com Inscrições abertas',
            'value'=> '$data->tituloevento'
        )
    ),
));
?>

<p>Caso você ainda não tenha uma conta, <a href="index.php?r=user/login">cadastre-se</a>. Logue no sistema e realize a inscrição clicando em "Inscrição->Nova Inscrição".</p>
<p>Este sistema, parte integrante do GUIA(Gerenciador Unificado de Informações Acadêmicas), gerencia os certificados
 emitidos pelo IFG-Câmpus Anápolis quanto a eventos e horas complementares e permite a impressão <i>online</i> dos mesmos.
 Os certificados dos eventos ficam disponíveis em, aproximadamente, duas semanas após o término do evento.</p>




