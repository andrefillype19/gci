<?php

class AtividadeController extends GxController {
    
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
 
        );
    }

    public function actionDynamicinsc()
    {
        $selecteditem = $_POST['Atividade']['id_evento'];
        $evento=Evento::model()->findbyPK($selecteditem);
        $data=array();
        if ($evento) {
            $criteria = Yii::app()->db->createCommand();
            $dadospeople = $criteria->select('id,nome')
                ->from('subevento')
                ->where('id_evento=' . $evento->id)
                ->order('unaccent_string(nome)')
                ->queryall();
            $data=CHtml::listData($dadospeople,'id','nome');
        }


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
                array('value'=>''),CHtml::encode('Não existe certificados cadastrados com o evento selecionado.'),true );
        }

    }

    public function actionViewparticipantes($id)
    {
        $this->render('viewparticipantes', array(
            'model' => $this->loadModel($id, 'Atividade'),
        ));

    }
    
    public function actionCertpal($id,$mode)
    {

        $model = $this->loadModel($id, 'Atividade');
        $modelevento= Evento::model()->findByPk($model->id_evento);
        $modelsubevento = Subevento::model()->findByPk($model->id_subevento);
        $arrayproponentes = explode(',', $model->Proponente);
        $mpdf=new mPDF('s','A4-L');
        $mpdf->SetTitle('Certificado'); 
        $mpdf->SetImportUse();
        

        $nano='na'; 
        if ($modelsubevento->generomasculino)
        {
            $nano='no';
        }

        $exibirnomedoevento=$nano. '<b> ' .$modelevento->tituloevento;


        //verifica se a atividade é única no certificado
        $criteria = new CDbCriteria;
        $criteria->select = 'tu.id';
        $criteria->join = 'left join subevento as tu  on t.id_subevento=tu.id';
        $criteria->addCondition("t.id = :param1");
        $criteria->group='tu.id';
        $criteria->having='count(t.id)= 1';
        $criteria->params =array(':param1' =>$model->id);
        $query=Atividade::model()->findAll($criteria);
        if (count($query)>0 ){
            $exibirnomedoevento='';
        }



        foreach ($arrayproponentes as $proponente) {
            
        
        // Add First page
        $mpdf->AddPage();


        $pagecount = $mpdf->SetSourceFile($modelsubevento->filepath);
        if ($mode==2 and $modelsubevento->altfile1!=null)
            $pagecount = $mpdf->SetSourceFile($modelsubevento->altfile1);
        if ($mode==3 and $modelsubevento->altfile2!=null)
                $pagecount = $mpdf->SetSourceFile($modelsubevento->altfile2);

        $tplId = $mpdf->ImportPage($pagecount);
    
        $actualsize = $mpdf->UseTemplate($tplId);
        
        // The height of the template as it was printed is returned as $actualsize['h']
        // The width of the template as it was printed is returned as $actualsize['w']
        
        //$dataProvider = Atividade::model()->findAllAttributes(array('tema','"Proponente"','qtdhoras'),true);
        
        $html='
        <html>
        <head>
        <style>
        p#exampleID1 {
        font-family: "Times New Roman";
        font-size: 24px;
        text-align: justify;
        line-height: 200%;
        
      }
      p#exampleID2{
        font-family: "Times New Roman";
        font-size: 24px;
        text-align: right;
        line-height: 200%;
      }
      
    </style>
  </head>
  <body>
    <br><br><br><br><br><br><br><br><br>
    <p id="exampleID1">O Instituto Federal de Educação, Ciência e Tecnologia de Goiás - Câmpus Anápolis certifica que <b>'.$proponente.
    '</b> ministrou o(a) '.$model->tipo.' <b>'.$model->tema .'</b>, '.$exibirnomedoevento. '</b> em '.$this->dataextenso($model->data).' .</p>
    <p id="exampleID2">'.$modelevento->cidade.', '.$this->dataextenso($modelevento->datafinal).'.</p>
  </body>
