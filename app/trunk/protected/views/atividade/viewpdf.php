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

<h2><?php echo GxHtml::encode($model->getRelationLabel('inscricaoatividades')); ?></h2>
<?php
    
    
    /*$ativ= $model->inscricaoatividades;
    @usort($ativ, function($a, $b)
    {
        return strcasecmp( GxHtml::valueEx($a), GxHtml::valueEx($b)) ;
    });*/
    
    $criteria = Yii::app()->db->createCommand();
    $dadospeople=$criteria->select ('t.id_inscricao,tu.nome')
        ->from ('inscricaoatividade as t')
        ->leftjoin('( select id, nome from "Inscricao" INNER JOIN
         "DadosPessoais" on "DadosPessoais".cpf = "Inscricao".cpf_dadospessoais
          where id_evento= :param0) as tu','t.id_inscricao = tu.id')
        ->where('t.id_atividade=:param1',array('param1'=>$model->id,'param0'=>$model->id_evento))
        ->order('tu.nome')
        ->queryall();
    
    
    $dataProvider = new CArrayDataProvider($dadospeople,array('keyField' => 'id_inscricao'));
    $dataProvider->pagination = false;
    
    //$dataProvider2 = new CArrayDataProvider($model->inscricaoatividades); 
        
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
                'name'=>'Assinatura',
                 'htmlOptions'=>array(
                    'width'=>'200',
                 ),
                ),
        ),
    ));
?>
É necessária a inscrição nesta atividade pelo GCI para que a presença seja lançada. Insira seu CPF e assine. Faça agora a sua inscrição no site bit.do/anagci ou altere-a, adicionando esta atividade, pois o prazo de inscrição é até o horário de fim da atividade no sistema.
