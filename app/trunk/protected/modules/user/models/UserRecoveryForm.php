<?php

/**
 * UserRecoveryForm class.
 * UserRecoveryForm is the data structure for keeping
 * user recovery form data. It is used by the 'recovery' action of 'UserController'.
 */
class UserRecoveryForm extends CFormModel {
	public $login_or_email, $user_id;
	
	/**
	 * Declares the validation rules.
	 * The rules state that username and password are required,
	 * and password needs to be authenticated.
	 */
	public function rules()
	{
		return array(
			// username and password are required
			array('login_or_email', 'required'),
			//array('login_or_email', 'match', 'pattern' => '/^([a-z0-9_]+)$|^([a-z0-9_\.]+@([a-z0-9\.]+[a-z0-9]+\.([a-z]{2,4})))$/i','message' => UserModule::t("Incorrect symbols (A-z0-9).")),
			// password needs to be authenticated
			array('login_or_email', 'checkexists'),
		);
	}
	/**
	 * Declares attribute labels.
	 */
	public function attributeLabels()
	{
		return array(
			'login_or_email'=>UserModule::t("Email ou CPF"),
		);
	}
	
	public function checkexists($attribute,$params) {
		if(!$this->hasErrors())  // we only want to authenticate when no input errors
		{
			if (strpos($this->login_or_email,"@")) {
				$user=User::model()->findBySql('Select * from users where email ILIKE :param1',['param1'=>$this->login_or_email]);
				if ($user)
					$this->user_id=$user->id;
			} else
            {
                $cpf = preg_replace('/[^0-9]/', '', $this->login_or_email);

                if (!is_numeric($cpf))
                {
                    $this->addError("login_or_email",'Email ou CPF fora do formato. CPF deve ser apenas números, sem pontos ou traços');
                    return;
                }
				$user=Profile::model()->findByAttributes(array('cpf'=>$cpf));
				if ($user)
					$this->user_id=$user->user_id;
			}
			
			if($user===null)
				if (strpos($this->login_or_email,"@")) {
					$this->addError("login_or_email",'Este e-mail não foi encontrado no sistema, ou seja, você não se registrou com este e-mail ou voce está digitando o e-mail errado. Tente com o seu CPF.');
				} else {
					$this->addError("login_or_email",'Este cpf não foi encontrado no sistema,tente com o seu e-mail');
				}
		}
	}
	
}