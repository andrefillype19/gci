<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array(
'tituloevento',
'instituicaopromotora',
array(
            'name' => 'Data',
            'type' => 'raw',
            'value' => ''
            ),
    ),
)); ?>

<h2><?php echo 'Dados dos Participantes' ?></h2>
<?php

    
    $dados= $model->dadosPessoaises;
    @usort($dados, function($a, $b)
    {
        return strcasecmp( $a->nome, $b->nome) ;
    });
    
    $dataProvider = new CArrayDataProvider($dados, array('keyField' => 'cpf' ));
    $dataProvider->pagination = false; 
        
    $this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'atividade-grid',
    'summaryText'=>'',
    'dataProvider' => $dataProvider,
    'columns' => array(
            array(
                'name'=>'Participante',
                'value'=>'str_pad($data->cpf, 11, "0", STR_PAD_LEFT)." ".strtoupper($data->nome)',
                ),
            array(
                'name'=>'e-mail',
                'value'=>'$data->email',
            ),
            array(
                'name'=>'Assinatura',
                 'htmlOptions'=>array(
                    'width'=>'200',
                 ),
            ),
            array(
                'name'=>'Deficiências',
                'value'=>'(Profile::model()->findByAttributes(["cpf"=>$data->cpf]))->deficiencia',
            ),
            array(
                'name'=>'Recursos Requisitados',
                'value'=>'(Profile::model()->findByAttributes(["cpf"=>$data->cpf]))->recursos',
            ),
        ),
    ));
    /* 
    echo GxHtml::openTag('ul');
    foreach($ativ as $relatedModel) {
        echo GxHtml::openTag('li');
        echo GxHtml::link(GxHtml::encode(GxHtml::valueEx($relatedModel)), array('inscricaoatividade/view', 'id' => GxActiveRecord::extractPkValue($relatedModel, true)));
        echo '<br><br>';
        echo GxHtml::closeTag('li');
    }
    echo GxHtml::closeTag('ul'); */
?>