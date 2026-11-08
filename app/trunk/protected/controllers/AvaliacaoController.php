<?php

class AvaliacaoController extends GxController {
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
        );
    }


	public function actionView($id) {
		$this->render('view', array(
			'model' => $this->loadModel($id, 'Avaliacao'),
		));
	}

	public function actionCreate($id) {
		$model = new Avaliacao;
        $modelavaliacao=Avaliacao::model()->findAllAttributes(null,true,"id_inscricao = :param1",array('param1'=>$id));
        
        if ( count($modelavaliacao) > 0)
            $this->redirect(array('inscricao/certificado', 'id' => $id)); 
            
		if (isset($_POST['Avaliacao'])) {
			$model->setAttributes($_POST['Avaliacao']);
            $modelinscricao=Inscricao::model()->findbypk($id) ;

            if (Yii::app()->user->role!='Authenticated'||
            ($modelinscricao->cpf_dadospessoais != Yii::app()->user->cpf))
            {
                throw new CHttpException(400, Yii::t('app', 'Permissão negada, acesso a área exclusiva do usuário'));                
            }
            $model->id_inscricao=$id;

			if ($model->save()) {
				if (Yii::app()->getRequest()->getIsAjaxRequest())
					Yii::app()->end();
				else
					$this->redirect(array('inscricao/certificado', 'id' => $id));
			}
		}

		$this->render('create', array( 'model' => $model));
	}

	public function actionUpdate($id) {
		$model = $this->loadModel($id, 'Avaliacao');


		if (isset($_POST['Avaliacao'])) {
			$model->setAttributes($_POST['Avaliacao']);

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
			$this->loadModel($id, 'Avaliacao')->delete();

			if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('admin'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('Avaliacao');
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new Avaliacao('search');
		$model->unsetAttributes();

		if (isset($_GET['Avaliacao']))
			$model->setAttributes($_GET['Avaliacao']);

		$this->render('admin', array(
			'model' => $model,
		));
	}

}