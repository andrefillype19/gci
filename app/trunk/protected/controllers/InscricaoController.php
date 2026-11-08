<?php

class InscricaoController extends GxController {
    
    public function filters()
    {
        return array(
            'rights', // perform access control for CRUD operations
        );
    }

    public function actionRelatorio()
    {

        $model = new Relatorio;
        if (isset($_POST['Relatorio'])) {
            $model->setAttributes($_POST['Relatorio']);
            if(Yii::app()->user->role == 'Authenticated'){
                $model->cpf_dados=Yii::app()->user->cpf;
            }
            if ($model->validate()) {
                $mpdf=new mPDF('s','A4');
                $mpdf->SetTitle('Relatório de Horas Complementares');
                $mpdf->SetImportUse();
                $mpdf->AddPage();
                $pagecount = $mpdf->SetSourceFile('/home/computacaoifg/www/gci/protected/uploads/paginabranco.pdf');
                //$pagecount = $mpdf->SetSourceFile('D:/Bitnami/wappstack-7.0.9-0/apache2/htdocs/gci/trunk/protected/uploads/paginabranco.pdf');
                $tplId = $mpdf->ImportPage($pagecount);
                $mpdf->UseTemplate($tplId);
                $stylesheet = file_get_contents(Yii::getPathOfAlias('webroot.css') . '/styles.css');
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($this->renderPartial('viewpdf', array('model' => $model), true));
                $mpdf->SetProtection(array('print'));
                $mpdf->Output('declaracao.pdf','I');
                //$this->render('viewpdf', array('model' => $model));
                return;
            }
        }
        $this->render('relatorio',array('model'=>$model));
    }


