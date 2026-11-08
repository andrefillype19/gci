<?php

class EventoController extends GxController {
    
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
 
        );
    }

    public function actionViewparticipantes($id)
    {
        $this->render('viewparticipantes', array(
            'model' => $this->loadModel($id, 'Evento'),
        ));

    }

    public function actionViewpdf($id)
    {       
        $mpdf=new mPDF('s','A4'); 
        // Add First page
        $mpdf->AddPage();
        
        $stylesheet = file_get_contents(Yii::getPathOfAlias('webroot.css') . '/styles2.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $stylesheet = file_get_contents(Yii::getPathOfAlias('webroot.css') . '/styles.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->WriteHTML($this->renderPartial('viewpdf', array('model' => $this->loadModel($id, 'Evento')), true));

        $mpdf->Output();
    }

	public function actionView($id) {
		$this->render('view', array(
			'model' => $this->loadModel($id, 'Evento'),
		));
	}

	private function createUser($model){
        if ( isset($_POST['login']) && isset($_POST['senha']) && $_POST['login']!=''
            && $_POST['senha']!=''){

            $user = new User;
            $user->username=$_POST['login'];
            $user->email=$user->username.'@gcifakeemail.com.br';
            $user->password=UserModule::encrypting($_POST['senha']);
            $user->status=1;
            $user->superuser=0;
            $user->activkey=UserModule::encrypting(microtime().$_POST['senha']);
            $user->save();
            //Por alguma razão $user->save não está atualizando com o id.
            $user=User::model()->findByAttributes(array('username'=>$user->username));
            $auth= Yii::app()->getModule("rights")->getAuthorizer();
            $auth->authManager->assign('Coordenadores',$user->id);
            $model->id_coord=$user->id;
        }
    }

	public function actionCreate() {
		$model = new Evento;

		if (isset($_POST['Evento'])) {
			$model->setAttributes($_POST['Evento']);
			$relatedData = array(
				//'dadosPessoaises' => $_POST['Evento']['dadosPessoaises'] === '' ? null : $_POST['Evento']['dadosPessoaises'],
				);
		    $this->createUser($model);
			if ($model->saveWithRelated($relatedData)) {

				if (Yii::app()->getRequest()->getIsAjaxRequest())
					Yii::app()->end();
				else
					$this->redirect(array('view', 'id' => $model->id));
			}
		}
		$this->render('create', array( 'model' => $model));
	}

	public function actionUpdate($id) {
		$model = $this->loadModel($id, 'Evento');


		if (isset($_POST['Evento'])) {
			$model->setAttributes($_POST['Evento']);
			$relatedData = array(
				//'dadosPessoaises' => $_POST['Evento']['dadosPessoaises'] === '' ? null : $_POST['Evento']['dadosPessoaises'],
				);
            $this->createUser($model);
			if ($model->saveWithRelated($relatedData)) {
				$this->redirect(array('view', 'id' => $model->id));
			}
		}

		$this->render('update', array(
				'model' => $model,
				));
	}

	public function actionDelete($id) { 
		if (Yii::app()->getRequest()->getIsPostRequest()) {
			$inscricao = Inscricao::model() -> findAllbyAttributes(array('id_evento'=>$id));
			if (count($inscricao)>0)
			{
				Yii::app()->user->setFlash('error','Remoção não permitida. Exitem inscrições referenciando esse evento');
			}
			else {
				$this -> loadModel($id, 'Evento')->delete();
			}
			
		if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('admin'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('Evento');
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new Evento('search');
		$model->unsetAttributes();

		if (isset($_GET['Evento']))
			$model->setAttributes($_GET['Evento']);

		$this->render('admin', array(
			'model' => $model,
		));
	}

}