</html>' ;
               
        $mpdf->WriteHTML($html);
        
        }
        $mpdf->Output('certificado.pdf','I');
        
        
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
        $model= $this->loadModel($id, 'Atividade');
        $mpdf->WriteHTML($this->renderPartial('viewpdf', array('model' => $model), true));
        
        $mpdf->Output();
        
    }


	public function actionView($id) {
	    $model=$this->loadModel($id, 'Atividade');
	    if(Yii::app()->user->role=='Coordenadores')
        {
                if( $model->idEvento->id_coord != Yii::app()->user->id )
                {
                    $this->redirect(array('index'));
                }          
        }
		$this->render('view', array(
			'model' => $model,
		));
	}
	
	
	public function actionCreate($id_evento=null) {
		$model = new Atividade;
        if ($id_evento!==null){
            $model->id_evento=$id_evento;
        }

		if (isset($_POST['Atividade'])) {
			$model->setAttributes($_POST['Atividade']);
			if(Yii::app()->user->role=='Coordenadores')
			{
				if( $model->idEvento->id_coord != Yii::app()->user->id )
				{
					$this->redirect(array('view', 'id' => 0));
				}          
			}
			if(  $model->save()  )
			{
          	$this->redirect(array('view', 'id' => $model->id));
			}
		}

		 $this->render('create',array(
            'model'=>$model,
        ));
	}

    public function actionOnline($id)
    {
        $model = $this->loadModel($id, 'Atividade');
        $inscricao=Inscricao::model()->find("cpf_dadospessoais=:cpf and id_evento=:evento", [
            ':cpf' =>Yii::app()->user->cpf,
            ':evento' => $model->id_evento,
        ]);
        if ($inscricao){
            $inscativ=Inscricaoatividade::model()->find("id_inscricao=:insc and id_atividade=:idat", [
                ':insc' => $inscricao->id,
                ':idat' => $model->id
            ]);
            if ($inscativ && $model->id==1414){
                date_default_timezone_set('America/Sao_Paulo');
                $now = new DateTime("now");
                $dataativ = str_replace('/', '-', $model->data);
                $ini= new DateTime($dataativ .' '.$model->horainicial);
                $fim=new DateTime($dataativ .' '.$model->horafinal);
                if ( $now > $ini && $now < $fim ) {
                    $inscativ->presenca=true;
                    $inscativ->save();
                    $this->render('sucess',array(
                        'model'=>$model,
                    ));
                }else {
                    $this->render('fail',array(
                        'model'=>$model,
                    ));
                }
            }else {
                $this->redirect(array('inscricao/update', 'id' => $inscricao->id));
            }
        } else{
            $this->redirect(array('inscricao/create'));
        }
    }

    public function actionUpdate($id)
	{
		$model = $this->loadModel($id, 'Atividade');
        $trueletivo=$model->eletivo;
        $truemulti=$model->multihorario;
        
        if(Yii::app()->user->role=='Coordenadores')
        {
           if( $model->idEvento->id_coord != Yii::app()->user->id  )
           {
              $this->redirect(array('view', 'id' => $model->id));
           }          
        }
		if (isset($_POST['Atividade'])) 
		{
			$model->setAttributes($_POST['Atividade']);
            if(Yii::app()->user->role=='Coordenadores')
            {
                $model->eletivo=$trueletivo;
                $model->multihorario=$truemulti;
            }
			if(  $model->save()  )
       		{

				$this->redirect(array('view', 'id' => $model->id));
			 }
		}
		 $this->render('update',array(
            'model'=>$model,
        ));

	}
	public function actionDelete($id) {
		
		if (Yii::app()->getRequest()->getIsPostRequest()) {
			$inscricaoatividade = Inscricaoatividade::model() -> findAllbyAttributes(array('id_atividade'=>$id));
			if (count($inscricaoatividade)>0)
			{
				Yii::app()->user->setFlash('error','Remoção não permitida. Exitem inscrições referenciando essa atividade');
			}
			else {
				$this -> loadModel($id, 'Atividade')->delete();
			}
			
			if (!Yii::app()->getRequest()->getIsAjaxRequest())
				$this->redirect(array('admin'));
		} else
			throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
	}

	public function actionIndex() {
		$dataProvider = new CActiveDataProvider('Atividade',array(
    'criteria'=>array(
        'order'=>'data ASC,horainicial ASC,horafinal ASC',
    )) );
		$this->render('index', array(
			'dataProvider' => $dataProvider,
		));
	}

	public function actionAdmin() {
		$model = new Atividade('search');
		$model->unsetAttributes();

		if (isset($_GET['Atividade']))
			$model->setAttributes($_GET['Atividade']);

		$this->render('admin', array(
			'model' => $model,
		));
	}
    
    function dataextenso($data) {

        $data = explode("/",$data);

        $dia = $data[0];
        $mes = $data[1];
        $ano = $data[2];

        switch ($mes){

        case 1: $mes = "janeiro"; break;
        case 2: $mes = "fevereiro"; break;
        case 3: $mes = "março"; break;
        case 4: $mes = "abril"; break;
        case 5: $mes = "maio"; break;
        case 6: $mes = "junho"; break;
        case 7: $mes = "julho"; break;
        case 8: $mes = "agosto"; break;
        case 9: $mes = "setembro"; break;
        case 10: $mes = "outubro"; break;
        case 11: $mes = "novembro"; break;
        case 12: $mes = "dezembro"; break;

        }


        return $dia.' de ' .$mes .' de '. $ano;
    }
}



