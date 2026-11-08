<?php

/**
 * Use this action to login a user.
 */
class LoginAction extends CAction {

    /**
     * The component name which is you register Simplesamlphp instance in your config/main.php.
     */
    public $simplesamlphpComponentName = 'simplesamlphp';

    /**
     * Where the user is redirected after logout.
     */
    public $redirectAfterLoginTo = array('/');

    /**
     * Simplesamlphp component instance.
     */
    private $simplesamlphpInstance = null;

    /**
     * Init LoginAction.
     */
    public function init() {
        assert(!is_null($this->simplesamlphpComponentName), 'You must set simplesamlphp component name.');
        assert(!empty($this->redirectAfterLoginTo), 'You must set redirect after login to.');

        $componentName = $this->simplesamlphpComponentName;
        $this->simplesamlphpInstance = Yii::app()->$componentName;
    }

    /**
     * Run the login action. The user will be redirected to Simplesamlphp IdP login page, if he successfully login then he will be redirected
     * to this page again. After that login the user to Yii application and then redirect the user to $redirectAfterLoginTo route.
     */
    public function run() {

	    $this->init();

        $this->setRootPathOfAlias();
        //$this->loadRequiredClass();
        $this->simplesamlphpInstance->requireAuth();
        $att = $this->simplesamlphpInstance->getAttributes();

        $cpf=$att['urn:oid:1.3.6.1.4.1.15996.100.1.1.1.1'][0];
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        $profile=Profile::model()->findByAttributes(["cpf"=>$cpf]);




        if ($profile==null) {
            Profile::$regMode = true;
            $username = $att['urn:oid:2.5.4.3'][0];
            $email = $att['urn:oid:0.9.2342.19200300.100.1.3'][0];
            //sometimes it comes with ''
            if (strlen($email) < 3){
                $email = $att['urn:oid:1.2.840.113556.1.2.431'][0];
            }
            //no caso de não houver e-mails permitir o acesso memso assim.
            if ($email==null){
                $email=$att['urn:oid:2.5.4.3'][0].'@ifg.edu.br';
            }

            $name = $att['urn:oid:2.5.4.42'][0] . " " . $att['urn:oid:2.5.4.4'][0];
            $password = random_bytes(8);
            $user = new User;
            $user->username = $username;
            $user->email = $email;
            $user->password = UserModule::encrypting($password);
            $user->status = 1;
            $user->superuser = 0;
            $user->activkey = UserModule::encrypting(microtime() . $password);



            if ($user->validate()) {
                $user->save();
                //Por alguma razão $user->save não está atualizando com o id.
                $user = User::model()->findByAttributes(array('username' => $username));
                $profile = new Profile;
                $profile->user_id = $user->id;
                $profile->firstname = $name;
                $profile->cpf = $cpf;
                if ($profile->save()){
                    $auth = Yii::app()->getModule("rights")->getAuthorizer();
                    $auth->authManager->assign('Authenticated', $profile->user_id);
                }else{
                    Yii::log("Erro login SP : /n".CHtml::errorSummary($profile)."/n".var_dump($profile),Clogger::LEVEL_ERROR);
                    $this->getController()->render('error',['code'=>'500','message'=> CHtml::errorSummary($user).
                        'Seus dados existentes não puderam ser migrados, devido a uma divergência de dados com um usuário já existente.
                        Atualize seu e-mail junto a CORAE, de forma que ele seja seu e-mail pessoal e não de seu responsável.Caso já tenha
                         feio isso, envie um e-mail para uaw@computacaoifg.com.br, com a foto da sua identidade com CPF ou CNH, informando sua matrícula.']);
                    return;
                }

            } else {
                Yii::log("Erro login SP : /n".CHtml::errorSummary($user)."/n".var_dump($user),Clogger::LEVEL_ERROR);
                $this->getController()->render('error',['code'=>'500','message'=> CHtml::errorSummary($user).
                    '<BR> Erro ao utilizar os dados do SUAP. Utilize o acesso geral( por login e senha).
            Confira se o e-mail cadastrado no SUAP/Qacadêmico está correto e acessível. Caso não esteja, procure a CORAE para atualização.']);
                return;
            }
        }
        $user=User::model()->findByPk($profile->user_id);
        $identity=new UserIdentity($user->username,'uselessfield');
        $identity->_id=$profile->user_id;
        Yii::app()->user->login($identity);

        $this->getController()->redirect($this->redirectAfterLoginTo);
    }

    private function setRootPathOfAlias() {
        if (Yii::getPathOfAlias('yii-simplesamlphp') === false) {
            Yii::setPathOfAlias('yii-simplesamlphp', realpath(dirname(__FILE__) . '/..'));
        }
    }

    private function loadRequiredClass() {
        Yii::import('yii-simplesamlphp.components.SSOUserIdentity');
    }

}
