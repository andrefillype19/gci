<?php /* @var $this Controller */ ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="language" content="en" />

	<!-- blueprint CSS framework -->
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/screen.css" media="screen, projection" />
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/print.css" media="print" />
	<!--[if lt IE 8]>
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/ie.css" media="screen, projection" />
	<![endif]-->

	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/main.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/form.css" />

	<title><?php echo CHtml::encode($this->pageTitle); ?></title>
</head>

<body>

<div class="container" id="page">

	<div id="header">
		<div id="logo"><?php echo CHtml::encode(Yii::app()->name); ?></div>
	</div><!-- header -->

	
		<?php 
		$roles=Rights::getAssignedRoles(Yii::app()->user->Id); // check for single role
		$admin=false;
        $coord=false;
		foreach($roles as $role)
        {
		  if($role->name == 'Admin')
			 $admin=true;
          if($role->name == 'Coordenadores')
          $coord=true;
        }
			$this->widget('ext.CDropDownMenu.CDropDownMenu',array(
			'style'=> 'default', // vertical ou navbar
            'items'=>array(
                array('label'=>Yii::t('app','Home'), 'url'=>array('/site/index')),
                array('label'=>Yii::t('app','About'), 'url'=>array('/site/page', 'view'=>'about')),
                array('label'=>Yii::t('app','Contact'), 'url'=>array('/site/contact')),
                array('label'=>Yii::t('app','Login'), 'url'=>array('/user/login'),'visible'=>Yii::app()->user->isGuest),
               	array('label'=>Yii::t('app','Perfil'), 'url'=>array('/user/profile'),'visible'=>!Yii::app()->user->isGuest),
               	array('label'=>Yii::t('app','Rights'), 'url'=>array('/rights'), 'visible'=>$admin),
                array('label'=>Yii::t('app','Atividade'), 'url'=>array('/atividade'),'visible'=>($admin || $coord) ),
                array('label'=>Yii::t('app','Equipe'), 'url'=>array('/equipe'),'visible'=>($admin || $coord) ),
                array('label'=>Yii::t('app','Evento'), 'url'=>array('/evento'),'visible'=>$admin,
                	'items'=>array(
            				array('label'=>'Criar','url'=>array('/evento/create'),'visible'=>$admin),
					        array('label'=>'Gerenciar', 'url'=>array('/evento/admin'),'visible'=>$admin),
					        array('label'=>'Certificados', 'url'=>array('/subevento/admin'),'visible'=>$admin),
            		),
            	),        
                array('label'=>Yii::t('app','Inscrição'), 'url'=>array('/inscricao'),'visible'=>!Yii::app()->user->isGuest),
				array('label'=>Yii::t('app','Presença'), 'url'=>array('/inscricaoatividade/admin'),'visible'=>($admin || $coord)), 
                array('label'=>Yii::t('app','Participantes'), 'url'=>array('/dadosPessoais'),'visible'=>($admin),
                	'items'=>array(
            				array('label'=>'Criar','url'=>array('/dadospessoais/create'),'visible'=>$admin,),
					),
            	),        
                array('label'=>Yii::t('app','Logout').' ('.Yii::app()->user->name.')', 'url'=>array('/user/logout'), 'visible'=>!Yii::app()->user->isGuest,),
                
	    ))); ?>
        
	<?php if(isset($this->breadcrumbs)):?>
		<?php $this->widget('zii.widgets.CBreadcrumbs', array(
			'links'=>$this->breadcrumbs,
		)); ?><!-- breadcrumbs -->
	<?php endif?>

	<?php echo $content; ?>

	<div class="clear"></div>

	<div id="footer">
		Copyright &copy; <?php echo date('Y'); ?> by Anna Paula Vieira Silva e Tainá Lima Fonseca.<br/>
		Todos direitos reservados ao IFG<br/>
	</div><!-- footer -->

</div><!-- page -->

</body>
</html>
