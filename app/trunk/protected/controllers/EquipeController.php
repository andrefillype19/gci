<?php

class EquipeController extends GxController {
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
        );
    }

    public function actionDynamicinsc()
    {

        $selecteditem = $_POST['Equipe']['id_subevento'];
        $subevento=Subevento::model()->findbyPK($selecteditem);
        $criteria = Yii::app()->db->createCommand();
        $dadospeople=$criteria->select ('t.id,upper(tu.nome) as nome')
        ->from ('Inscricao as t')
        ->leftjoin('DadosPessoais as tu','tu.cpf = t.cpf_dadospessoais')
        ->where('id_evento='.$subevento->id_evento)
        ->order('unaccent_string(tu.nome)')
        ->queryall();
        $data=CHtml::listData($dadospeople,'id','nome');
        if ( count($data) > 0)
        {
            foreach($data as $value=>$name)
            {
                echo CHtml::tag('option',
                   array('value'=>$value),CHtml::encode($name),true);
            }
        }
        else
        {
             echo CHtml::tag('option',
                 array('value'=>''),CHtml::encode('Não existe inscrições com o subevento selecionado'),true );
        }
    }


	public function actionView($id) {
		$this->render('view', array(
			'model' => $this->loadModel($id, 'Equipe'),
		));
	}

	public function actionCreate() {
		$model = new Equipe;
        
        

		if (isset($_POST['Equipe'])) {
			$model->setAttributes($_POST['Equipe']);
            
			if ($model->save()) {
				if (Yii::app()->getRequest()->getIsAjaxRequest())
					Yii::app()->end();
				else
					$this->redirect(array('view', 'id' => $model->id));
			}
		}

		$this->render('create', array( 'model' => $model));
	}

	public function actionUpdate($id) {
		$model = $this->loadModel($id, 'Equipe');

		if (isset($_POST['Equipe'])) {
			$model->setAttributes($_POST['Equipe']);
            
			if ($model->save()) {
				$this->redirect(array('view', 'id' => $model->id));
			}
		}

		$this->render('update', array(
				'model' => $model,
				));
	}

	public function actionDelete($id) {
		if (Yii::app()->getRequest()->getIsPostRequest()) {
			$this->loadModel($id, 'Equipe')->delete();

			if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('index'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('Equipe');
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new Equipe('search');
		$model->unsetAttributes();

		if (isset($_GET['Equipe']))
			$model->setAttributes($_GET['Equipe']);

		$this->render('admin', array(
			'model' => $model,
		));
	}

}