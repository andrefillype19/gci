<?php

class LogoutController extends Controller
{
	public $defaultAction = 'logout';
	
	/**
	 * Logout the current user and redirect to returnLogoutUrl.
	 */
	public function actionLogout()
	{

		Yii::app()->user->logout();
        $message='Se você usou o IFG-ID, e estiver usando um computador público, outras pessoas podem acessar sua conta, portanto fechem o navegador';
        $this->render('/user/message',array('content'=>$message,'title'=>'Feche o Navegador para concluir a saída do GCI'));
	}

}