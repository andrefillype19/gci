<?php

class InscricaoatividadeController extends GxController {
    
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
 
        );
    }

    public function actionDynamicativ()
    {
        $selecteditem = $_POST['Inscricaoatividade']['id'];
        if ($selecteditem) {
            $criteria = Yii::app()->db->createCommand();
            $dados = $criteria->select('id,tema')
                ->from('atividade')
                ->where('id_evento= :idevento',[ ':idevento'=>$selecteditem])
                ->order('unaccent_string(tema)')
                ->queryall();

            $data=CHtml::listData($dados,'id','tema');
        }


        if ( count($data) > 0)
        {
            echo CHtml::tag('option',
                array('value'=>0),CHtml::encode('Todas as atividades'),true );
            foreach($data as $value=>$name)
            {
                echo CHtml::tag('option',
                    array('value'=>$value),CHtml::encode($name),true);
            }
        }
        else
        {
            echo CHtml::tag('option',
                array('value'=>''),CHtml::encode('Não existe atividades cadastradas no evento selecionado.'),true );
        }

    }


	public function actionView($id) {
		$this->render('view', array(
			'model' => $this->loadModel($id, 'Inscricaoatividade'),
		));
	}

	public function actionCreate() {
		$model = new Inscricaoatividade;
		$model->presenca=true;
		
		if (isset($_POST['Inscricaoatividade'])) {
			$model->setAttributes($_POST['Inscricaoatividade']);
            
            $modelatividade=Atividade::model()->findbyPK($model->id_atividade);
            if(Yii::app()->user->role=='Coordenadores')
            {
                if( $modelatividade->idEvento->id_coord != Yii::app()->user->id )
                  throw new CHttpException(400, Yii::t('app', 'Você não tem permissão para criar inscrições deste subEvento'));
            }

			if ($model->save()) {
				if (Yii::app()->getRequest()->getIsAjaxRequest())
					Yii::app()->end();
				else {
					Yii::app()->user->setFlash('success', "Presença cadastrada para a inscrição $model->id_inscricao");
					$this->redirect(array('create'));
				}
			}
		}

		$this->render('create', array( 'model' => $model));
	}

	public function actionUpdate($id) {
		$model = $this->loadModel($id, 'Inscricaoatividade');

			if (isset($_POST['Inscricaoatividade'])) {
			$model->setAttributes($_POST['Inscricaoatividade']);
			
			$modelatividade=Atividade::model()->findbyPK($model->id_atividade);
            if(Yii::app()->user->role=='Coordenadores')
            {
                if( $modelatividade->idEvento->id_coord != Yii::app()->user->id )
                  throw new CHttpException(400, Yii::t('app', 'Você não tem permissão para atualizar inscrições deste subEvento'));
            }

			if ($model->save()) {
				$this->redirect(array('view', 'id' => $model->id));
			}
		}

		$this->render('update', array(
				'model' => $model,
				));
	}

	public function actionDelete($id) {
		//hack , estou modificando o comportamento do botão deletar na visão admin para mudar a presença
		
		if (Yii::app()->getRequest()->getIsPostRequest()) {
			if (Yii::app()->getRequest()->getIsAjaxRequest()) {
				$model=$this->loadModel($id, 'Inscricaoatividade');
				$modelatividade=Atividade::model()->findbyPK($model->id_atividade);
				if(Yii::app()->user->role=='Coordenadores')
				{
					if( $modelatividade->idEvento->id_coord != Yii::app()->user->id )
					  throw new CHttpException(400, Yii::t('app', 'Você não tem permissão para alterar atividades deste subEvento'));
				}
				if ($model->presenca==true)
					$model->presenca=false;
				else
					$model->presenca=true;
				if (!$model->save()){
					throw new CHttpException(400,Yii::t('app', 'Erro ao salvar a presença.'));
				}
				return ;
			}
		}
	 	else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
		//comportamento original do delete sem alteração
		/*if (Yii::app()->getRequest()->getIsPostRequest()) {
			$this->loadModel($id, 'Inscricaoatividade')->delete();

			if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('admin'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
		*/
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('Inscricaoatividade');
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new Inscricaoatividade('search');
		$model->unsetAttributes();

		if (isset($_GET['Inscricaoatividade'])) {
            $model->setAttributes($_GET['Inscricaoatividade']);
        }

		$this->render('admin', array(
			'model' => $model,
		));
	}

}