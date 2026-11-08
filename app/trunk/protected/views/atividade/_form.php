<div class="form">


<?php $form = $this->beginWidget('GxActiveForm', array(
	'id' => 'atividade-form',
	'enableAjaxValidation' => false,
));
?>

	<p class="note">
		<?php echo Yii::t('app', 'Fields with'); ?> <span class="required">*</span> <?php echo Yii::t('app', 'are required'); ?>.
	</p>

	<?php echo $form->errorSummary($model);
        $desabilitar= false;
        $roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
        foreach($roles as $role)
            if($role->name != 'Admin' && $this->action->Id=='update')
                $desabilitar= true;
     ?>

        <div class="row">
        <?php echo $form->labelEx($model,'tipoext'); ?>
        <?php echo $form->textField($model, 'tipo'); ?>
        <?php echo $form->error($model,'tipo'); ?>
        </div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'tema'); ?>
		<?php echo $form->textField($model, 'tema', array('maxlength' => 255)); ?>
		<?php echo $form->error($model,'tema'); ?>
		</div><!-- row -->
		<div class="row">
        <?php echo $form->labelEx($model,'Proponente'); ?>
        <?php echo $form->textField($model, 'Proponente'); ?>
        <?php echo $form->error($model,'Proponente'); ?>
        </div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'qtdhoras'); ?>
		<?php echo $form->textField($model, 'qtdhoras'); ?>
		<?php echo $form->error($model,'qtdhoras'); ?>
		</div><!-- row -->
		<div class="row">
        <?php echo $form->labelEx($model,'local'); ?>
        <?php echo $form->textField($model, 'local'); ?>
        <?php echo $form->error($model,'local'); ?>
        </div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'vagasdisponiveis'); ?>
		<?php echo $form->textField($model, 'vagasdisponiveis'); ?>
		<?php echo $form->error($model,'vagasdisponiveis'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'multihorario'); ?>
		<?php echo $form->checkBox($model, 'multihorario',array('disabled'=>$desabilitar)); ?>
		<?php echo $form->error($model,'multihorario'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'presenca'); ?>
		<?php echo $form->checkBox($model, 'presenca'); ?>
		<?php echo $form->error($model,'presenca'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'eletivo'); ?>
		<?php echo $form->checkBox($model, 'eletivo',array('disabled'=>$desabilitar)); ?>
		<?php echo $form->error($model,'eletivo'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'id_evento'); ?>
        <?php
        $criteria = Yii::app()->db->createCommand();
        $result = $criteria->select("id,case WHEN datafinal + Interval '60 days'  > now()  THEN 'Atuais' ELSE 'StandBy' END as tempo, tituloevento as nome")
            ->from('Evento')
            ->order('tempo,unaccent_string(tituloevento)')
            ->queryall();
        echo $form->dropDownList($model, 'id_evento', GxHtml::listDataEx($result,'id','nome','tempo'),
            array(
                'prompt' => Yii::t('app', 'Escolha o evento...'),
                'style' => 'width: 400px',
                'ajax' => array(
                    'type' => 'POST', //request type
                    'url' => CController::createUrl('atividade/dynamicinsc'), //url to call.
                    'update' => '#' . CHtml::activeId($model, 'id_subevento'), //selector to update
                    //'data'=>'js:javascript statement'
                    //leave out the data key to pass all form values through
                )));

        ?>
		<?php echo $form->error($model,'id_evento'); ?>
		</div><!-- row -->
		<div class="row">
        <?php echo $form->labelEx($model,'id_subevento'); ?>
        <?php
        $this->widget('ext.select2.ESelect2', array(
            'model' => $model,
            'attribute' => 'id_subevento',
            'data' => ($model)?GxHtml::listDataEx(Subevento::model()->findAll("id_evento= :idevento",
                [':idevento' =>$model->id_evento ]), 'id','nome'):[],
            'options' => array(
                'placeholder' => 'Escolha um  certificado..........................................................',
                'allowClear' => true,
                'style' => 'width: 400px',
            ),
        )); ?>
        <?php echo $form->error($model,'id_subevento'); ?>
        </div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'data'); ?>
		<?php $form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => $model,
			'attribute' => 'data',
			'value' => $model->data,
			'language'=>'pt',
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'dd-mm-yy',
				),
		    'htmlOptions' => array(
		         'disabled'=>$desabilitar
            ),
			));
; ?>
		<?php echo $form->error($model,'data'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'horainicial'); ?>
		<?php echo $form->textField($model, 'horainicial',array('disabled'=>$desabilitar)); ?>
		<?php echo $form->error($model,'horainicial'); ?>
		</div><!-- row -->
		<div class="row">
		<?php echo $form->labelEx($model,'horafinal'); ?>
		<?php echo $form->textField($model, 'horafinal',array('disabled'=>$desabilitar)); ?>
		<?php echo $form->error($model,'horafinal'); ?>
		</div><!-- row -->

		
		<?php
 /*  Ineficiente!! A quantidade de itens é grande. Se maior que 500, depois de 300 itens não salva.
    // see http://www.yiiframework.com/doc/guide/1.1/en/form.table
    // Note: Can be a route to a config file too,
    //       or create a method 'getMultiModelForm()' in the member model
    //$dadospeoplecompare=GxHtml::listDataEx(Inscricao::model()->findAllAttributes('cpf_dadospessoais', true,""));
    $criteria = Yii::app()->db->createCommand();
    $dadospeople=$criteria->select ('t.id,tu.nome')
        ->from ('Inscricao as t')
        ->leftjoin('DadosPessoais as tu','t.cpf_dadospessoais = tu.cpf')
        ->where('t.id_evento=:param1',array('param1'=>$model->id_evento))
        ->queryall();
    $dadospeople2=array();
    foreach ( $dadospeople as $people)
    {
        $dadospeople2[$people['id']]=$people['id']." ".strtoupper($people['nome']);        
    }
    
    $inscricaoatividadeFormConfig = array(
          'elements'=>array(
            'id_inscricao'=>array(
            'type'=>'dropdownlist',
            'disabled'=>true,
            'items'=>$dadospeople2 
            ),
			'presenca'=>array(
                'type'=>'dropdownlist',
                'items'=>array(''=>'ausente','1'=>'presente'),
            ),
            ),
         ); 
    
        
    $criteria = new CDbCriteria;
    $criteria->select = 't.*,tu.nome';
    $criteria->join = 'INNER JOIN ( select id, nome from "Inscricao" INNER JOIN "DadosPessoais" on "DadosPessoais".cpf = "Inscricao".cpf_dadospessoais
    where id_evento= :param0
      )AS tu ON t.id_inscricao = tu.id';
    $criteria->addCondition('id_atividade = :param1');
    $criteria->order='tu.nome';
    $criteria->params =array(':param1'=>$model->id, ':param0'=>$model->id_evento);
    $ativ= $inscricaoatividade->findAll($criteria);
    
    
    $this->widget('ext.multimodelform.MultiModelForm',array(
            'id' => 'id_inscricaoatividade', //the unique widget id
            'formConfig' => $inscricaoatividadeFormConfig, //the form configuration array
            'model' => $inscricaoatividade, //instance of the form model
            //if submitted not empty from the controller,
            //the form will be rendered with validation errors
            'validatedItems' => $validatedinscricao,
            'allowRemoveItem'=>false,
            'allowAddItem'=>false,
            //array of member instances loaded from db
            'data' => $ativ,
        )); */
    ?>
		

<?php
echo GxHtml::submitButton(Yii::t('app', 'Salvar'));
$this->endWidget();
?>
</div><!-- form -->