    public function actionRegistersList()
    {
        $selecteditem = $_POST['Inscricao']['id_evento'];
        $criteria = Yii::app()->db->createCommand();
        $dadospeople=$criteria->select ('t.id,upper(tu.nome) as nome')
            ->from ('Inscricao as t')
            ->leftjoin('DadosPessoais as tu','tu.cpf = t.cpf_dadospessoais')
            ->where('id_evento='.$selecteditem)
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
                array('value'=>''),CHtml::encode('Não existe inscrições com o Evento selecionado'),true );
        }

    }

    public function actionSearchAutocomplete(){
        $dados = $_GET['term'];

        $criteria = new CDbCriteria;
        $criteria->select = "cpf, upper(nome) as nome";
        $criteria->order = "unaccent_string(nome)";
        $criteria->addSearchCondition('nome',$dados,true,'');
        $dadospeople = DadosPessoais::model()->findAll($criteria);

//        $criteria = Yii::app()->db->createCommand();
//        $dadospeople=$criteria->select ('cpf, email, upper(nome) as nome')
//            ->from ('DadosPessoais')
////            ->where('nome LIKE :dado',array('dado'=>"%{$dados}%"))
////            ->addSearchCondition('nome',$dados,true,'')
//            ->order('nome')
//            ->queryall();

        $data = array();
        foreach ($dadospeople as $people){
            $data[] = array(
                'id'=>$people['cpf'],
                'value'=>$people['nome'],
                'label'=>$people['nome'],
            );
        }

//        $data=CHtml::listData($dadospeople,'cpf','nome');
        $json = CJSON::encode($data);
        echo $json;
    }

    public function security($cpf,$id_evento)
    {
        $roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
        foreach($roles as $role)
        {
            if($role->name == 'Authenticated')
                if ( Yii::app()->user->cpf != $cpf )
                    $this->redirect(array('index'));
            if($role->name=='Coordenadores')
            {
                $evento=Evento::model()->find("id = :param1",array('param1'=>$id_evento));
                if( $evento->id_coord != Yii::app()->user->id )
                {
                    $this->redirect(array('index'));
                }          
            }
        }                            
    }

    public function actionCertificado($id)
    {
        $modelInscricao=Inscricao::model()->findbyPK($id);
        //INSCRIÇÃO EXISTE ?
        if($modelInscricao == null)
        {
            $this->redirect(array('index'));
        }
        //é do mesmo CPF do usuário corrente? ou do mesmo grupo se coordenador
        $this->security($modelInscricao->cpf_dadospessoais,$modelInscricao->id_evento);
        
                
        $modelEvento=Evento::model()->findbyPK($modelInscricao->id_evento);
        
        date_default_timezone_set('America/Sao_Paulo');

        //verifica se os certificados estão disponíveis
        if ($modelEvento->printcertificate==false){
            $this->render('_error');
            return;
        }
        
        //ja preencheu a avaliação ???
        $modelavaliacao=Avaliacao::model()->findAllAttributes(null,true,"id_inscricao = :param1",array('param1'=>$id));
        if (count($modelavaliacao) == 0 && Yii::app()->user->role=='Authenticated' )
            $this->redirect(array('avaliacao/create', 'id' => $id));
        
        $modelDados=DadosPessoais::model()->findbyPK($modelInscricao->cpf_dadospessoais);
        $modelDados->nome=mb_strtoupper($modelDados->nome,'UTF-8');
        
        $criteria = Yii::app()->db->createCommand();
        $result=$criteria->select ('id_subevento, sum (qtdhoras) as horascertificado')
        ->from ('inscricaoatividade as t')
        ->leftjoin('atividade as tu','t.id_atividade = tu.id')
        ->where('t.presenca=true AND  t.id_inscricao=:param1',array('param1'=>$id))
        ->group('id_subevento')
        ->queryall();
        
        
        $mpdf=new mPDF('s','A4-L');
        $mpdf->SetTitle('Certificado'); 
        $mpdf->SetImportUse(); 
      
        foreach($result as $certdata)
        {
            // Add First page
            $mpdf->AddPage();
            $modelsubevento = Subevento::model()->findbyPK($certdata['id_subevento']);
            
            $pagecount = $mpdf->SetSourceFile($modelsubevento->filepath);
            
            $tplId = $mpdf->ImportPage($pagecount);
            $actualsize = $mpdf->UseTemplate($tplId);
            
            // The height of the template as it was printed is returned as $actualsize['h']
            // The width of the template as it was printed is returned as $actualsize['w']
            
            //$dataProvider = Atividade::model()->findAllAttributes(array('tema','"Proponente"','qtdhoras'),true);
            $realiz='realizada';
            $dado='da'; 
            
			if ($modelsubevento->generomasculino)
            {
                $realiz='realizado';
                $dado='do'; 
            }
            if ( substr($modelEvento->datainicial,0,2) == substr($modelEvento->datafinal,0,2) )
			{
				$periodo = 	'em '.$this->dataextenso($modelEvento->datafinal);			
			}
			else{
				$periodo =  'no período de '.substr($modelEvento->datainicial,0,2).$this->datameio($modelEvento->datainicial,$modelEvento->datafinal).' a '.$this->dataextenso($modelEvento->datafinal);
			}
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
            <p id="exampleID1">O '.$modelEvento->instituicaopromotora.' certifica que <b>'.$modelDados->nome.
            '</b> participou '.$dado.' <b>'.$modelsubevento->nome. '</b>, '.$realiz.' '.$periodo.', com o total de '.$certdata['horascertificado']. ' horas. </p>
            <p id="exampleID2">'.$modelEvento->cidade.', '.$this->dataextenso($modelEvento->datafinal).'.</p>
            </body>
            </html>' ;
                   
            $mpdf->WriteHTML($html);
            
            $mpdf->AddPage();
        
            
            $criteria2 = new CDbCriteria;
            $criteria2->select = 'tema,"Proponente",sum(qtdhoras) as qtdhoras';
            $criteria2->join = 'INNER JOIN inscricaoatividade AS tu ON tu.id_atividade = t.id';
            $criteria2->addCondition("id_inscricao=:param1 AND id_subevento=:param2 and tu.presenca=true");
            $criteria2->group='tema,"Proponente"';
            $criteria2->params =array(':param1' =>$id,':param2'=>$certdata['id_subevento']);
            $query=Atividade::model()->findAll($criteria2);
            
            
            
            $dataProvider = new CArrayDataProvider($query);
            $dataProvider->pagination = false; 
            $stylesheet = file_get_contents(Yii::getPathOfAlias('webroot.css') . '/styles.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->WriteHTML($this->renderPartial('_gridview', array('dataProvider' => $dataProvider), true));

            }
        //Impressão de Certificados de Monitor/Expositor Científico.
        $extras = Equipe::model()->findAllAttributes('id_inscricao,id_subevento,campo,horascertificado', true,'id_inscricao = :param1',array(':param1'=>$id));
        foreach($extras as $certdata)
        {
            // Add First page
            $mpdf->AddPage();
            $modelsubevento = Subevento::model()->findByPK($certdata->id_subevento);
            $filename=str_replace(" ", "_", $modelsubevento->nome);

            $pagecount = $mpdf->SetSourceFile($modelsubevento->filepath);

            $tplId = $mpdf->ImportPage($pagecount);
            $actualsize = $mpdf->UseTemplate($tplId);

            // The height of the template as it was printed is returned as $actualsize['h']
            // The width of the template as it was printed is returned as $actualsize['w']


            $realiz='realizada';
            $nano='na';
            if ($modelsubevento->generomasculino)
            {
                $realiz='realizado';
                $nano='no';
            }

            if ( substr($modelEvento->datainicial,0,2) == substr($modelEvento->datafinal,0,2) )
            {
                $periodo =  'em '.$this->dataextenso($modelEvento->datafinal);
            }
            else{
                $periodo =  'no período de '.substr($modelEvento->datainicial,0,2).$this->datameio($modelEvento->datainicial,$modelEvento->datafinal).' a '.$this->dataextenso($modelEvento->datafinal);
            }

            if ($certdata->horascertificado === null || $certdata->horascertificado=='' )
                $opcional='.';
            else
                $opcional=', com o total de '.$certdata->horascertificado. ' horas.';

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
            <p id="exampleID1">O '.$modelEvento->instituicaopromotora.' certifica que <b>'.$modelDados->nome.
            '</b> atuou como <b>'.$certdata->campo. '</b>, '.$nano.' <b>'.$modelsubevento->nome.'</b> '.$realiz.' '.$periodo.$opcional.' </p>
            <p id="exampleID2">'.$modelEvento->cidade.', '.$this->dataextenso($modelEvento->datafinal).'.</p>
            </body>
            </html>' ;

            $mpdf->WriteHTML($html);
        }
        if (count($extras)==0 && count($result)==0 )
        {
            $html='
            <html>
            <body>
            <br><br><br><br><br><br><br><br><br>
            <h1>Você não possui presença em nenhuma atividade.</h1>
            </body>
            </html>';
            $mpdf->WriteHTML($html);
        }
        $mpdf->SetProtection(array('print'));
        $mpdf->Output('certificado.pdf','I');

    }


    //Este método não pode ser chamado no Update, apenas no create
    public function actionDynamiccat()
    {
        //a usuário , por cargas dágua, voltou a selecionar a opção "selecione um evento"
        if (((int)$_POST['Inscricao']['id_evento'])==0 )
                return;
        //admins e coordenadores podem realizar inscrição fora do prazo
        if(Yii::app()->user->role=='Admin' || Yii::app()->user->role=='Coordenadores')
            $time="";
        else
            $time ="AND  vagasdisponiveis > 0 AND data+horafinal+'2:00:00' >= now() ";

        $model= new Inscricao;
        $criteria = new CDbCriteria;
        $criteria->select = 'id, data,horainicial,horafinal,tema,id_subevento,eletivo,tipo';
        $criteria->addCondition("id_evento = :para  $time");
        $criteria->order='data asc,horainicial asc, horafinal asc';
        $criteria->params =array(':para' => (int)$_POST['Inscricao']['id_evento']); 
        $query=Atividade::model()->findAll($criteria);
        if ($query==null)
        {
            echo "Desculpe, mas não há atividades com vagas disponíveis para este evento";
            return;
        }
        //lista de atividades nao eletivas que virão marcadas.
        $model->atividades =Atividade::model()->findAllAttributes(
            null,true,"eletivo = false and vagasdisponiveis > 0 and id_evento=:param1 $time",
            array(":param1"=>(int)$_POST['Inscricao']['id_evento']));
        echo 'Selecione as atividades que deseje participar. Atividades indicadas não podem ser removidas !<br>';
        echo GxHtml::activeCheckBoxList($model, 'atividades', GxHtml::encodeEx(GxHtml::listDataEx($query)),array('template'=>'<tr><td>{input}</td><td>{label}</td></tr>'));
    }


    public function actionView($id) {
        $model=$this->loadModel($id, 'Inscricao');
        $this->security($model->cpf_dadospessoais,$model->id_evento);
        
        $this->render('view', array(
            'model' => $model,
        ));
    }


    public function actionCreate() {
        $model = new Inscricao;
        if (isset($_POST['Inscricao'])) {
            $model->setAttributes($_POST['Inscricao']);
            if(Yii::app()->user->role == 'Authenticated'){
                $model->cpf_dadospessoais=Yii::app()->user->cpf;
            }
            $this->security($model->cpf_dadospessoais,$model->id_evento);
            $relatedData = array(
                'atividades' => $_POST['Inscricao']['atividades'] === '' ? null : $_POST['Inscricao']['atividades'],
                );
            if ($_POST['cpf_area']!=null)
            {
                $cpfs = explode("\r\n", $_POST['cpf_area']);
                array_push($cpfs,$model->cpf_dadospessoais);
            }
            if( (Yii::app()->user->role=='Admin' || Yii::app()->user->role=='Coordenadores' ) && $_POST['cpf_area']!=null ) {
                $invalidCPFs = "";
                $validCPFs = "";
                foreach ($cpfs as $cpf) {
                    $cpf=preg_replace('/[^0-9]/', '', $cpf);
                    if (strlen($cpf)<1){
                        continue;
                    }
                    //Se o CPF não estiver no Sistema,  é cadastrado.
                    $people= DadosPessoais::model()->findByPk($cpf);
                    if (!$people){
                        $people= new DadosPessoais();
                        $people->nome="Usuário com CPF $cpf";
                        $people->cpf=$cpf;
                        if (!$people->save()){
                            Yii::app()->user->setFlash('error', "Não foi possível relizar o cadastro do CPF $cpf, pois não é um cpf válido");
                            continue;
                        }
                    }
                    //Se o CPF já tiver feito a inscrição no evento.
                    $inscricao=Inscricao::model()->find("cpf_dadospessoais=:cpf and id_evento=:evento", [
                        ':cpf' => $cpf,
                        ':evento' => $_POST['Inscricao']['id_evento'],
                    ]);

                    if ( count($relatedData["atividades"]) < 1 ){
                        Yii::app()->user->setFlash('error', "Voce não selecionou nenhuma atividade ");
                        break;
                    }


                    if ($inscricao ){
                        foreach ( $relatedData["atividades"] as $idativ){
                            $inscativ=Inscricaoatividade::model()->find("id_inscricao=:insc and id_atividade=:idat", [
                                ':insc' => $inscricao->id,
                                ':idat' => $idativ
                            ]);
                            if (!$inscativ){
                                $inscativ=new Inscricaoatividade();
                                $inscativ->id_inscricao=$inscricao->id;
                                $inscativ->id_atividade=$idativ;
                                if ($inscativ->save())
                                {
                                    Yii::app()->user->setFlash('success', "O CPF $cpf foi cadastrado na atividade  $idativ ");
                                }else {
                                    Yii::app()->user->setFlash('error', "Não foi possível relizar a inscrição do CPF $cpf na atividade $idativ ,devido a erro: ".$inscativ->errors["id_atividade"][0]??""." ".$inscativ->errors["id_inscricao"][0])??"";
                                }
                            }

                        }
                    }else{
                        $inscricao = new Inscricao;
                        $inscricao->setAttributes($_POST['Inscricao']);
                        $inscricao->cpf_dadospessoais = $cpf;
                        if ( $inscricao->saveWithRelated($relatedData))
                        {
                            Yii::app()->user->setFlash('success', "O CPF $cpf foi cadastrado em todas as atividades selecionadas");
                        }else {
                            Yii::app()->user->setFlash('error', "Não foi possível relizar a inscrição do CPF $cpf em nenhuma atividade devido aos erros:".implode(" ",$inscricao->errors));
                        }
                    }

                }
                $this->redirect(array('admin'));
            }
            else {
                if ($model->saveWithRelated($relatedData))
                {
                    Yii::app()->user->setFlash('success','Inscrição realizada com sucesso');
                    if (Yii::app()->getRequest()->getIsAjaxRequest())
                        Yii::app()->end();
                    else
                        $this->redirect(array('view', 'id' => $model->id));
                }
            }
        }
        $this->render('create', array( 'model' => $model));
    }

    public function actionUpdate($id) {
        $model = $this->loadModel($id, 'Inscricao');
        
        //verifica se o prazo de inscrição ainda está ativo
        if(Yii::app()->user->role=='Authenticated')
        {
            $modelEvento=Evento::model()->findbyPk($model->id_evento);
            //verifica se ainda é possível realizar a inscrição de acordo com a data final do evento.
            date_default_timezone_set('America/Sao_Paulo');
            $date1 = new DateTime("now");
            $modelEvento->datainscricaof = str_replace('/', '-', $modelEvento->datainscricaof);
            $date2= new DateTime($modelEvento->datainscricaof .' '.'23:59:00');
            if ( $date2 < $date1)
                $this->redirect(array('view', 'id' => $model->id));
        }
        
        $this->security($model->cpf_dadospessoais,$model->id_evento);

        if (isset($_POST['Inscricao'])) {
            $model->setAttributes($_POST['Inscricao']);
            $this->security($model->cpf_dadospessoais,$model->id_evento);
            $relatedData = array(
                'atividades' => $_POST['Inscricao']['atividades'] === '' ? null : $_POST['Inscricao']['atividades'],
                );

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
            $this->loadModel($id, 'Inscricao')->delete();

            if (!Yii::app()->getRequest()->getIsAjaxRequest())
                $this->redirect(array('admin'));
        } else
            throw new CHttpException(400, Yii::t('app', 'Your request is invalid.'));
    }

    public function actionIndex() {
        
        $roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
        foreach($roles as $role)
            if($role->name == 'Authenticated')
            {
                $profile=Profile::model()->findByAttributes(["cpf"=>Yii::app()->user->cpf]);;

                if ($profile->necesp ==null  )
                {
                    $this->redirect(array('/user/profile/edit'));
                }

                  $dataProvider = new CActiveDataProvider('Inscricao',array(
                  'criteria'=>array(
                  'condition'=>'cpf_dadospessoais='.Yii::app()->user->cpf,
                  ),
                  ));
            }
            else{
              $dataProvider = new CActiveDataProvider('Inscricao');;
            }
         
        $this->render('index', array(
            'dataProvider' => $dataProvider,
        ));
    }

    public function actionAdmin() {
        $model = new Inscricao('search');
        $model->unsetAttributes();

        if (isset($_GET['Inscricao']))
            $model->setAttributes($_GET['Inscricao']);

        $this->render('admin', array(
            'model' => $model,
        ));
    }
    
 function datameio($data, $data2) {

      if (substr($data,3,2)==substr($data2,3,2))
          return ;
        
        if (substr($data,2,1)=='-')
            $data3 = explode("-",$data);
        else 
            $data3 = explode("/",$data);
        
        $mes = $data3[1];

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

        
        return ' de ' .$mes;
    }
    
        function dataextenso($data) {

        if (substr($data,2,1)=='-')
            $data3 = explode("-",$data);
        else 
            $data3 = explode("/",$data);

        $dia = $data3[0];
        $mes = $data3[1];
        $ano = $data3[2];

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