<?php

class DadosPessoaisController extends GxController {
        
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
 
        );
    }


	public function actionView($id) {
		$this->render('view', array(
			'model' => $this->loadModel($id, 'DadosPessoais'),
		));
	}

	public function actionCreate() {
		$model = new DadosPessoais;


		if (isset($_POST['DadosPessoais'])) {
			$model->setAttributes($_POST['DadosPessoais']);
			$relatedData = array();

			if ($model->saveWithRelated($relatedData)) {
				if (Yii::app()->getRequest()->getIsAjaxRequest())
					Yii::app()->end();
				else
					$this->redirect(array('view', 'id' => $model->cpf));
			}
		}

		$this->render('create', array( 'model' => $model));
	}

	public function actionUpdate($id) {
		$model = $this->loadModel($id, 'DadosPessoais');


		if (isset($_POST['DadosPessoais'])) {
			$model->setAttributes($_POST['DadosPessoais']);
			$relatedData = array();

			if ($model->saveWithRelated($relatedData)) {
				$this->redirect(array('view', 'id' => $model->cpf));
			}
		}

		$this->render('update', array(
				'model' => $model,
				));
	}

	public function actionDelete($id) {
		if (Yii::app()->getRequest()->getIsPostRequest()) {
			$this->loadModel($id, 'DadosPessoais')->delete();

			if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('admin'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('DadosPessoais');
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new DadosPessoais('search');
		$model->unsetAttributes();

		if (isset($_GET['DadosPessoais']))
			$model->setAttributes($_GET['DadosPessoais']);

		$this->render('admin', array(
			'model' => $model,
		));
	}

}