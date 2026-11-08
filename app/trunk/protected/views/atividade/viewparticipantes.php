<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array(
        'tema',
        'Proponente',
        array(
            'name' => 'idEvento',
            'type' => 'raw',
            'value' => GxHtml::valueEx($model->idEvento)
        ),
        'idSubevento.nome',
        'data',
        'horainicial',
        'horafinal',
    ),
)); ?>

<h2><?php echo 'Lista de Participantes' ?></h2>
<?php
    
    
    /*$ativ= $model->dadosPessoaises;
    @usort($ativ, function($a, $b)
    {
        return strcasecmp( $a->nome, $b->nome) ;
    }); */


    $criteria = Yii::app()->db->createCommand();
    $dadospeople=$criteria->select ('t.id_inscricao,tu.nome,tu.email')
    ->from ('inscricaoatividade as t')
    ->leftjoin('( select id, nome,email from "Inscricao" INNER JOIN
         "DadosPessoais" on "DadosPessoais".cpf = "Inscricao".cpf_dadospessoais
          where id_evento= :param0) as tu','t.id_inscricao = tu.id')
    ->where('t.id_atividade=:param1',array('param1'=>$model->id,'param0'=>$model->id_evento))
    ->order('tu.nome')
    ->queryall();
    
    $dataProvider = new CArrayDataProvider($dadospeople, array('keyField' => 'id_inscricao' ));
    $dataProvider->pagination = false; 
        
    $this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'atividade-grid',
    'summaryText'=>'',
    'dataProvider' => $dataProvider,
    'columns' => array(
            array(
                'name'=>'Participante',
                'value'=>'$data["id_inscricao"]." ".strtoupper($data["nome"])',
                ),
            array(
                'name'=>'e-mail',
                'value'=>'$data["email"]',
            ),
            array(
                'name'=>'Acessibilidade Necessária',
                 'htmlOptions'=>array(
                    'width'=>'200',
                 ),
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