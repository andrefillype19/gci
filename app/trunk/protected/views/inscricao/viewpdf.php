<br><br><br><br><br>
<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array(
                    'Nome',
                    'cpf_dados',
                    'matricula',
                    [
                        'name'=>'Emissão',
                        'value'=>date("d/m/Y H:i:s"),
                    ]
                    ),
)); ?>

<h2><?php echo 'Relatório de Atividades Complementares' ?></h2>
<?php
    $sql = "select g.nome, string_agg(cert,'<br>') as certificados, sum(horas) as horasbrutas, least(sum(horas), c.ch*g.modificador) as horascomput  from 
    (select concat (t1.nome,'-', t1.horas,' horas') as cert, horas, tipo from  ( 
        select y.nome, sum (x.qtdhoras) as horas, y.tipo 
        from  \"DadosPessoais\" as t left join \"Inscricao\" as u on t.cpf=u.cpf_dadospessoais  left join 
                 inscricaoatividade as v  on u.id=v.id_inscricao left join atividade as x on v.id_atividade=x.id  left join subevento as y on y.id=x.id_subevento 
                 left join \"Evento\" as e on e.id=y.id_evento 
        where v.presenca=true AND  t.cpf=:cpf and e.datainicial > :entrada
        group by y.nome, y.tipo 
        union  
	select concat ( y.nome,'-',v.campo) as nome, horascertificado as horas, 3 as tipo
        from  \"DadosPessoais\" as t left join \"Inscricao\" as u on t.cpf=u.cpf_dadospessoais  left join 
                 equipe as v on u.id=v.id_inscricao left join subevento as y on y.id=v.id_subevento 
                 left join \"Evento\" as e on e.id=y.id_evento 
        where  t.cpf=:cpf and e.datainicial > :entrada
        
        ) as t1 order by cert ) as t2
        left join grupohoras as g on g.resolucao=t2.tipo left join cursos as c on c.id=:curso
        where g.grau=1
        group by g.nome, c.ch, g.modificador";

    $command=Yii::app()->db->createCommand($sql);
    $command->bindValue(':entrada', $model->entrada);
    $command->bindValue(':cpf', $model->cpf_dados);
    $command->bindValue(':curso', $model->curso);
    $result1=$command->queryAll();

    $sql="select 'Iniciação Ciêntifica e Tecnológica' as nome, string_agg( p.titulo, '<br>') as certificados , 60 as horasbrutas,60 as horascomput
        from  pesquisa_aluno as pa left join pesquisa as p on p.id=pa.id_pesquisa
        where pa.status=1 and p.status !=2 and id_aluno= :matricula
        group by id_aluno 
        union
        select 'Projetos de Ensino, Pesquisa e Extenção' as nome, string_agg( e.titulo, '<br>') as certificados , 60 as horasbrutas,60 as horascomput
                from  extensao_alunos as ea left join extensao as e on e.id=ea.id_extensao
                where ea.situacao=1 and id_aluno= :matricula
               group by id_aluno 
        union
        select 'Estágio não-obrigatório' as nome, string_agg(local, '<br>') as certificados , 60 as horasbrutas,60 as horascomput
            from estagio
            where status_relatorio=2 and cond_estagio=1 and id_aluno=:matricula
            group by id_aluno ";
    $command=Yii::app()->db2->createCommand($sql);
    $command->bindValue(':matricula', $model->matricula);
    $result2=$command->queryAll();
    $result=array_merge($result1,$result2);


    $resultdp = new CArrayDataProvider($result,array('keyField' => 'nome'));
    $resultdp->pagination = false;

    $this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'horasatividades-grid',
    'summaryText'=>'',
    'dataProvider' => $resultdp,
    'columns' => array(
            array(
                'name'=>'Grupo',
                'value'=>'$data["nome"]',
                'htmlOptions'=>array(
                    'width'=>'200',
                ),
                ),
            array(
                'name'=>'Certificados',
                'type'=>'raw',
                'value'=>'$data["certificados"]',
                ),
            array(
                'name'=>'Soma',
                'value'=>'$data["horasbrutas"]',
                'htmlOptions'=>array(
                    'width'=>'50',
                ),
            ),
            array(
                'name'=>'Horas Convalidadas',
                'value'=>'$data["horascomput"]',
                'htmlOptions'=>array(
                    'width'=>'50',
                ),
            ),
        ),
    ));
?>

