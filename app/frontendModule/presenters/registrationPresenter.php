<?php

/*
 * TODO:
 *  predavani parametru v url
 *  .
 *
 */
namespace frontendModule{
    use Nette\Environment;
    use Nette\Application\UI\Form;
    use \DB;
    
    class registrationPresenter extends \BasePresenter {

        /**
         *
         * @var array
         */
        protected $config;

        public function startup(){
            $bans = DB::bans('ip', $_SERVER['REMOTE_ADDR'])->where('expires > ?', time());
            $this->template->banned = false;
            $this->template->reg = $this->getContext()->parameters['registration'];
            $this->config = $this->getContext()->parameters;
            foreach($bans as $ban){
                $this->template->banned = true;
            }
            parent::startup();
        }

        public function createComponentRegisterForm() {
            $form = new Form;
            #$form->addProtection('Cross Site Request Forgery!');
            $form->addText('username', 'Přezdívka: ')
                    ->addRule(Form::FILLED, 'Musíte vyplnit uživatelské jméno!')
                    ->addRule(function (\Nette\Forms\Controls\TextInput $user){
                        if(count(db::users()->where("username LIKE ? OR urlfragment = ?", array($user->getValue(), \Utilities::string2url($user->getValue()))))){
                            return false;
                        }
                        return true;
                    }, 'Uživatelské jméno je již obsazené.');

            $form->addPassword('password', 'Heslo')
                    ->addRule(Form::FILLED, 'Musíte vyplnit heslo.')
                    ->addRule(Form::LENGTH, 'Heslo je příliš krátké.');

            $form->addText('mail', 'E-mail')
                    ->addRule(Form::EMAIL, 'Email není validní!')
                    ->addRule(function(\Nette\Forms\Controls\TextInput $mail){
                        if(count(db::users_profiles()->where("mail LIKE ?", $mail->getValue()))){
                            return false;
                        }
                        return true;
                    }, 'Emailová adresa je již obsazená.');

            $form->addCheckbox('eula', 'Souhlasím s podmínkami.')
                    ->addRule(Form::FILLED, 'Musíte souhlasit.');

            $form->addText('spambot', 'Toto musíte vyplnit.')
                    ->addRule(Form::LENGTH, 'Špatně.', 0)
                    ->getControlPrototype()->class("hidden");
            $form['spambot']->getLabelPrototype()->class("hidden");

            $form->addGroup('Povinné údaje')->add($form['username'],$form['password'],$form['mail'],$form['eula'],$form['spambot']);

            $form->addSubmit('save', 'Registrovat');
            $form->onSuccess[] = callback($this, 'processRegisterForm');

            $form->setAction($this->link("register"));
            return $form;
        }

        public function processRegisterForm(\Nette\Application\UI\Form $form) {
            if($this->template->banned) return false;
            $data = $form->getValues();
            $data["token"] = md5(uniqid());
            $data["create_time"] = time();
            $data["password"] = sha1($data["password"]);
            unset($data['eula']);
            unset($data['spambot']);
            if(!DB::registration()->where("token = ?", $data["token"])->count()){
                DB::registration()->insert($data);
            }
            $this->getTemplate()->mail = $data["mail"];
            $this->redirect(301, "mail", serialize(array("mail"=> $data["mail"], "token" => $data["token"])));
            return true;
        }

        /**
         *
         * @param string $data
         */
        public function actionMail($data){
            $data = unserialize($data);
            $l = $this->link("//finish", $data["token"]);

            $template = new \Nette\Templating\FileTemplate(__DIR__. '/../templates/registration/sendmail.latte');
            $template->registerFilter(new \Nette\Latte\Engine);
            $template->link =$l;

            $mail = new \Nette\Mail\Message;
            $mail->setFrom($this->config['registration']['mail']);
            $mail->addTo($data['mail']);
            $mail->setHtmlBody($template);
            $mail->send();

            $this->template->mail = $data['mail'];            
      }

        public function actionFinish( $id ){
            $db = $this->context->database;
            if($this->template->banned) return false;
            $this->getTemplate()->message = "";
            $reg = $db->registration()->where("token = ?", $id);
            if(!count($reg)){
                $this->getTemplate()->message = "Registrace nebyla nalezena. Nevypršela už platnost odkazu?";
            }else{
                foreach($reg as $r){
                    $row = $r;
                    break;
                }
                if(DB::users()->where("username LIKE ?", $row["username"])->count() || DB::users()->where("mail = ?", $row["mail"])->count()){
                    $this->getTemplate()->message = "Uživatelské jméno/mail je již obsazen. Je nám líto.";
                    $reg->delete();
                    return false;
                }
                $db->users()->insert(array(
                    "id" => 0,
                    "username" => $row["username"]
                ));
                $db->users_profiles()->insert(array(
                    "id"=>0,
                    "password" => $row["password"],
                    "mail" => $row["mail"],
                    "created" => $row["create_time"],
                    "login"=>0,
                    'urlfragment'=> \Utilities::string2url($row['username'])
                ));
                $db->users_prerferences()->insert(array(
                    "id"=>0,
                    "color" => "#fff"
                ));

                $reg->delete(); /* Vloží se pouze jednou, ale smažou se všechny odpovídající tokeny */
                $this->getTemplate()->message = "Registrace byla dokončena. Nyní se můžete přihlásit.";
            }
        }

        public function actionRecoverpassword(){

        }
    }
}