<?php
use Phidias\Core\Controller;
use Phidias\Component\Navigation;
use Phidias\Util\Form;
use Phidias\Util\Form\Field;

class Phidias_Samples_Forms_Controller extends Controller
{
    private function buildForm()
    {
        $form = new Form();

        $form->action(Navigation::link('phidias/samples/forms'));

        $form->field(Field::Text('firstname',     'first name', NULL, NULL, 'this is the description'))
             ->field(Field::Text('lastname',      'last name'))
             ->field(Field::TextArea('summary',   'summarize your experience'))
             ->field(Field::Select('gender',  'gender', array(
                0 => 'female',
                1 => 'male'
             )));

        $form->field(Field::factory('Text', 'sometext', 'Dynamic text'));

        $form->fieldset('contact information', array(
            Field::Text('address', 'your address'),
            Field::Text('email', 'your primary email'),
            Field::Text('email2', 'your secondary email')
        ));


        $form->fieldset('some other information', array(
            Field::Text('facebook', 'facebook'),
            Field::Text('twitter', 'twitter'),
            Field::Text('tumblr', 'tumblr')
        ));

        return $form;
    }

    public function main()
    {
        $form = $this->buildForm();

        $form->setValues(array(
            'firstname' => 'jeje',
            'lastname'  => 'jojo',
            'summary'   => 'algun textf sdfd sdfsd fsdf sdfo',
            'gender'    => 0,
            'address'   => '123 Fake St.',
            'sometext'  => 'some value'
        ));

        $this->set('form', $form);
    }

    public function main_post()
    {
        $form = $this->buildForm();
        $form->fetchPost();
    }
}