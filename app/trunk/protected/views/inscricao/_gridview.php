<?php 


$this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'atividade-grid',
    'dataProvider' => $dataProvider,
    'summaryText'=>'',
    'columns' => array(array('header'=>'Tema','name'=>'tema'),'Proponente',array('header'=>'Horas','name'=>'qtdhoras','htmlOptions'=>array('style' => 'text-align: right;'))),
        
    ));



?>