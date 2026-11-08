<div class="form">

    <?php $form = $this->beginWidget('GxActiveForm', array(
        'action' => Yii::app()->createUrl($this->route),
        'method' => 'get',
    )); ?>

    <div class="row">
        <?php echo $form->label($model, 'id'); ?>
        <?php echo $form->textField($model, 'id'); ?>
    </div>



    <div class="row">
        <?php echo $form->label($model, 'id_subevento'); ?>
        <?php
        $result = Subevento::model()->findAll(['order' => 'unaccent_string(nome)']);
        echo $form->dropDownList($model, 'id_subevento', GxHtml::listDataEx($result, 'id', 'nome'),
            array(
                'prompt' => Yii::t('app', 'Escolha o evento...'),
                'ajax' => array(
                    'type' => 'POST', //request type
                    'url' => CController::createUrl('equipe/dynamicinsc'), //url to call.
                    //Style: CController::createUrl('currentController/methodToCall')
                    'update' => '#' . CHtml::activeId($model, 'id_inscricao'), //selector to update
                    //'data'=>'js:javascript statement'
                    //leave out the data key to pass all form values through
                )));
        ?>
    </div>

    <div class="row">
        <?php echo $form->label($model, 'id_inscricao'); ?>
        <?php
        $criteria = Yii::app()->db->createCommand();
        $dadospeople = $criteria->select('t.id,upper(tu.nome) as nome')
            ->from('Inscricao as t')
            ->leftjoin('DadosPessoais as tu', 'tu.cpf = t.cpf_dadospessoais')
            ->where('t.id_evento=:param1',[':param1'=>$model->id_subevento->id_evento??0])
            ->order('unaccent_string(tu.nome)')
            ->queryall();

        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_inscricao',
            'data' => GxHtml::listDataEx($dadospeople, 'id', 'nome'),
            'options' => array(
                'placeholder' => 'Escolha um Inscrito..............................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>

    </div>

    <div class="row">
        <?php echo $form->label($model, 'campo'); ?>
        <?php echo $form->textField($model, 'campo', array('maxlength' => 256)); ?>
    </div>

    <div class="row">
        <?php echo $form->label($model, 'horascertificado'); ?>
        <?php echo $form->textField($model, 'horascertificado'); ?>
    </div>

    <div class="row buttons">
        <?php echo GxHtml::submitButton(Yii::t('app', 'Search')); ?>
    </div>

    <?php $this->endWidget(); ?>

</div><!-- search-form -->
