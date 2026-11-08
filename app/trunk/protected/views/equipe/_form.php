<div class="form">

    <?php $form = $this->beginWidget('GxActiveForm', array(
        'id' => 'equipe-form',
        'enableAjaxValidation' => false,
    ));
    ?>

    <p class="note">
        <?php echo Yii::t('app', 'Fields with'); ?> <span
                class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
        Atenção: O Colaborador deve possuir inscrição no evento.
    </p>

    <?php echo $form->errorSummary($model); ?>


    <div class="row">
        <?php echo $form->labelEx($model, 'id_subevento'); ?>
        <?php

        $criteria = Yii::app()->db->createCommand();
        $result = $criteria->select("u.id as id,case WHEN t.datafinal + Interval '60 days'  > now()  
        THEN 'Atuais' ELSE 'StandBy' END as tempo, nome")
            ->from('subevento as u')
            ->leftJoin('Evento as t','t.id=u.id_evento')
            ->order('tempo,unaccent_string(tituloevento)')
            ->queryall();

        $data=GxHtml::listDataEx($result,'id','nome','tempo');

        //$result = Subevento::model()->findAll(['order' => 'unaccent_string(nome)']);

        echo $form->dropDownList($model, 'id_subevento', $data,
            array(
                'prompt' => Yii::t('app', 'Escolha o Certificado...'),
                'ajax' => array(
                    'type' => 'POST', //request type
                    'url' => CController::createUrl('equipe/dynamicinsc'), //url to call.
                    'update' => '#' . CHtml::activeId($model, 'id_inscricao'), //selector to update
                    //'data'=>'js:javascript statement'
                    //leave out the data key to pass all form values through
                )));
        ?>
        <?php echo $form->error($model, 'id_subevento'); ?>
    </div><!-- row -->


    <div class="row">
        <?php echo $form->labelEx($model, 'id_inscricao'); ?>
        <?php
        if ($model->id_subevento == null) {
            $dadospeople=[];
        } else {
            $subevento = Subevento::model()->findbyPK($model->id_subevento);
            $criteria = Yii::app()->db->createCommand();
            $dadospeople = $criteria->select('t.id,upper(tu.nome) as nome')
                ->from('Inscricao as t')
                ->leftjoin('DadosPessoais as tu', 'tu.cpf = t.cpf_dadospessoais')
                ->where('id_evento=' . $subevento->id_evento)
                ->order('unaccent_string(tu.nome)')
                ->queryall();
        }

        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_inscricao',
            'data' => GxHtml::listDataEx($dadospeople, 'id', 'nome'),
            'options' => array(
                'placeholder' => 'Digite um nome..............................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
                'disabled'=>'disabled'
            ),
        ));
        ?>

        <?php echo $form->error($model, 'id_inscricao'); ?>
    </div><!-- row -->

    <div class="row">
        <?php echo $form->labelEx($model, 'campo'); ?>
        <?php echo $form->textField($model, 'campo', array('maxlength' => 256)); ?>
        <?php echo $form->error($model, 'campo'); ?>
    </div><!-- row -->
    <div class="row">
        <?php echo $form->labelEx($model, 'horascertificado'); ?>
        <?php echo $form->textField($model, 'horascertificado'); ?>
        <?php echo $form->error($model, 'horascertificado'); ?>
    </div><!-- row -->


    <?php
    echo GxHtml::submitButton(Yii::t('app', 'Save'));
    $this->endWidget();
    ?>
</div><!-- form -->